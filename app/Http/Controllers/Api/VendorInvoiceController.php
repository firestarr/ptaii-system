<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\VendorInvoice;
use App\Models\VendorInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\POLine;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Vendor;
use App\Models\Accounting\VendorPayable;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\CurrencyRate;
use App\Http\Requests\VendorInvoiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VendorInvoiceController extends Controller
{
    /**
     * Display a listing of vendor invoices with filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = VendorInvoice::with(['vendor', 'lines.item', 'goodsReceipts']);
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        if ($request->has('date_from') && !empty($request->date_from)) {
            $dateFrom = $request->date_from;
            if (strtotime($dateFrom) !== false) {
                $query->whereDate('invoice_date', '>=', $dateFrom);
            }
        }
        
        if ($request->has('date_to') && !empty($request->date_to)) {
            $dateTo = $request->date_to;
            if (strtotime($dateTo) !== false) {
                $query->whereDate('invoice_date', '<=', $dateTo);
            }
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('invoice_number', 'like', "%{$search}%");
        }
        
        // Filter by currency
        if ($request->has('currency_code')) {
            $query->where('currency_code', $request->currency_code);
        }
        
        // Filter by receipt
        if ($request->has('receipt_id')) {
            $receiptId = $request->receipt_id;
            if (is_array($receiptId) && empty($receiptId)) {
                // Skip applying whereHas if receiptId array is empty to avoid invalid query
            } else {
                $query->whereHas('goodsReceipts', function($q) use ($receiptId) {
                    if (is_array($receiptId)) {
                        $q->whereIn('receipt_id', $receiptId);
                    } else {
                        $q->where('receipt_id', $receiptId);
                    }
                });
            }
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'invoice_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $vendorInvoices = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $vendorInvoices
        ]);
    }

    /**
     * Store a newly created vendor invoice from multiple goods receipts.
     *
     * @param  \App\Http\Requests\VendorInvoiceRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(VendorInvoiceRequest $request)
    {
        try {
            DB::beginTransaction();
            
            // Validate receipt IDs
            $receiptIds = $request->receipt_ids;
            if (empty($receiptIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'At least one goods receipt must be selected'
                ], 400);
            }
            
            // Get all receipts
            $receipts = GoodsReceipt::with(['lines.purchaseOrderLine.purchaseOrder', 'lines.item', 'vendor'])
                ->whereIn('receipt_id', $receiptIds)
                ->get();
                
            if ($receipts->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid goods receipts found'
                ], 404);
            }
            
            // Validate all receipts are from the same vendor
            $vendorId = $receipts->first()->vendor_id;
            if ($receipts->pluck('vendor_id')->unique()->count() > 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'All goods receipts must be from the same vendor'
                ], 400);
            }
            
            // Get vendor
            $vendor = $receipts->first()->vendor;
            
            // Prepare invoice lines
            $invoiceLines = [];
            $subtotal = 0;
            $taxTotal = 0;
            
            // Track which PO currencies we're dealing with
            $poCurrencies = [];
            
            // Set invoice currency (from request, vendor's preferred currency or default)
            $currency = $request->currency_code ?? $vendor->preferred_currency ?? config('app.base_currency', 'USD');
            $baseCurrency = config('app.base_currency', 'USD');
            $invoiceDate = $request->invoice_date ?? now()->format('Y-m-d');
            
            // Get exchange rate if currency is not base currency
            $exchangeRate = 1; // Default for base currency
            if ($currency !== $baseCurrency) {
                if ($request->has('exchange_rate')) {
                    $exchangeRate = $request->exchange_rate;
                } else {
                    // Fetch from database
                    $rateRecord = CurrencyRate::getCurrentRate($currency, $baseCurrency, $invoiceDate);
                    if ($rateRecord) {
                        $exchangeRate = $rateRecord;
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Exchange rate not found for ' . $currency . '. Please enter the exchange rate manually.'
                        ], 422);
                    }
                }
            }
            
            foreach ($receipts as $receipt) {
                // Get uninvoiced lines
                $uninvoicedLines = $receipt->lines()->where('is_invoiced', false)->get();
                
                foreach ($uninvoicedLines as $receiptLine) {
                    // Get the PO line to get the original price
                    $poLine = $receiptLine->purchaseOrderLine;
                    if (!$poLine) {
                        // Skip if no PO line (free text items, etc.)
                        continue;
                    }
                    
                    $po = $poLine->purchaseOrder;
                    
                    // Track PO currencies
                    $poCurrencies[$po->currency_code] = true;
                    
                    // Get price from PO line
                    $lineUnitPrice = $poLine->unit_price;
                    $lineQuantity = $receiptLine->received_quantity;
                    
                    // Calculate tax proportionally
                    $lineTaxRate = $poLine->quantity > 0 ? $poLine->tax / ($poLine->unit_price * $poLine->quantity) : 0;
                    $lineTax = $lineUnitPrice * $lineQuantity * $lineTaxRate;
                    
                    // PO line currency conversion
                    $poLineCurrency = $po->currency_code ?? $baseCurrency;
                    $poExchangeRate = $po->exchange_rate ?: 1;
                    
                    // If PO line currency differs from invoice currency, we need to convert
                    if ($poLineCurrency !== $currency) {
                        // Convert via base currency
                        if ($poLineCurrency !== $baseCurrency) {
                            // First convert from PO currency to base currency
                            $baseUnitPrice = $lineUnitPrice * $poExchangeRate;
                            $baseTax = $lineTax * $poExchangeRate;
                            
                            // Then convert from base currency to invoice currency
                            if ($currency !== $baseCurrency) {
                                $currencyRate = 1 / $exchangeRate; // inverse because we're going from base to invoice
                                $lineUnitPrice = $baseUnitPrice * $currencyRate;
                                $lineTax = $baseTax * $currencyRate;
                            } else {
                                $lineUnitPrice = $baseUnitPrice;
                                $lineTax = $baseTax;
                            }
                        } else {
                            // PO is in base currency, convert directly to invoice currency
                            $currencyRate = 1 / $exchangeRate; // inverse because we're going from base to invoice
                            $lineUnitPrice = $lineUnitPrice * $currencyRate;
                            $lineTax = $lineTax * $currencyRate;
                        }
                    }
                    
                    $lineSubtotal = $lineUnitPrice * $lineQuantity;
                    $lineTotal = $lineSubtotal + $lineTax;
                    
                    $subtotal += $lineSubtotal;
                    $taxTotal += $lineTax;
                    
                    $invoiceLines[] = [
                        'receipt_line_id' => $receiptLine->line_id,
                        'po_line_id' => $poLine->line_id,
                        'po_id' => $po->po_id,
                        'item_id' => $receiptLine->item_id,
                        'quantity' => $lineQuantity,
                        'unit_price' => $lineUnitPrice,
                        'subtotal' => $lineSubtotal,
                        'tax' => $lineTax,
                        'total' => $lineTotal,
                        'currency_code' => $currency,
                        'exchange_rate' => $exchangeRate,
                        'base_currency_unit_price' => $lineUnitPrice * $exchangeRate,
                        'base_currency_subtotal' => $lineSubtotal * $exchangeRate,
                        'base_currency_tax' => $lineTax * $exchangeRate,
                        'base_currency_total' => $lineTotal * $exchangeRate
                    ];
                }
            }
            
            if (empty($invoiceLines)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No uninvoiced receipt lines found in the selected receipts'
                ], 400);
            }
            
            // If using multiple currencies, warn the user
            if (count($poCurrencies) > 1) {
                \Log::warning("Invoice created from POs with multiple currencies: " . implode(", ", array_keys($poCurrencies)));
            }
            
            $totalAmount = $subtotal + $taxTotal;
            
            // Base currency amounts
            $baseCurrencyTotal = $totalAmount * $exchangeRate;
            $baseCurrencyTax = $taxTotal * $exchangeRate;
            
            // Calculate due date based on vendor payment terms if not provided
            $dueDate = $request->due_date;
            if (!$dueDate) {
                $paymentTerm = $vendor->payment_term ?? 30; // Default 30 days
                $dueDate = date('Y-m-d', strtotime($invoiceDate . ' + ' . $paymentTerm . ' days'));
            }
            
            // Determine PO ID if all receipts belong to the same PO
            $poIds = [];
            foreach ($receipts as $receipt) {
                foreach ($receipt->lines as $line) {
                    $poIds[] = $line->po_id;
                }
            }
            $uniquePoIds = array_unique($poIds);
            $poIdToSet = null;
            if (count($uniquePoIds) === 1) {
                $poIdToSet = $uniquePoIds[0];
            }
            
            // Create vendor invoice
            $vendorInvoice = VendorInvoice::create([
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $invoiceDate,
                'vendor_id' => $vendorId,
                'po_id' => $poIdToSet,
                'total_amount' => $totalAmount,
                'tax_amount' => $taxTotal,
                'due_date' => $dueDate,
                'status' => 'pending',
                'currency_code' => $currency,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_currency_total' => $baseCurrencyTotal,
                'base_currency_tax' => $baseCurrencyTax
            ]);
            
            // Create invoice lines and update receipt lines
            foreach ($invoiceLines as $line) {
                $invoiceLine = VendorInvoiceLine::create([
                    'invoice_id' => $vendorInvoice->invoice_id,
                    'po_line_id' => $line['po_line_id'],
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                    'tax' => $line['tax'],
                    'total' => $line['total'],
                    'base_currency_unit_price' => $line['base_currency_unit_price'],
                    'base_currency_subtotal' => $line['base_currency_subtotal'],
                    'base_currency_tax' => $line['base_currency_tax'],
                    'base_currency_total' => $line['base_currency_total']
                ]);
                
                // Update receipt line to mark as invoiced
                GoodsReceiptLine::where('line_id', $line['receipt_line_id'])
                    ->update([
                        'is_invoiced' => true,
                        'invoice_line_id' => $invoiceLine->line_id
                    ]);
            }
            
            // Create relations between invoice and receipts
            foreach ($receipts as $receipt) {
                DB::table('invoice_receipt_relations')->insert([
                    'invoice_id' => $vendorInvoice->invoice_id,
                    'receipt_id' => $receipt->receipt_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            // Create vendor payable
            $vendorPayable = VendorPayable::create([
                'vendor_id' => $vendorId,
                'invoice_id' => $vendorInvoice->invoice_id,
                'amount' => $baseCurrencyTotal,
                'due_date' => $dueDate,
                'paid_amount' => 0,
                'balance' => $baseCurrencyTotal,
                'status' => 'Open',
                'currency_code' => $currency,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_currency_amount' => $baseCurrencyTotal,
                'base_currency_balance' => $baseCurrencyTotal
            ]);
            
            // Create accounting entry if needed
            if ($request->input('create_journal_entry', false)) {
                $this->createJournalEntry($vendorInvoice, $vendorPayable, $request);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor Invoice successfully created',
                'data' => $vendorInvoice->load(['vendor', 'lines.item', 'goodsReceipts'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Vendor Invoice',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Display the specified vendor invoice.
     *
     * @param  \App\Models\VendorInvoice  $vendorInvoice
     * @return \Illuminate\Http\Response
     */
    public function show(VendorInvoice $vendorInvoice)
    {
        $vendorInvoice->load([
            'vendor', 
            'lines.item', 
            'lines.purchaseOrderLine.purchaseOrder',
            'goodsReceipts'
        ]);
        
        // Get additional receipt details
        $receiptDetails = [];
        foreach ($vendorInvoice->goodsReceipts as $receipt) {
            $invoiceLineIds = $vendorInvoice->lines->pluck('line_id')->toArray();
            if (empty($invoiceLineIds)) {
                $receiptLines = collect();
            } else {
                $receiptLines = GoodsReceiptLine::where('receipt_id', $receipt->receipt_id)
                    ->whereNotNull('invoice_line_id')
                    ->whereIn('invoice_line_id', $invoiceLineIds)
                    ->with(['item', 'purchaseOrderLine.purchaseOrder'])
                    ->get();
            }
                
            $receiptDetails[] = [
                'receipt_id' => $receipt->receipt_id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_date' => $receipt->receipt_date,
                'lines' => $receiptLines
            ];
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'invoice' => $vendorInvoice,
                'receipt_details' => $receiptDetails
            ]
        ]);
    }

    /**
     * Update the specified vendor invoice in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\VendorInvoice  $vendorInvoice
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, VendorInvoice $vendorInvoice)
    {
        // Only allow updating certain fields if invoice is in pending status
        if ($vendorInvoice->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only invoices with pending status can be updated'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'invoice_number' => 'sometimes|required|string|max:50|unique:vendor_invoices,invoice_number,' . $vendorInvoice->invoice_id . ',invoice_id',
            'invoice_date' => 'sometimes|required|date',
            'due_date' => 'sometimes|nullable|date|after_or_equal:invoice_date',
            'exchange_rate' => 'sometimes|required|numeric|min:0.000001',
            'currency_code' => 'sometimes|required|string|size:3',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Only allow updating metadata, not lines or receipts
            $dataToUpdate = $request->only([
                'invoice_number',
                'invoice_date',
                'due_date',
                'currency_code',
                'exchange_rate'
            ]);
            
            // If currency or exchange rate changes, need to recalculate base amounts
            $recalculateBase = false;
            if (
                (isset($dataToUpdate['currency_code']) && $dataToUpdate['currency_code'] !== $vendorInvoice->currency_code) ||
                (isset($dataToUpdate['exchange_rate']) && $dataToUpdate['exchange_rate'] !== $vendorInvoice->exchange_rate)
            ) {
                $recalculateBase = true;
            }
            
            if ($recalculateBase) {
                $newExchangeRate = $dataToUpdate['exchange_rate'] ?? $vendorInvoice->exchange_rate;
                
                // Update base currency amounts in invoice
                $dataToUpdate['base_currency_total'] = $vendorInvoice->total_amount * $newExchangeRate;
                $dataToUpdate['base_currency_tax'] = $vendorInvoice->tax_amount * $newExchangeRate;
                
                // Update payable
                $vendorPayable = VendorPayable::where('invoice_id', $vendorInvoice->invoice_id)->first();
                if ($vendorPayable) {
                    $vendorPayable->update([
                        'currency_code' => $dataToUpdate['currency_code'] ?? $vendorInvoice->currency_code,
                        'exchange_rate' => $newExchangeRate,
                        'amount' => $dataToUpdate['base_currency_total'],
                        'balance' => $dataToUpdate['base_currency_total'] - $vendorPayable->paid_amount,
                        'due_date' => $dataToUpdate['due_date'] ?? $vendorPayable->due_date
                    ]);
                }
                
                // Update invoice lines
                foreach ($vendorInvoice->lines as $line) {
                    $line->update([
                        'base_currency_unit_price' => $line->unit_price * $newExchangeRate,
                        'base_currency_subtotal' => $line->subtotal * $newExchangeRate,
                        'base_currency_tax' => $line->tax * $newExchangeRate,
                        'base_currency_total' => $line->total * $newExchangeRate
                    ]);
                }
            }
            
            $vendorInvoice->update($dataToUpdate);
            
            // Update journal entry if exists and requested
            if ($request->input('update_journal_entry', false)) {
                $vendorPayable = VendorPayable::where('invoice_id', $vendorInvoice->invoice_id)->first();
                $this->updateJournalEntry($vendorInvoice, $vendorPayable, $request);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor Invoice successfully updated',
                'data' => $vendorInvoice->load(['vendor', 'lines.item', 'goodsReceipts'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update Vendor Invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified vendor invoice from storage.
     *
     * @param  \App\Models\VendorInvoice  $vendorInvoice
     * @return \Illuminate\Http\Response
     */
    public function destroy(VendorInvoice $vendorInvoice)
    {
        // Check if the invoice has related payments
        $vendorPayable = VendorPayable::where('invoice_id', $vendorInvoice->invoice_id)->first();
        if ($vendorPayable && $vendorPayable->paid_amount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete invoice with payments'
            ], 400);
        }
        
        // Check if invoice status allows deletion
        if (!in_array($vendorInvoice->status, ['pending', 'cancelled'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending or cancelled invoices can be deleted'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Find receipt lines associated with this invoice
            $receiptLineIds = GoodsReceiptLine::where('invoice_line_id', function($query) use ($vendorInvoice) {
                $query->select('line_id')
                    ->from('vendor_invoice_lines')
                    ->where('invoice_id', $vendorInvoice->invoice_id);
            })->pluck('line_id');
            
            // Update receipt lines to mark as not invoiced
            GoodsReceiptLine::whereIn('line_id', $receiptLineIds)
                ->update([
                    'is_invoiced' => false,
                    'invoice_line_id' => null
                ]);
            
            // Delete related journal entries
            $journalEntries = JournalEntry::where('reference_type', 'VendorInvoice')
                ->where('reference_id', $vendorInvoice->invoice_id)
                ->get();
                
            foreach ($journalEntries as $journalEntry) {
                JournalEntryLine::where('journal_id', $journalEntry->journal_id)->delete();
                $journalEntry->delete();
            }
            
            // Delete related vendor payable
            if ($vendorPayable) {
                $vendorPayable->delete();
            }
            
            // Delete invoice receipt relations
            DB::table('invoice_receipt_relations')
                ->where('invoice_id', $vendorInvoice->invoice_id)
                ->delete();
            
            // Delete invoice lines
            $vendorInvoice->lines()->delete();
            
            // Delete the invoice
            $vendorInvoice->delete();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor Invoice successfully deleted'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete Vendor Invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the status of a vendor invoice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\VendorInvoice  $vendorInvoice
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, VendorInvoice $vendorInvoice)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,paid,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $oldStatus = $vendorInvoice->status;
        $newStatus = $request->status;
        
        // Define allowed status transitions
        $allowedTransitions = [
            'pending' => ['approved', 'cancelled'],
            'approved' => ['paid', 'cancelled'],
            'paid' => ['cancelled'],
            'cancelled' => []
        ];
        
        if (!in_array($newStatus, $allowedTransitions[$oldStatus])) {
            return response()->json([
                'status' => 'error',
                'message' => "Status cannot be changed from {$oldStatus} to {$newStatus}"
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Update invoice status
            $vendorInvoice->update([
                'status' => $newStatus
            ]);
            
            // Update related vendor payable status if exists
            $vendorPayable = VendorPayable::where('invoice_id', $vendorInvoice->invoice_id)->first();
            if ($vendorPayable) {
                if ($newStatus === 'paid') {
                    $vendorPayable->update(['status' => 'Paid']);
                } else if ($newStatus === 'cancelled') {
                    $vendorPayable->update(['status' => 'Cancelled']);
                } else if ($newStatus === 'approved') {
                    $vendorPayable->update(['status' => 'Open']);
                }
            }
            
            // If cancelled, update receipt lines to be uninvoiced
            if ($newStatus === 'cancelled') {
                // Find receipt lines associated with this invoice
                $receiptLineIds = GoodsReceiptLine::where('invoice_line_id', function($query) use ($vendorInvoice) {
                    $query->select('line_id')
                        ->from('vendor_invoice_lines')
                        ->where('invoice_id', $vendorInvoice->invoice_id);
                })->pluck('line_id');
                
                // Update receipt lines to mark as not invoiced
                GoodsReceiptLine::whereIn('line_id', $receiptLineIds)
                    ->update([
                        'is_invoiced' => false,
                        'invoice_line_id' => null
                    ]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor Invoice status successfully updated',
                'data' => $vendorInvoice
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update Vendor Invoice status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get exchange rate for a specific date.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getExchangeRate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_code' => 'required|string|size:3',
            'date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $currency = $request->currency_code;
        $date = $request->date;
        $baseCurrency = config('app.base_currency', 'USD');
        
        // If requesting base currency, rate is always 1
        if ($currency === $baseCurrency) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'currency_code' => $baseCurrency,
                    'rate' => 1,
                    'date' => $date
                ]
            ]);
        }
        
        // Get exchange rate
        $rate = CurrencyRate::getCurrentRate($currency, $baseCurrency, $date);
        
        if (!$rate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Exchange rate not found for ' . $currency . ' on date ' . $date
            ], 404);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'currency_code' => $currency,
                'rate' => $rate,
                'date' => $date
            ]
        ]);
    }
    
    /**
     * Get uninvoiced receipts for a vendor.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getUninvoicedReceipts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,vendor_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $vendorId = $request->vendor_id;
        
        // Get receipts that are not fully invoiced
        $receipts = GoodsReceipt::where('vendor_id', $vendorId)
            ->where('status', 'confirmed')
            ->whereHas('lines', function($query) {
                $query->where('is_invoiced', false);
            })
            ->with([
                'lines' => function($query) {
                    $query->where('is_invoiced', false)
                        ->with(['purchaseOrderLine.purchaseOrder', 'item', 'warehouse']);
                }, 
                'vendor'
            ])
            ->get();
        
        // Calculate uninvoiced amounts and prepare response
        $formattedReceipts = $receipts->map(function($receipt) {
            $uninvoicedLines = $receipt->lines->map(function($line) {
                $poLine = $line->purchaseOrderLine;
                return [
                    'line_id' => $line->line_id,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item->item_code,
                    'item_name' => $line->item->name,
                    'po_number' => $poLine ? $poLine->purchaseOrder->po_number : null,
                    'po_id' => $poLine ? $poLine->purchaseOrder->po_id : null,
                    'po_line_id' => $poLine ? $poLine->line_id : null,
                    'quantity' => $line->received_quantity,
                    'unit_price' => $poLine ? $poLine->unit_price : 0,
                    'tax' => $poLine ? ($poLine->tax / $poLine->quantity * $line->received_quantity) : 0,
                    'subtotal' => $poLine ? ($poLine->unit_price * $line->received_quantity) : 0,
                    'total' => $poLine ? ($poLine->unit_price * $line->received_quantity + 
                        ($poLine->tax / $poLine->quantity * $line->received_quantity)) : 0,
                    'currency_code' => $poLine ? $poLine->purchaseOrder->currency_code : null,
                    'warehouse_id' => $line->warehouse_id,
                    'warehouse_name' => $line->warehouse->name,
                    'batch_number' => $line->batch_number
                ];
            });
            
            // Group by currency
            $currencies = [];
            foreach ($uninvoicedLines as $line) {
                $currencyCode = $line['currency_code'] ?? 'UNKNOWN';
                if (!isset($currencies[$currencyCode])) {
                    $currencies[$currencyCode] = [
                        'subtotal' => 0,
                        'tax' => 0,
                        'total' => 0
                    ];
                }
                
                $currencies[$currencyCode]['subtotal'] += $line['subtotal'];
                $currencies[$currencyCode]['tax'] += $line['tax'];
                $currencies[$currencyCode]['total'] += $line['total'];
            }
            
            return [
                'receipt_id' => $receipt->receipt_id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_date' => $receipt->receipt_date,
                'uninvoiced_lines' => $uninvoicedLines,
                'uninvoiced_count' => $uninvoicedLines->count(),
                'po_numbers' => $receipt->getPoNumbersAttribute(),
                'totals_by_currency' => $currencies
            ];
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $formattedReceipts
        ]);
    }
    
    /**
     * Create journal entry for a vendor invoice.
     *
     * @param  \App\Models\VendorInvoice  $vendorInvoice
     * @param  \App\Models\Accounting\VendorPayable  $vendorPayable
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    private function createJournalEntry(VendorInvoice $vendorInvoice, VendorPayable $vendorPayable, $request)
    {
        // Validate required account IDs
        $validator = Validator::make($request->all(), [
            'ap_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id',
            'expense_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id',
            'tax_account_id' => 'required_if:create_journal_entry,true|exists:ChartOfAccount,account_id'
        ]);
        
        if ($validator->fails()) {
            throw new \Exception('GL accounts required to create journal entry: ' . json_encode($validator->errors()));
        }
        
        // Get account IDs
        $apAccountId = $request->ap_account_id;
        $expenseAccountId = $request->expense_account_id;
        $taxAccountId = $request->tax_account_id;
        
        // Get current period
        $periodId = $this->getCurrentPeriodId();
        
        // Create journal entry
        $journalEntry = JournalEntry::create([
            'journal_number' => 'VINV-' . $vendorInvoice->invoice_id,
            'entry_date' => $vendorInvoice->invoice_date,
            'reference_type' => 'VendorInvoice',
            'reference_id' => $vendorInvoice->invoice_id,
            'description' => 'Invoice from ' . $vendorInvoice->vendor->name . ' - ' . $vendorInvoice->invoice_number,
            'period_id' => $periodId,
            'status' => 'Posted'
        ]);
        
        // Calculate amounts
        $subtotal = $vendorInvoice->total_amount - $vendorInvoice->tax_amount;
        $taxAmount = $vendorInvoice->tax_amount;
        $totalAmount = $vendorInvoice->total_amount;
        
        // Base currency amounts
        $baseCurrencySubtotal = $subtotal * $vendorInvoice->exchange_rate;
        $baseCurrencyTaxAmount = $taxAmount * $vendorInvoice->exchange_rate;
        $baseCurrencyTotalAmount = $totalAmount * $vendorInvoice->exchange_rate;
        
        // Create journal entry lines
        
        // Debit Expense Account (subtotal amount)
        JournalEntryLine::create([
            'journal_id' => $journalEntry->journal_id,
            'account_id' => $expenseAccountId,
            'debit_amount' => $baseCurrencySubtotal,
            'credit_amount' => 0,
            'description' => 'Expense from ' . $vendorInvoice->vendor->name,
            'currency_code' => $vendorInvoice->currency_code,
            'foreign_amount' => $subtotal
        ]);
        
        // Debit Tax Account (tax amount, if any)
        if ($taxAmount > 0) {
            JournalEntryLine::create([
                'journal_id' => $journalEntry->journal_id,
                'account_id' => $taxAccountId,
                'debit_amount' => $baseCurrencyTaxAmount,
                'credit_amount' => 0,
                'description' => 'Tax from ' . $vendorInvoice->vendor->name,
                'currency_code' => $vendorInvoice->currency_code,
                'foreign_amount' => $taxAmount
            ]);
        }
        
        // Credit Accounts Payable (total amount)
        JournalEntryLine::create([
            'journal_id' => $journalEntry->journal_id,
            'account_id' => $apAccountId,
            'debit_amount' => 0,
            'credit_amount' => $baseCurrencyTotalAmount,
            'description' => 'Payable to ' . $vendorInvoice->vendor->name,
            'currency_code' => $vendorInvoice->currency_code,
            'foreign_amount' => $totalAmount
        ]);
    }
    
    /**
     * Update journal entry for a vendor invoice.
     *
     * @param  \App\Models\VendorInvoice  $vendorInvoice
     * @param  \App\Models\Accounting\VendorPayable  $vendorPayable
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    private function updateJournalEntry(VendorInvoice $vendorInvoice, VendorPayable $vendorPayable, $request)
    {
        // Find existing journal entry
        $journalEntry = JournalEntry::where('reference_type', 'VendorInvoice')
            ->where('reference_id', $vendorInvoice->invoice_id)
            ->first();
            
        if (!$journalEntry) {
            // If no existing journal entry, create a new one
            $this->createJournalEntry($vendorInvoice, $vendorPayable, $request);
            return;
        }
        
        // Validate required account IDs
        $validator = Validator::make($request->all(), [
            'ap_account_id' => 'required_if:update_journal_entry,true|exists:ChartOfAccount,account_id',
            'expense_account_id' => 'required_if:update_journal_entry,true|exists:ChartOfAccount,account_id',
            'tax_account_id' => 'required_if:update_journal_entry,true|exists:ChartOfAccount,account_id'
        ]);
        
        if ($validator->fails()) {
            throw new \Exception('GL accounts required to update journal entry: ' . json_encode($validator->errors()));
        }
        
        // Get account IDs
        $apAccountId = $request->ap_account_id;
        $expenseAccountId = $request->expense_account_id;
        $taxAccountId = $request->tax_account_id;
        
        // Update journal entry header
        $journalEntry->update([
            'entry_date' => $vendorInvoice->invoice_date,
            'description' => 'Invoice from ' . $vendorInvoice->vendor->name . ' - ' . $vendorInvoice->invoice_number
        ]);
        
        // Delete existing journal entry lines
        JournalEntryLine::where('journal_id', $journalEntry->journal_id)->delete();
        
        // Calculate amounts
        $subtotal = $vendorInvoice->total_amount - $vendorInvoice->tax_amount;
        $taxAmount = $vendorInvoice->tax_amount;
        $totalAmount = $vendorInvoice->total_amount;
        
        // Base currency amounts
        $baseCurrencySubtotal = $subtotal * $vendorInvoice->exchange_rate;
        $baseCurrencyTaxAmount = $taxAmount * $vendorInvoice->exchange_rate;
        $baseCurrencyTotalAmount = $totalAmount * $vendorInvoice->exchange_rate;
        
        // Create new journal entry lines
        
        // Debit Expense Account (subtotal amount)
        JournalEntryLine::create([
            'journal_id' => $journalEntry->journal_id,
            'account_id' => $expenseAccountId,
            'debit_amount' => $baseCurrencySubtotal,
            'credit_amount' => 0,
            'description' => 'Expense from ' . $vendorInvoice->vendor->name,
            'currency_code' => $vendorInvoice->currency_code,
            'foreign_amount' => $subtotal
        ]);
        
        // Debit Tax Account (tax amount, if any)
        if ($taxAmount > 0) {
            JournalEntryLine::create([
                'journal_id' => $journalEntry->journal_id,
                'account_id' => $taxAccountId,
                'debit_amount' => $baseCurrencyTaxAmount,
                'credit_amount' => 0,
                'description' => 'Tax from ' . $vendorInvoice->vendor->name,
                'currency_code' => $vendorInvoice->currency_code,
                'foreign_amount' => $taxAmount
            ]);
        }
        
        // Credit Accounts Payable (total amount)
        JournalEntryLine::create([
            'journal_id' => $journalEntry->journal_id,
            'account_id' => $apAccountId,
            'debit_amount' => 0,
            'credit_amount' => $baseCurrencyTotalAmount,
            'description' => 'Payable to ' . $vendorInvoice->vendor->name,
            'currency_code' => $vendorInvoice->currency_code,
            'foreign_amount' => $totalAmount
        ]);
    }
    
    /**
     * Get current accounting period ID.
     *
     * @return int
     * @throws \Exception
     */
    private function getCurrentPeriodId()
    {
        $currentPeriod = DB::table('AccountingPeriod')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('status', 'Open')
            ->first();
        
        if (!$currentPeriod) {
            throw new \Exception('No active accounting period found for the current date');
        }
        
        return $currentPeriod->period_id;
    }
}
<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesInvoice;
use App\Models\Sales\SalesInvoiceLine;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SOLine;
use App\Models\Item;
use App\Models\Sales\Delivery;
use App\Models\Sales\DeliveryLine;
use App\Models\Accounting\CustomerReceivable;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SalesInvoiceController extends Controller
{
    /**
     * Display a listing of the sales invoices.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = SalesInvoice::with(['customer', 'delivery']);
        
        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        // Search by invoice number or customer name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }
        
        // Date range filters
        if ($request->has('date_range')) {
            $today = now()->format('Y-m-d');
            
            switch ($request->date_range) {
                case 'today':
                    $query->whereDate('invoice_date', $today);
                    break;
                case 'week':
                    $query->whereBetween('invoice_date', [
                        now()->startOfWeek()->format('Y-m-d'),
                        now()->endOfWeek()->format('Y-m-d')
                    ]);
                    break;
                case 'month':
                    $query->whereBetween('invoice_date', [
                        now()->startOfMonth()->format('Y-m-d'),
                        now()->endOfMonth()->format('Y-m-d')
                    ]);
                    break;
            }
        } else if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('invoice_date', [$request->start_date, $request->end_date]);
        }
        
        // Sorting
        $sortField = $request->input('sort_field', 'invoice_id');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $invoices = $query->paginate($request->input('per_page', 10));
        
        return response()->json($invoices, 200);
    }

    /**
     * Store a newly created sales invoice in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|unique:SalesInvoice,invoice_number',
            'invoice_date' => 'required|date',
            'customer_id' => 'required|exists:Customer,customer_id',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'status' => 'required|string|max:50',
            'currency_code' => 'nullable|string|size:3',
            'do_id' => 'nullable|exists:Delivery,delivery_id',
            'lines' => 'required|array',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.uom_id' => 'required|exists:unit_of_measures,uom_id',
            'lines.*.do_line_id' => 'nullable|exists:DeliveryLine,line_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Determine currency and exchange rate
            $baseCurrency = config('app.base_currency', 'USD');
            $currencyCode = $request->currency_code ?? $baseCurrency;
            
            // Get exchange rate if invoice currency is different from base currency
            $exchangeRate = 1.0; // Default for base currency
            
            if ($currencyCode !== $baseCurrency) {
                $rate = CurrencyRate::where('from_currency', $currencyCode)
                    ->where('to_currency', $baseCurrency)
                    ->where('is_active', true)
                    ->where('effective_date', '<=', $request->invoice_date)
                    ->where(function($query) use ($request) {
                        $query->where('end_date', '>=', $request->invoice_date)
                              ->orWhereNull('end_date');
                    })
                    ->orderBy('effective_date', 'desc')
                    ->first();
                    
                if (!$rate) {
                    // Try to find a reverse rate
                    $reverseRate = CurrencyRate::where('from_currency', $baseCurrency)
                        ->where('to_currency', $currencyCode)
                        ->where('is_active', true)
                        ->where('effective_date', '<=', $request->invoice_date)
                        ->where(function($query) use ($request) {
                            $query->where('end_date', '>=', $request->invoice_date)
                                  ->orWhereNull('end_date');
                        })
                        ->orderBy('effective_date', 'desc')
                        ->first();
                        
                    if ($reverseRate) {
                        $exchangeRate = 1 / $reverseRate->rate;
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'No exchange rate found for the specified currency on the invoice date'
                        ], 422);
                    }
                } else {
                    $exchangeRate = $rate->rate;
                }
            }
            
            $totalAmount = 0;
            $taxAmount = 0;

            // Create sales invoice
            $invoice = SalesInvoice::create([
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date,
                'customer_id' => $request->customer_id,
                'do_id' => $request->do_id,
                'due_date' => $request->due_date,
                'status' => $request->status,
                'reference' => $request->reference ?? null,
                'payment_terms' => $request->payment_terms ?? null,
                'total_amount' => 0, // Will be updated later
                'tax_amount' => 0,   // Will be updated later
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_currency_total' => 0, // Will be updated later
                'base_currency_tax' => 0    // Will be updated later
            ]);

            // Create invoice lines
            foreach ($request->lines as $line) {
                // Get delivery line if specified
                $doLine = null;
                if (isset($line['do_line_id'])) {
                    $doLine = DeliveryLine::with('salesOrderLine')
                        ->find($line['do_line_id']);
                }
                
                // Calculate amounts
                $unitPrice = $line['unit_price'];
                $quantity = $line['quantity'];
                $subtotal = $unitPrice * $quantity;
                $discount = $line['discount'] ?? 0;
                $tax = $line['tax'] ?? 0;
                $total = $subtotal - $discount + $tax;
                
                // Calculate base currency amounts
                $baseUnitPrice = $unitPrice * $exchangeRate;
                $baseSubtotal = $subtotal * $exchangeRate;
                $baseDiscount = $discount * $exchangeRate;
                $baseTax = $tax * $exchangeRate;
                $baseTotal = $total * $exchangeRate;
                
                SalesInvoiceLine::create([
                    'invoice_id' => $invoice->invoice_id,
                    'do_line_id' => $line['do_line_id'] ?? null,
                    'so_line_id' => $doLine && $doLine->salesOrderLine ? $doLine->salesOrderLine->line_id : null,
                    'item_id' => $line['item_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'uom_id' => $line['uom_id'],
                    'base_currency_unit_price' => $baseUnitPrice,
                    'base_currency_subtotal' => $baseSubtotal,
                    'base_currency_discount' => $baseDiscount,
                    'base_currency_tax' => $baseTax,
                    'base_currency_total' => $baseTotal
                ]);
                
                $totalAmount += $total;
                $taxAmount += $tax;
            }

            // Update invoice totals
            $baseCurrencyTotal = $totalAmount * $exchangeRate;
            $baseCurrencyTax = $taxAmount * $exchangeRate;
            
            $invoice->update([
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'base_currency_total' => $baseCurrencyTotal,
                'base_currency_tax' => $baseCurrencyTax
            ]);

            // Create customer receivable record
            CustomerReceivable::create([
                'customer_id' => $request->customer_id,
                'invoice_id' => $invoice->invoice_id,
                'amount' => $totalAmount,
                'due_date' => $request->due_date,
                'paid_amount' => 0,
                'balance' => $totalAmount,
                'status' => 'Open',
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_currency_amount' => $baseCurrencyTotal,
                'base_currency_balance' => $baseCurrencyTotal
            ]);

            // Update delivery status if DO is specified
            if ($request->do_id) {
                Delivery::find($request->do_id)->update(['status' => 'Invoiced']);
            }

            DB::commit();
            
            return response()->json([
                'data' => $invoice->load('salesInvoiceLines'), 
                'message' => 'Sales invoice created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create sales invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a sales invoice from Delivery Orders
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function createFromDeliveries(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|unique:SalesInvoice,invoice_number',
            'invoice_date' => 'required|date',
            'customer_id' => 'required|exists:Customer,customer_id',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'status' => 'required|string|max:50',
            'delivery_ids' => 'required|array',
            'delivery_ids.*' => 'exists:Delivery,delivery_id'
        ], [
            'delivery_ids.required' => 'The delivery ids field is required.'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get customer
            $customer = \App\Models\Sales\Customer::findOrFail($request->customer_id);
            
            // Determine currency and exchange rate
            $baseCurrency = config('app.base_currency', 'USD');
            $currencyCode = $request->currency_code ?? $customer->currency_code ?? $baseCurrency;
            
            // Get exchange rate if invoice currency is different from base currency
            $exchangeRate = 1.0; // Default for base currency
            
            if ($currencyCode !== $baseCurrency) {
                $rate = CurrencyRate::where('from_currency', $currencyCode)
                    ->where('to_currency', $baseCurrency)
                    ->where('is_active', true)
                    ->where('effective_date', '<=', $request->invoice_date)
                    ->where(function($query) use ($request) {
                        $query->where('end_date', '>=', $request->invoice_date)
                              ->orWhereNull('end_date');
                    })
                    ->orderBy('effective_date', 'desc')
                    ->first();
                    
                if (!$rate) {
                    // Try to find a reverse rate
                    $reverseRate = CurrencyRate::where('from_currency', $baseCurrency)
                        ->where('to_currency', $currencyCode)
                        ->where('is_active', true)
                        ->where('effective_date', '<=', $request->invoice_date)
                        ->where(function($query) use ($request) {
                            $query->where('end_date', '>=', $request->invoice_date)
                                  ->orWhereNull('end_date');
                        })
                        ->orderBy('effective_date', 'desc')
                        ->first();
                        
                    if ($reverseRate) {
                        $exchangeRate = 1 / $reverseRate->rate;
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'No exchange rate found for the specified currency on the invoice date'
                        ], 422);
                    }
                } else {
                    $exchangeRate = $rate->rate;
                }
            }
            
            // Use the first delivery as the primary reference for the invoice
            $primaryDeliveryId = $request->delivery_ids[0] ?? null;
            
            // Create sales invoice
            $invoice = SalesInvoice::create([
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date,
                'customer_id' => $request->customer_id,
                'do_id' => $primaryDeliveryId,
                'due_date' => $request->due_date,
                'status' => $request->status,
                'reference' => $request->reference ?? null,
                'payment_terms' => $request->payment_terms ?? $customer->payment_terms ?? null,
                'total_amount' => 0, // Will be updated later
                'tax_amount' => 0,   // Will be updated later
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_currency_total' => 0, // Will be updated later
                'base_currency_tax' => 0    // Will be updated later
            ]);

            $totalAmount = 0;
            $taxAmount = 0;
            $processedItems = []; // To avoid duplicate items from multiple deliveries

            // Process each delivery
            foreach ($request->delivery_ids as $deliveryId) {
                $delivery = Delivery::with(['deliveryLines.item', 'deliveryLines.salesOrderLine'])
                    ->findOrFail($deliveryId);
                
                // Check if delivery belongs to the same customer
                if ($delivery->customer_id != $request->customer_id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Delivery #'.$delivery->delivery_number.' belongs to a different customer'
                    ], 422);
                }
                
                // Check if delivery is already invoiced
                if ($delivery->status === 'Invoiced') {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Delivery #'.$delivery->delivery_number.' is already invoiced'
                    ], 422);
                }
                
                // Process delivery lines
                foreach ($delivery->deliveryLines as $deliveryLine) {
                    // Use a combination of item and delivery line as key to avoid duplicates
                    $key = $deliveryLine->item_id . '-' . $deliveryLine->line_id;
                    
                    if (!isset($processedItems[$key])) {
                        $processedItems[$key] = [
                            'item_id' => $deliveryLine->item_id,
                            'item' => $deliveryLine->item,
                            'do_line_id' => $deliveryLine->line_id,
                            'so_line_id' => $deliveryLine->salesOrderLine ? $deliveryLine->salesOrderLine->line_id : null,
                            'so_line' => $deliveryLine->salesOrderLine,
                            'quantity' => 0,
                            'uom_id' => $deliveryLine->uom_id
                        ];
                    }
                    
                    $processedItems[$key]['quantity'] += $deliveryLine->delivered_quantity;
                }
                
                // Update delivery status
                $delivery->update(['status' => 'Invoiced']);
            }
            
            // Create invoice lines from processed items
            foreach ($processedItems as $item) {
                // Get pricing from SO Line if available, otherwise use item default price
                $unitPrice = 0;
                $discount = 0;
                $tax = 0;
                $taxRate = 0; // default tax rate
                
                if ($item['so_line_id'] && $item['so_line']) {
                    // Get pricing from related SO Line
                    $unitPrice = $item['so_line']->unit_price;
                    $discount = ($item['so_line']->discount / $item['so_line']->quantity) * $item['quantity'];
                    $tax = ($item['so_line']->tax / $item['so_line']->quantity) * $item['quantity'];
                    $taxRate = $item['so_line']->tax_rate ?? 0;
                } else {
                    // Use item default pricing
                    $unitPrice = $item['item']->getDefaultSalePriceInCurrency($currencyCode, $request->invoice_date);
                    // Calculate tax based on configured rate if available
                    $taxRate = config('app.base_currency', 0);
                }
                
                $subtotal = $unitPrice * $item['quantity'];
                $total = $subtotal - $discount;
                
                // If no tax amount but tax rate is available, calculate it
                if ($tax == 0 && $taxRate > 0) {
                    $tax = $total * ($taxRate / 100);
                    $total += $tax;
                }
                
                // Calculate base currency amounts
                $baseUnitPrice = $unitPrice * $exchangeRate;
                $baseSubtotal = $subtotal * $exchangeRate;
                $baseDiscount = $discount * $exchangeRate;
                $baseTax = $tax * $exchangeRate;
                $baseTotal = $total * $exchangeRate;
                
                // Create invoice line
                SalesInvoiceLine::create([
                    'invoice_id' => $invoice->invoice_id,
                    'do_line_id' => $item['do_line_id'],
                    'so_line_id' => $item['so_line_id'],
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'uom_id' => $item['uom_id'],
                    'base_currency_unit_price' => $baseUnitPrice,
                    'base_currency_subtotal' => $baseSubtotal,
                    'base_currency_discount' => $baseDiscount,
                    'base_currency_tax' => $baseTax,
                    'base_currency_total' => $baseTotal
                ]);
                
                $totalAmount += $total;
                $taxAmount += $tax;
            }
            
            // Update invoice totals
            $baseCurrencyTotal = $totalAmount * $exchangeRate;
            $baseCurrencyTax = $taxAmount * $exchangeRate;
            
            $invoice->update([
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'base_currency_total' => $baseCurrencyTotal,
                'base_currency_tax' => $baseCurrencyTax
            ]);
            
            // Create customer receivable
            CustomerReceivable::create([
                'customer_id' => $request->customer_id,
                'invoice_id' => $invoice->invoice_id,
                'amount' => $totalAmount,
                'due_date' => $request->due_date,
                'paid_amount' => 0,
                'balance' => $totalAmount,
                'status' => 'Open',
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_currency_amount' => $baseCurrencyTotal,
                'base_currency_balance' => $baseCurrencyTotal
            ]);

            DB::commit();
            
            return response()->json([
                'data' => $invoice->load('salesInvoiceLines'), 
                'message' => 'Sales invoice created successfully from deliveries'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create sales invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified sales invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $invoice = SalesInvoice::with([
            'customer',
            'delivery',
            'salesInvoiceLines.item',
            'salesInvoiceLines.deliveryLine.salesOrderLine',
            'customerReceivables.receivablePayments',
            'salesReturns'
        ])->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Sales invoice not found'], 404);
        }

        return response()->json(['data' => $invoice], 200);
    }

    /**
     * Update the specified sales invoice in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $invoice = SalesInvoice::find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Sales invoice not found'], 404);
        }

        // Check if invoice can be updated (not paid)
        if (in_array($invoice->status, ['Paid', 'Closed'])) {
            return response()->json(['message' => 'Cannot update a ' . $invoice->status . ' invoice'], 400);
        }

        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|unique:SalesInvoice,invoice_number,' . $id . ',invoice_id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'status' => 'required|string|max:50',
            'lines' => 'sometimes|array',
            'lines.*.item_id' => 'required_with:lines|exists:items,item_id',
            'lines.*.quantity' => 'required_with:lines|numeric|min:0',
            'lines.*.unit_price' => 'required_with:lines|numeric|min:0',
            'lines.*.uom_id' => 'required_with:lines|exists:unit_of_measures,uom_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Update invoice basic info
            $invoice->update([
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'status' => $request->status,
                'reference' => $request->reference ?? $invoice->reference,
                'payment_terms' => $request->payment_terms ?? $invoice->payment_terms
            ]);

            // Update invoice lines if provided
            if ($request->has('lines')) {
                // Delete existing lines (this is a replace operation)
                $invoice->salesInvoiceLines()->delete();
                
                $totalAmount = 0;
                $taxAmount = 0;
                
                // Re-create lines
                foreach ($request->lines as $line) {
                    // Calculate amounts
                    $unitPrice = $line['unit_price'];
                    $quantity = $line['quantity'];
                    $subtotal = $unitPrice * $quantity;
                    $discount = $line['discount'] ?? 0;
                    $tax = $line['tax'] ?? 0;
                    $total = $subtotal - $discount + $tax;
                    
                    // Calculate base currency amounts
                    $baseUnitPrice = $unitPrice * $invoice->exchange_rate;
                    $baseSubtotal = $subtotal * $invoice->exchange_rate;
                    $baseDiscount = $discount * $invoice->exchange_rate;
                    $baseTax = $tax * $invoice->exchange_rate;
                    $baseTotal = $total * $invoice->exchange_rate;
                    
                    SalesInvoiceLine::create([
                        'invoice_id' => $invoice->invoice_id,
                        'do_line_id' => $line['do_line_id'] ?? null,
                        'so_line_id' => $line['so_line_id'] ?? null,
                        'item_id' => $line['item_id'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'total' => $total,
                        'uom_id' => $line['uom_id'],
                        'base_currency_unit_price' => $baseUnitPrice,
                        'base_currency_subtotal' => $baseSubtotal,
                        'base_currency_discount' => $baseDiscount,
                        'base_currency_tax' => $baseTax,
                        'base_currency_total' => $baseTotal
                    ]);
                    
                    $totalAmount += $total;
                    $taxAmount += $tax;
                }
                
                // Update invoice totals
                $baseCurrencyTotal = $totalAmount * $invoice->exchange_rate;
                $baseCurrencyTax = $taxAmount * $invoice->exchange_rate;
                
                $invoice->update([
                    'total_amount' => $totalAmount,
                    'tax_amount' => $taxAmount,
                    'base_currency_total' => $baseCurrencyTotal,
                    'base_currency_tax' => $baseCurrencyTax
                ]);
                
                // Update receivable
                $receivable = CustomerReceivable::where('invoice_id', $invoice->invoice_id)->first();
                if ($receivable) {
                    $paidAmount = $receivable->paid_amount ?? 0;
                    $newBalance = $totalAmount - $paidAmount;
                    
                    $receivable->update([
                        'amount' => $totalAmount,
                        'balance' => $newBalance,
                        'base_currency_amount' => $baseCurrencyTotal,
                        'base_currency_balance' => $newBalance * $invoice->exchange_rate
                    ]);
                    
                    // Update status based on payment amount
                    if ($paidAmount > 0) {
                        if ($paidAmount >= $totalAmount) {
                            $receivable->update(['status' => 'Closed']);
                            $invoice->update(['status' => 'Paid']);
                        } else {
                            $receivable->update(['status' => 'Partial']);
                            $invoice->update(['status' => 'Partially Paid']);
                        }
                    }
                }
            }

            // Update receivable if status changes
            if ($request->status === 'Paid' && $invoice->status !== 'Paid') {
                $receivable = CustomerReceivable::where('invoice_id', $invoice->invoice_id)->first();
                if ($receivable) {
                    $receivable->update([
                        'paid_amount' => $receivable->amount,
                        'balance' => 0,
                        'status' => 'Closed',
                        'base_currency_balance' => 0
                    ]);
                }
            }

            DB::commit();

            return response()->json(['data' => $invoice->load('salesInvoiceLines'), 'message' => 'Sales invoice updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update sales invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified sales invoice from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $invoice = SalesInvoice::find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Sales invoice not found'], 404);
        }

        // Check if invoice can be deleted (not paid and no returns)
        if (in_array($invoice->status, ['Paid', 'Closed'])) {
            return response()->json(['message' => 'Cannot delete a ' . $invoice->status . ' invoice'], 400);
        }

        if ($invoice->salesReturns->count() > 0) {
            return response()->json(['message' => 'Cannot delete invoice with related returns'], 400);
        }

        try {
            DB::beginTransaction();

            // Delete related receivables
            CustomerReceivable::where('invoice_id', $invoice->invoice_id)->delete();

            // Delete related invoice lines
            $invoice->salesInvoiceLines()->delete();

            // Update delivery status
            if ($invoice->do_id) {
                Delivery::find($invoice->do_id)->update(['status' => 'Delivered']);
            }
            
            // Also check other deliveries
            $doLineIds = $invoice->salesInvoiceLines()->pluck('do_line_id')->filter()->toArray();
            if (!empty($doLineIds)) {
                $deliveries = Delivery::whereHas('deliveryLines', function($query) use ($doLineIds) {
                    $query->whereIn('line_id', $doLineIds);
                })->get();
                
                foreach ($deliveries as $delivery) {
                    // Only reset status if this was the only invoice for the delivery
                    $otherInvoiceExists = SalesInvoice::where('invoice_id', '!=', $invoice->invoice_id)
                        ->where('do_id', $delivery->delivery_id)
                        ->exists();
                    
                    if (!$otherInvoiceExists) {
                        $delivery->update(['status' => 'Delivered']);
                    }
                }
            }

            // Delete the invoice
            $invoice->delete();

            DB::commit();

            return response()->json(['message' => 'Sales invoice deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete sales invoice', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payment information for a specific invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function paymentInfo($id)
    {
        $invoice = SalesInvoice::find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Sales invoice not found'], 404);
        }

        $receivable = CustomerReceivable::where('invoice_id', $id)
            ->with(['receivablePayments' => function($query) {
                $query->orderBy('payment_date', 'desc');
            }])
            ->first();

        if (!$receivable) {
            return response()->json(['message' => 'Receivable information not found'], 404);
        }

        return response()->json(['data' => $receivable, 'message' => 'Payment information retrieved successfully'], 200);
    }

    /**
     * Record a payment for a specific invoice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function recordPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'payment_currency' => 'nullable|string|size:3'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice = SalesInvoice::find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Sales invoice not found'], 404);
        }

        $receivable = CustomerReceivable::where('invoice_id', $id)->first();

        if (!$receivable) {
            return response()->json(['message' => 'Receivable record not found'], 404);
        }

        if ($receivable->status === 'Closed') {
            return response()->json(['message' => 'Invoice is already fully paid'], 400);
        }

        try {
            DB::beginTransaction();
            
            // Determine payment currency and exchange rate
            $paymentCurrency = $request->payment_currency ?? $invoice->currency_code;
            $exchangeRate = 1.0;
            
            if ($paymentCurrency !== $invoice->currency_code) {
                // Need to convert payment currency to invoice currency
                $rate = CurrencyRate::getCurrentRate($paymentCurrency, $invoice->currency_code, $request->payment_date);
                if (!$rate) {
                    return response()->json(['message' => 'No exchange rate found for the payment currency'], 400);
                }
                $exchangeRate = $rate;
            }
            
            // Convert payment amount to invoice currency
            $invoiceAmount = $request->amount * $exchangeRate;
            
            // Calculate exchange difference (if any)
            $exchangeDifference = 0;
            if ($paymentCurrency !== $invoice->currency_code) {
                $exactAmount = $invoiceAmount * (1 / $exchangeRate);
                $exchangeDifference = $request->amount - $exactAmount;
            }
            
            // Add payment record
            $payment = \App\Models\Accounting\ReceivablePayment::create([
                'receivable_id' => $receivable->receivable_id,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference,
                'payment_currency' => $paymentCurrency,
                'exchange_rate' => $exchangeRate,
                'receivable_amount' => $invoiceAmount, // Amount in invoice currency
                'exchange_difference' => $exchangeDifference
            ]);
            
            // Update receivable balance
            $currentPaid = $receivable->paid_amount ?? 0;
            $newPaid = $currentPaid + $invoiceAmount;
            $newBalance = $receivable->amount - $newPaid;
            
            $receivable->update([
                'paid_amount' => $newPaid,
                'balance' => $newBalance,
                'base_currency_balance' => $newBalance * $invoice->exchange_rate
            ]);
            
            // Update receivable status
            if ($newBalance <= 0) {
                $receivable->update(['status' => 'Closed']);
                $invoice->update(['status' => 'Paid']);
            } else {
                $receivable->update(['status' => 'Partial']);
                $invoice->update(['status' => 'Partially Paid']);
            }

            DB::commit();

            return response()->json([
                'data' => $payment,
                'message' => 'Payment recorded successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to record payment', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get overdue invoices (dashboard data)
     *
     * @return \Illuminate\Http\Response
     */
    public function getOverdueInvoices()
    {
        $today = now()->format('Y-m-d');
        
        $overdueInvoices = SalesInvoice::with(['customer'])
            ->whereIn('status', ['Sent', 'Partially Paid'])
            ->where('due_date', '<', $today)
            ->orderBy('due_date')
            ->get();
            
        return response()->json(['data' => $overdueInvoices], 200);
    }
    
    /**
     * Get payment summary by currency
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getPaymentSummaryByCurrency(Request $request)
    {
        $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');
        
        $summary = DB::table('ReceivablePayment')
            ->join('CustomerReceivable', 'ReceivablePayment.receivable_id', '=', 'CustomerReceivable.receivable_id')
            ->join('SalesInvoice', 'CustomerReceivable.invoice_id', '=', 'SalesInvoice.invoice_id')
            ->whereBetween('ReceivablePayment.payment_date', [$startDate, $endDate])
            ->select(
                'ReceivablePayment.payment_currency',
                DB::raw('SUM(ReceivablePayment.amount) as total_amount'),
                DB::raw('COUNT(ReceivablePayment.payment_id) as payment_count')
            )
            ->groupBy('ReceivablePayment.payment_currency')
            ->get();
            
        return response()->json(['data' => $summary], 200);
    }
    
    /**
     * Get deliveries available for invoicing
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getDeliveriesForInvoicing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get deliveries that are delivered but not yet invoiced
$deliveries = Delivery::with(['customer', 'deliveryLines.item', 'deliveryLines.salesOrderLine'])
            ->where('customer_id', $request->customer_id)
            ->whereIn('status', ['In Transit', 'Completed'])
            ->orderBy('delivery_date', 'desc')
            ->get();
            
        return response()->json(['data' => $deliveries], 200);
    }
    
    /**
     * Get delivery lines by item for invoicing
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getDeliveryLinesByItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'delivery_ids' => 'required|array',
            'delivery_ids.*' => 'exists:Delivery,delivery_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get all delivery lines from specified deliveries
$deliveryLines = DeliveryLine::with(['item', 'salesOrderLine', 'delivery'])
            ->whereHas('delivery', function($query) use ($request) {
                $query->where('customer_id', $request->customer_id)
                      ->whereIn('delivery_id', $request->delivery_ids)
                      ->whereIn('status', ['In Transit', 'Completed']);
            })
            ->get();
            
        // Group delivery lines by item
        $groupedLines = [];
        foreach ($deliveryLines as $line) {
            $itemId = $line->item_id;
            
            if (!isset($groupedLines[$itemId])) {
                $groupedLines[$itemId] = [
                    'item_id' => $itemId,
                    'item_code' => $line->item->item_code,
                    'item_name' => $line->item->name,
                    'uom_id' => $line->uom_id,
                    'uom_symbol' => $line->item->unitOfMeasure ? $line->item->unitOfMeasure->symbol : '',
                    'total_quantity' => 0,
                    'unit_price' => $line->salesOrderLine ? $line->salesOrderLine->unit_price : $line->item->sale_price,
                    'delivery_lines' => []
                ];
            }
            
            $groupedLines[$itemId]['total_quantity'] += $line->delivered_quantity;
            $groupedLines[$itemId]['delivery_lines'][] = [
                'do_line_id' => $line->line_id,
                'do_id' => $line->delivery_id,
                'do_number' => $line->delivery->delivery_number,
                'quantity' => $line->delivered_quantity,
                'so_line_id' => $line->so_line_id,
                'so_number' => $line->salesOrderLine ? $line->salesOrderLine->salesOrder->so_number : null
            ];
        }
        
        return response()->json(['data' => array_values($groupedLines)], 200);
    }
}
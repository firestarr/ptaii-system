<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\VendorQuotation;
use App\Http\Requests\PurchaseOrderRequest;
use App\Services\PONumberGenerator;
use App\Models\CurrencyRate;
use App\Models\Vendor;
use App\Models\PurchaseRequisition;
use App\Models\PRLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    protected $poNumberGenerator;
    
    public function __construct(PONumberGenerator $poNumberGenerator)
    {
        $this->poNumberGenerator = $poNumberGenerator;
    }
    
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['vendor']);
        
        // Apply filters
if ($request->filled('status')) {
    if (is_array($request->status)) {
        $query->whereIn('status', $request->status);
    } else {
        $query->where('status', $request->status);
    }
}
        
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('po_date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('po_date', '<=', $request->date_to);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('po_number', 'like', "%{$search}%");
        }
        
        // Filter untuk Outstanding PO
        if ($request->has('has_outstanding') && $request->has_outstanding) {
            $query->whereHas('lines', function($q) {
$q->whereRaw('quantity > (
                    SELECT COALESCE(SUM(grl.received_quantity), 0)
                    FROM goods_receipt_lines grl
                    JOIN goods_receipts gr ON grl.receipt_id = gr.receipt_id
                    WHERE grl.po_line_id = po_lines.line_id
                    AND gr.status = \'confirmed\'
                )');
            });
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'po_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $purchaseOrders = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $purchaseOrders
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PurchaseOrderRequest $request)
    {
        try {
            DB::beginTransaction();
            
            // Generate PO number
            $poNumber = $this->poNumberGenerator->generate();
            
            // Get the vendor to check for default currency
            $vendor = Vendor::find($request->vendor_id);
            
            // Determine currency to use (from request, vendor preference, or system default)
            $currencyCode = $request->currency_code ?? $vendor->preferred_currency ?? config('app.base_currency', 'USD');
            $baseCurrency = config('app.base_currency', 'USD');
            
            // Get exchange rate
            $exchangeRate = 1.0; // Default for base currency
            
            if ($currencyCode !== $baseCurrency) {
                $rate = CurrencyRate::where('from_currency', $currencyCode)
                    ->where('to_currency', $baseCurrency)
                    ->where('is_active', true)
                    ->where('effective_date', '<=', $request->po_date)
                    ->where(function($query) use ($request) {
                        $query->where('end_date', '>=', $request->po_date)
                              ->orWhereNull('end_date');
                    })
                    ->orderBy('effective_date', 'desc')
                    ->first();
                    
                if (!$rate) {
                    // Try to find a reverse rate
                    $reverseRate = CurrencyRate::where('from_currency', $baseCurrency)
                        ->where('to_currency', $currencyCode)
                        ->where('is_active', true)
                        ->where('effective_date', '<=', $request->po_date)
                        ->where(function($query) use ($request) {
                            $query->where('end_date', '>=', $request->po_date)
                                  ->orWhereNull('end_date');
                        })
                        ->orderBy('effective_date', 'desc')
                        ->first();
                        
                    if ($reverseRate) {
                        $exchangeRate = 1 / $reverseRate->rate;
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'status' => 'error',
                            'message' => 'No exchange rate found for the specified currency on the purchase date'
                        ], 422);
                    }
                } else {
                    $exchangeRate = $rate->rate;
                }
            }
            
            // Calculate totals in document currency
            $subtotal = 0;
            $taxTotal = 0;
            
            foreach ($request->lines as $line) {
                $lineSubtotal = $line['unit_price'] * $line['quantity'];
                $lineTax = isset($line['tax']) ? $line['tax'] : 0;
                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
            }
            
            $totalAmount = $subtotal + $taxTotal;
            
            // Calculate totals in base currency
            $baseCurrencyTotal = $totalAmount * $exchangeRate;
            $baseCurrencyTax = $taxTotal * $exchangeRate;
            
            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $poNumber,
                'po_date' => $request->po_date,
                'vendor_id' => $request->vendor_id,
                'payment_terms' => $request->payment_terms,
                'delivery_terms' => $request->delivery_terms,
                'expected_delivery' => $request->expected_delivery,
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'tax_amount' => $taxTotal,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency_total' => $baseCurrencyTotal,
                'base_currency_tax' => $baseCurrencyTax,
                'base_currency' => $baseCurrency
            ]);
            
            // Create PO lines
            foreach ($request->lines as $line) {
                $lineSubtotal = $line['unit_price'] * $line['quantity'];
                $lineTax = isset($line['tax']) ? $line['tax'] : 0;
                $lineTotal = $lineSubtotal + $lineTax;
                
                // Calculate base currency amounts
                $baseUnitPrice = $line['unit_price'] * $exchangeRate;
                $baseSubtotal = $lineSubtotal * $exchangeRate;
                $baseTax = $lineTax * $exchangeRate;
                $baseTotal = $lineTotal * $exchangeRate;
                
                $purchaseOrder->lines()->create([
                    'item_id' => $line['item_id'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'uom_id' => $line['uom_id'],
                    'subtotal' => $lineSubtotal,
                    'tax' => $lineTax,
                    'total' => $lineTotal,
                    // New multicurrency fields
                    'base_currency_unit_price' => $baseUnitPrice,
                    'base_currency_subtotal' => $baseSubtotal,
                    'base_currency_tax' => $baseTax,
                    'base_currency_total' => $baseTotal
                ]);
            }
            
            // If quotation_id is provided, mark quotation as accepted
            if (isset($request->quotation_id)) {
                $quotation = VendorQuotation::findOrFail($request->quotation_id);
                $quotation->update(['status' => 'accepted']);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order created successfully',
                'data' => $purchaseOrder->load(['vendor', 'lines.item', 'lines.unitOfMeasure'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Purchase Order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['vendor', 'lines.item', 'lines.unitOfMeasure', 'goodsReceipts']);
        
        return response()->json([
            'status' => 'success',
            'data' => $purchaseOrder
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        // Check if PO can be updated (only draft status)
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft Purchase Orders can be updated'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Determine currency to use
            $currencyCode = $request->currency_code ?? $purchaseOrder->currency_code;
            $baseCurrency = config('app.base_currency', 'USD');
            
            // Get exchange rate if the currency has changed
            $exchangeRate = $purchaseOrder->exchange_rate;
            
            if ($currencyCode !== $purchaseOrder->currency_code) {
                if ($currencyCode !== $baseCurrency) {
                    $rate = CurrencyRate::where('from_currency', $currencyCode)
                        ->where('to_currency', $baseCurrency)
                        ->where('is_active', true)
                        ->where('effective_date', '<=', $request->po_date)
                        ->where(function($query) use ($request) {
                            $query->where('end_date', '>=', $request->po_date)
                                ->orWhereNull('end_date');
                        })
                        ->orderBy('effective_date', 'desc')
                        ->first();
                        
                    if (!$rate) {
                        // Try to find a reverse rate
                        $reverseRate = CurrencyRate::where('from_currency', $baseCurrency)
                            ->where('to_currency', $currencyCode)
                            ->where('is_active', true)
                            ->where('effective_date', '<=', $request->po_date)
                            ->where(function($query) use ($request) {
                                $query->where('end_date', '>=', $request->po_date)
                                    ->orWhereNull('end_date');
                            })
                            ->orderBy('effective_date', 'desc')
                            ->first();
                            
                        if ($reverseRate) {
                            $exchangeRate = 1 / $reverseRate->rate;
                        } else {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'error',
                                'message' => 'No exchange rate found for the specified currency on the purchase date'
                            ], 422);
                        }
                    } else {
                        $exchangeRate = $rate->rate;
                    }
                } else {
                    $exchangeRate = 1.0; // Base currency
                }
            }
            
            // Calculate totals
            $subtotal = 0;
            $taxTotal = 0;
            
            foreach ($request->lines as $line) {
                $lineSubtotal = $line['unit_price'] * $line['quantity'];
                $lineTax = isset($line['tax']) ? $line['tax'] : 0;
                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
            }
            
            $totalAmount = $subtotal + $taxTotal;
            
            // Calculate totals in base currency
            $baseCurrencyTotal = $totalAmount * $exchangeRate;
            $baseCurrencyTax = $taxTotal * $exchangeRate;
            
            // Update purchase order
            $purchaseOrder->update([
                'po_date' => $request->po_date,
                'vendor_id' => $request->vendor_id,
                'payment_terms' => $request->payment_terms,
                'delivery_terms' => $request->delivery_terms,
                'expected_delivery' => $request->expected_delivery,
                'total_amount' => $totalAmount,
                'tax_amount' => $taxTotal,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency_total' => $baseCurrencyTotal,
                'base_currency_tax' => $baseCurrencyTax
            ]);
            
            // Update PO lines
            if ($request->has('lines')) {
                // Delete existing lines
                $purchaseOrder->lines()->delete();
                
                // Create new lines
                foreach ($request->lines as $line) {
                    $lineSubtotal = $line['unit_price'] * $line['quantity'];
                    $lineTax = isset($line['tax']) ? $line['tax'] : 0;
                    $lineTotal = $lineSubtotal + $lineTax;
                    
                    // Calculate base currency amounts
                    $baseUnitPrice = $line['unit_price'] * $exchangeRate;
                    $baseSubtotal = $lineSubtotal * $exchangeRate;
                    $baseTax = $lineTax * $exchangeRate;
                    $baseTotal = $lineTotal * $exchangeRate;
                    
                    $purchaseOrder->lines()->create([
                        'item_id' => $line['item_id'],
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'uom_id' => $line['uom_id'],
                        'subtotal' => $lineSubtotal,
                        'tax' => $lineTax,
                        'total' => $lineTotal,
                        // New multicurrency fields
                        'base_currency_unit_price' => $baseUnitPrice,
                        'base_currency_subtotal' => $baseSubtotal,
                        'base_currency_tax' => $baseTax,
                        'base_currency_total' => $baseTotal
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order updated successfully',
                'data' => $purchaseOrder->load(['vendor', 'lines.item', 'lines.unitOfMeasure'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update Purchase Order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        // Check if PO can be deleted (only draft status)
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft Purchase Orders can be deleted'
            ], 400);
        }
        
        $purchaseOrder->lines()->delete();
        $purchaseOrder->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Purchase Order deleted successfully'
        ]);
    }
    
    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'status' => 'required|in:draft,submitted,approved,sent,partial,received,completed,canceled'
        ]);
        
        // Additional validations based on status transition
        $currentStatus = $purchaseOrder->status;
        $newStatus = $request->status;
        
        $validTransitions = [
            'draft' => ['submitted', 'canceled'],
            'submitted' => ['approved', 'canceled'],
            'approved' => ['sent', 'canceled'],
            'sent' => ['partial', 'received', 'canceled'],
            'partial' => ['completed', 'canceled'],
            'received' => ['completed', 'canceled'],
            'completed' => ['canceled'],
            'canceled' => []
        ];
        
        if (!in_array($newStatus, $validTransitions[$currentStatus])) {
            return response()->json([
                'status' => 'error',
                'message' => "Status cannot be changed from {$currentStatus} to {$newStatus}"
            ], 400);
        }
        
        $purchaseOrder->update(['status' => $newStatus]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Purchase Order status updated successfully',
            'data' => $purchaseOrder
        ]);
    }
    
    public function createFromQuotation(Request $request)
    {
        $request->validate([
            'quotation_id' => 'required|exists:vendor_quotations,quotation_id'
        ]);
        
        $quotation = VendorQuotation::with(['lines', 'vendor', 'requestForQuotation'])
                                  ->findOrFail($request->quotation_id);
        
        // Check if quotation is in accepted status
        if ($quotation->status !== 'accepted') {
            return response()->json([
                'status' => 'error',
                'message' => 'Purchase Order can only be created from accepted quotations'
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Generate PO number
            $poNumber = $this->poNumberGenerator->generate();
            
            // Calculate totals
            $subtotal = 0;
            $taxTotal = 0;
            
            foreach ($quotation->lines as $line) {
                $lineSubtotal = $line->unit_price * $line->quantity;
                $subtotal += $lineSubtotal;
            }
            
            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $poNumber,
                'po_date' => now(),
                'vendor_id' => $quotation->vendor_id,
                'payment_terms' => null,
                'delivery_terms' => null,
                'expected_delivery' => null,
                'status' => 'draft',
                'total_amount' => $subtotal,
                'tax_amount' => 0
            ]);
            
            // Create PO lines from quotation lines
            foreach ($quotation->lines as $line) {
                $lineSubtotal = $line->unit_price * $line->quantity;
                
                $purchaseOrder->lines()->create([
                    'item_id' => $line->item_id,
                    'unit_price' => $line->unit_price,
                    'quantity' => $line->quantity,
                    'uom_id' => $line->uom_id,
                    'subtotal' => $lineSubtotal,
                    'tax' => 0,
                    'total' => $lineSubtotal
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order created from quotation successfully',
                'data' => $purchaseOrder->load(['vendor', 'lines.item', 'lines.unitOfMeasure'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create Purchase Order from quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Convert purchase order currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PurchaseOrder  $purchaseOrder
     * @return \Illuminate\Http\Response
     */
    public function convertCurrency(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Only allow currency conversion for draft orders
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft Purchase Orders can have their currency converted'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'currency_code' => 'required|string|size:3',
            'use_exchange_rate_date' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Don't convert if already in the target currency
        if ($purchaseOrder->currency_code === $request->currency_code) {
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order is already in the specified currency',
                'data' => $purchaseOrder
            ]);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        
        try {
            DB::beginTransaction();
            
            // Determine which exchange rate to use
            $useExchangeRateDate = $request->use_exchange_rate_date ?? true;
            $exchangeRateDate = $useExchangeRateDate ? $purchaseOrder->po_date : now()->format('Y-m-d');
            
            // Get exchange rate from base currency to target currency
            if ($request->currency_code !== $baseCurrency) {
                $rate = CurrencyRate::where('from_currency', $baseCurrency)
                    ->where('to_currency', $request->currency_code)
                    ->where('is_active', true)
                    ->where('effective_date', '<=', $exchangeRateDate)
                    ->where(function($query) use ($exchangeRateDate) {
                        $query->where('end_date', '>=', $exchangeRateDate)
                            ->orWhereNull('end_date');
                    })
                    ->orderBy('effective_date', 'desc')
                    ->first();
                    
                if (!$rate) {
                    // Try to find a reverse rate
                    $reverseRate = CurrencyRate::where('from_currency', $request->currency_code)
                        ->where('to_currency', $baseCurrency)
                        ->where('is_active', true)
                        ->where('effective_date', '<=', $exchangeRateDate)
                        ->where(function($query) use ($exchangeRateDate) {
                            $query->where('end_date', '>=', $exchangeRateDate)
                                ->orWhereNull('end_date');
                        })
                        ->orderBy('effective_date', 'desc')
                        ->first();
                        
                    if ($reverseRate) {
                        $newExchangeRate = 1 / $reverseRate->rate;
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'status' => 'error',
                            'message' => 'No exchange rate found for the specified currency'
                        ], 422);
                    }
                } else {
                    $newExchangeRate = $rate->rate;
                }
            } else {
                // Converting to base currency
                $newExchangeRate = 1.0;
            }
            
            // Calculate conversion factor between old and new currencies
            $conversionFactor = $newExchangeRate / $purchaseOrder->exchange_rate;
            
            // Update order totals
            $newTotalAmount = $purchaseOrder->base_currency_total / $newExchangeRate;
            $newTaxAmount = $purchaseOrder->base_currency_tax / $newExchangeRate;
            
            // Update purchase order
            $purchaseOrder->update([
                'currency_code' => $request->currency_code,
                'exchange_rate' => $newExchangeRate,
                'total_amount' => $newTotalAmount,
                'tax_amount' => $newTaxAmount
            ]);
            
            // Update all line items
            foreach ($purchaseOrder->lines as $line) {
                $newUnitPrice = $line->base_currency_unit_price / $newExchangeRate;
                $newSubtotal = $line->base_currency_subtotal / $newExchangeRate;
                $newTax = $line->base_currency_tax / $newExchangeRate;
                $newTotal = $line->base_currency_total / $newExchangeRate;
                
                $line->update([
                    'unit_price' => $newUnitPrice,
                    'subtotal' => $newSubtotal,
                    'tax' => $newTax,
                    'total' => $newTotal
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order currency converted successfully',
                'data' => $purchaseOrder->fresh()->load(['vendor', 'lines.item', 'lines.unitOfMeasure'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to convert Purchase Order currency',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display outstanding quantities for a specific purchase order.
     *
     * @param  \App\Models\PurchaseOrder  $purchaseOrder
     * @return \Illuminate\Http\Response
     */
    public function showOutstanding(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['lines.item', 'goodsReceipts.lines']);
        
        $outstandingLines = [];
        
        foreach ($purchaseOrder->lines as $poLine) {
            // Hitung total yang sudah diterima untuk line ini
            $receivedQuantity = 0;
            
            foreach ($purchaseOrder->goodsReceipts as $receipt) {
                foreach ($receipt->lines as $receiptLine) {
                    if ($receiptLine->po_line_id === $poLine->line_id) {
                        $receivedQuantity += $receiptLine->received_quantity;
                    }
                }
            }
            
            // Hitung outstanding
            $outstandingQuantity = $poLine->quantity - $receivedQuantity;
            
            // Jika masih ada outstanding, tambahkan ke hasil
            if ($outstandingQuantity > 0) {
                $outstandingLines[] = [
                    'line_id' => $poLine->line_id,
                    'item_code' => $poLine->item->item_code,
                    'item_name' => $poLine->item->name,
                    'ordered_quantity' => $poLine->quantity,
                    'received_quantity' => $receivedQuantity,
                    'outstanding_quantity' => $outstandingQuantity,
                    'unit_price' => $poLine->unit_price,
                    'outstanding_value' => $outstandingQuantity * $poLine->unit_price
                ];
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'po_number' => $purchaseOrder->po_number,
                'po_date' => $purchaseOrder->po_date,
                'vendor' => $purchaseOrder->vendor->name,
                'outstanding_lines' => $outstandingLines,
                'total_outstanding_value' => array_sum(array_column($outstandingLines, 'outstanding_value'))
            ]
        ]);
    }

    /**
     * Get all purchase orders with outstanding quantities.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getAllOutstanding(Request $request)
    {
$query = PurchaseOrder::with(['vendor', 'lines.item'])
            ->whereIn('status', ['sent', 'partial']) // Hanya PO yang relevan
            ->whereHas('lines', function($q) {
                $q->whereRaw('quantity > (
                    SELECT COALESCE(SUM(grl.received_quantity), 0)
                    FROM goods_receipt_lines grl
                    JOIN goods_receipts gr ON grl.receipt_id = gr.receipt_id
                    WHERE grl.po_line_id = po_lines.line_id
                    AND gr.status = \'confirmed\'
                )');
            });
        
        // Filter tambahan
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        if ($request->filled('expected_from')) {
            $query->where('expected_delivery', '>=', $request->expected_from);
        }
        
        if ($request->filled('expected_to')) {
            $query->where('expected_delivery', '<=', $request->expected_to);
        }
        
        // Sorting
        $sortField = $request->input('sort_field', 'expected_delivery');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $purchaseOrders = $query->paginate($perPage);
        
        // Hitung outstanding untuk setiap PO
        $result = $purchaseOrders->map(function($po) {
            $outstandingValue = 0;
            $outstandingItems = 0;
            
            foreach ($po->lines as $line) {
                // Hitung received quantity
$receivedQuantity = DB::table('goods_receipt_lines')
                    ->join('goods_receipts', 'goods_receipt_lines.receipt_id', '=', 'goods_receipts.receipt_id')
                    ->where('goods_receipt_lines.po_line_id', $line->line_id)
                    ->where('goods_receipts.status', 'confirmed')
                    ->sum('goods_receipt_lines.received_quantity');
                
                $outstanding = $line->quantity - $receivedQuantity;
                
                if ($outstanding > 0) {
                    $outstandingValue += $outstanding * $line->unit_price;
                    $outstandingItems++;
                }
            }
            
            return [
                'po_id' => $po->po_id,
                'po_number' => $po->po_number,
                'po_date' => $po->po_date,
                'vendor_name' => $po->vendor->name,
                'expected_delivery' => $po->expected_delivery,
                'status' => $po->status,
                'total_value' => $po->total_amount,
                'outstanding_value' => $outstandingValue,
                'outstanding_percentage' => $po->total_amount > 0 ? 
                    round(($outstandingValue / $po->total_amount) * 100, 2) : 0,
                'outstanding_items' => $outstandingItems
            ];
        });
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'outstanding_pos' => $result,
                'pagination' => [
                    'total' => $purchaseOrders->total(),
                    'per_page' => $purchaseOrders->perPage(),
                    'current_page' => $purchaseOrders->currentPage(),
                    'last_page' => $purchaseOrders->lastPage()
                ]
            ]
        ]);
    }
    
    /**
     * Get detailed outstanding report with item details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function outstandingItemsReport(Request $request)
    {
        // Filter parameters
        $vendorIds = $request->input('vendor_ids', []);
        $itemIds = $request->input('item_ids', []);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $expectedFrom = $request->input('expected_from');
        $expectedTo = $request->input('expected_to');
        
        // Get all POs with outstanding items
        $query = PurchaseOrder::with(['vendor', 'lines.item'])
            ->whereIn('status', ['sent', 'partial']) // Hanya PO yang relevan
            ->whereHas('lines', function($q) {
                $q->whereRaw('quantity > (
                    SELECT COALESCE(SUM(grl.received_quantity), 0)
                    FROM goods_receipt_lines grl
                    JOIN goods_receipts gr ON grl.receipt_id = gr.receipt_id
                    WHERE grl.po_line_id = po_lines.line_id
                    AND gr.status = "confirmed"
                )');
            });
        
        // Apply filters
        if (!empty($vendorIds)) {
            $query->whereIn('vendor_id', $vendorIds);
        }
        
        if ($dateFrom) {
            $query->whereDate('po_date', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->whereDate('po_date', '<=', $dateTo);
        }
        
        if ($expectedFrom) {
            $query->where('expected_delivery', '>=', $expectedFrom);
        }
        
        if ($expectedTo) {
            $query->where('expected_delivery', '<=', $expectedTo);
        }
        
        // Filter by specific items
        if (!empty($itemIds)) {
            $query->whereHas('lines', function($q) use ($itemIds) {
                $q->whereIn('item_id', $itemIds);
            });
        }
        
        $purchaseOrders = $query->get();
        
        // Prepare report data
        $outstandingItems = [];
        
        foreach ($purchaseOrders as $po) {
            foreach ($po->lines as $line) {
                // Calculate received quantity
                $receivedQuantity = DB::table('goods_receipt_lines')
                    ->join('goods_receipts', 'goods_receipt_lines.receipt_id', '=', 'goods_receipts.receipt_id')
                    ->where('goods_receipt_lines.po_line_id', $line->line_id)
                    ->where('goods_receipts.status', 'confirmed')
                    ->sum('goods_receipt_lines.received_quantity');
                
                $outstandingQuantity = $line->quantity - $receivedQuantity;
                
                // Only include if outstanding
                if ($outstandingQuantity > 0) {
                    $item = $line->item;
                    
                    // Skip if filtering by item and not in the list
                    if (!empty($itemIds) && !in_array($item->item_id, $itemIds)) {
                        continue;
                    }
                    
                    // Create item key for grouping
                    $itemKey = $item->item_id;
                    
                    if (!isset($outstandingItems[$itemKey])) {
                        $outstandingItems[$itemKey] = [
                            'item_id' => $item->item_id,
                            'item_code' => $item->item_code,
                            'item_name' => $item->name,
                            'total_outstanding' => 0,
                            'total_value' => 0,
                            'orders' => []
                        ];
                    }
                    
                    // Add to total outstanding for this item
                    $outstandingItems[$itemKey]['total_outstanding'] += $outstandingQuantity;
                    $outstandingItems[$itemKey]['total_value'] += $outstandingQuantity * $line->unit_price;
                    
                    // Add order details
                    $outstandingItems[$itemKey]['orders'][] = [
                        'po_id' => $po->po_id,
                        'po_number' => $po->po_number,
                        'po_date' => $po->po_date,
                        'expected_delivery' => $po->expected_delivery,
                        'vendor_name' => $po->vendor->name,
                        'ordered_quantity' => $line->quantity,
                        'received_quantity' => $receivedQuantity,
                        'outstanding_quantity' => $outstandingQuantity,
                        'unit_price' => $line->unit_price,
                        'outstanding_value' => $outstandingQuantity * $line->unit_price,
                        'days_outstanding' => now()->diffInDays($po->po_date),
                        'overdue' => $po->expected_delivery && now()->gt($po->expected_delivery)
                    ];
                }
            }
        }
        
        // Convert to indexed array and sort by total outstanding quantity
        $result = array_values($outstandingItems);
        usort($result, function($a, $b) {
            return $b['total_outstanding'] <=> $a['total_outstanding'];
        });
        
        // Calculate overall totals
        $totalOutstanding = array_sum(array_column($result, 'total_outstanding'));
        $totalValue = array_sum(array_column($result, 'total_value'));
        $totalOrders = count(array_unique(array_merge(...array_map(function($item) {
            return array_column($item['orders'], 'po_id');
        }, $result))));
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_outstanding_items' => count($result),
                    'total_outstanding_quantity' => $totalOutstanding,
                    'total_outstanding_value' => $totalValue,
                    'total_affected_orders' => $totalOrders
                ],
                'items' => $result
            ]
        ]);
    }
    /**
     * Create PO directly from Purchase Requisition
     */
    public function createFromPR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pr_id' => 'required|exists:purchase_requisitions,pr_id',
            'vendor_id' => 'required|exists:vendors,vendor_id',
            'pricing_source' => 'required|in:contract,quotation,item_pricing,manual',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0.000001',
            'payment_terms' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'expected_delivery' => 'nullable|date|after_or_equal:today',
            'manual_prices' => 'required_if:pricing_source,manual|array',
            'manual_prices.*.pr_line_id' => 'required_if:pricing_source,manual|exists:pr_lines,line_id',
            'manual_prices.*.unit_price' => 'required_if:pricing_source,manual|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            $pr = PurchaseRequisition::with(['lines.item', 'lines.unitOfMeasure'])
                                    ->findOrFail($request->pr_id);
            
            // Check PR status
            if ($pr->status !== 'approved') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only approved PR can be converted to PO'
                ], 400);
            }
            
            $vendor = Vendor::findOrFail($request->vendor_id);
            
            // Validate pricing availability
            $pricingValidation = $this->validatePricingForPR($pr, $vendor, $request->pricing_source, $request->manual_prices ?? []);
            
            if (!$pricingValidation['valid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $pricingValidation['message'],
                    'missing_pricing' => $pricingValidation['missing_items']
                ], 400);
            }
            
            // Generate PO
            $po = $this->createPOFromPR($pr, $vendor, $request);
            
            // Update PR status
            $pr->update(['status' => 'po_created']);
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order created successfully from PR',
                'data' => $po->load(['vendor', 'lines.item', 'lines.unitOfMeasure'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create PO from PR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create split POs from Purchase Requisition with multiple vendors
     */
    public function createSplitPOFromPR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pr_id' => 'required|exists:purchase_requisitions,pr_id',
            'vendor_selections' => 'required|array|min:1',
            'vendor_selections.*.pr_line_id' => 'required|exists:pr_lines,line_id',
            'vendor_selections.*.vendor_id' => 'required|exists:vendors,vendor_id',
            'vendor_selections.*.quantity' => 'required|numeric|min:0.01',
            'vendor_selections.*.unit_price' => 'required|numeric|min:0',
            'vendor_selections.*.currency_code' => 'nullable|string|size:3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            $pr = PurchaseRequisition::with('lines')->findOrFail($request->pr_id);
            
            // Check PR status
            if ($pr->status !== 'approved') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only approved PR can be converted to PO'
                ], 400);
            }
            
            // Validate total quantities match PR
            $this->validateQuantitySplit($pr, $request->vendor_selections);
            
            // Group selections by vendor
            $vendorGroups = collect($request->vendor_selections)->groupBy('vendor_id');
            
            $createdPOs = [];
            
            foreach ($vendorGroups as $vendorId => $selections) {
                $vendor = Vendor::findOrFail($vendorId);
                
                // Create PO for this vendor
                $po = $this->createPOForVendorFromSelections($pr, $vendor, $selections->toArray());
                $createdPOs[] = $po;
            }
            
            // Update PR status
            $pr->update(['status' => 'po_created']);
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => count($createdPOs) . ' Purchase Orders created successfully',
                'data' => $createdPOs
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create split POs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate pricing availability for PR
     */
    private function validatePricingForPR($pr, $vendor, $pricingSource, $manualPrices = [])
    {
        $missingPricing = [];
        $manualPricesMap = collect($manualPrices)->keyBy('pr_line_id');
        
        foreach ($pr->lines as $line) {
            $item = $line->item;
            $hasPricing = false;
            
            switch ($pricingSource) {
                case 'contract':
                    // Check active vendor contract
                    $contract = VendorContract::where('vendor_id', $vendor->vendor_id)
                        ->where('status', 'active')
                        ->where('start_date', '<=', now())
                        ->where(function($q) {
                            $q->where('end_date', '>=', now())->orWhereNull('end_date');
                        })->first();
                    $hasPricing = (bool) $contract;
                    break;
                    
                case 'quotation':
                    // Check valid quotation
                    $quotation = VendorQuotation::where('vendor_id', $vendor->vendor_id)
                        ->where('status', 'received')
                        ->where('validity_date', '>=', now())
                        ->whereHas('lines', function($q) use ($item) {
                            $q->where('item_id', $item->item_id);
                        })->first();
                    $hasPricing = (bool) $quotation;
                    break;
                    
                case 'item_pricing':
                    // Check item pricing
                    $pricing = $item->prices()
                        ->where('vendor_id', $vendor->vendor_id)
                        ->where('price_type', 'purchase')
                        ->active()
                        ->where('min_quantity', '<=', $line->quantity)
                        ->first();
                    $hasPricing = (bool) $pricing;
                    break;
                    
                case 'manual':
                    $hasPricing = $manualPricesMap->has($line->line_id);
                    break;
            }
            
            if (!$hasPricing) {
                $missingPricing[] = [
                    'pr_line_id' => $line->line_id,
                    'item_id' => $item->item_id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->name
                ];
            }
        }
        
        return [
            'valid' => empty($missingPricing),
            'message' => empty($missingPricing) ? 'All items have valid pricing' : 'Some items missing pricing',
            'missing_items' => $missingPricing
        ];
    }

    /**
     * Create PO from PR
     */
    private function createPOFromPR($pr, $vendor, $request)
    {
        // Generate PO number
        $poNumber = $this->poNumberGenerator->generate();
        
        // Determine currency
        $currency = $request->currency_code ?? $vendor->preferred_currency ?? config('app.base_currency', 'USD');
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Get exchange rate
        $exchangeRate = $request->exchange_rate ?? $this->getExchangeRateForCurrency($currency, $baseCurrency);
        
        // Get pricing for each line
        $manualPricesMap = collect($request->manual_prices ?? [])->keyBy('pr_line_id');
        
        $subtotal = 0;
        $taxTotal = 0;
        
        $poLines = [];
        
        foreach ($pr->lines as $prLine) {
            $unitPrice = $this->getUnitPriceForLine($prLine, $vendor, $request->pricing_source, $manualPricesMap);
            
            $lineSubtotal = $unitPrice * $prLine->quantity;
            $lineTax = 0; // Calculate if needed
            $lineTotal = $lineSubtotal + $lineTax;
            
            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            
            $poLines[] = [
                'item_id' => $prLine->item_id,
                'unit_price' => $unitPrice,
                'quantity' => $prLine->quantity,
                'uom_id' => $prLine->uom_id,
                'subtotal' => $lineSubtotal,
                'tax' => $lineTax,
                'total' => $lineTotal,
                'base_currency_unit_price' => $unitPrice * $exchangeRate,
                'base_currency_subtotal' => $lineSubtotal * $exchangeRate,
                'base_currency_tax' => $lineTax * $exchangeRate,
                'base_currency_total' => $lineTotal * $exchangeRate
            ];
        }
        
        $totalAmount = $subtotal + $taxTotal;
        
        // Create PO
        $po = PurchaseOrder::create([
            'po_number' => $poNumber,
            'po_date' => now(),
            'vendor_id' => $vendor->vendor_id,
            'payment_terms' => $request->payment_terms ?? ($vendor->payment_term . ' days'),
            'delivery_terms' => $request->delivery_terms,
            'expected_delivery' => $request->expected_delivery,
            'status' => 'draft',
            'total_amount' => $totalAmount,
            'tax_amount' => $taxTotal,
            'currency_code' => $currency,
            'exchange_rate' => $exchangeRate,
            'base_currency_total' => $totalAmount * $exchangeRate,
            'base_currency_tax' => $taxTotal * $exchangeRate,
            'base_currency' => $baseCurrency,
            'reference_document' => "PR-{$pr->pr_number}"
        ]);
        
        // Create PO lines
        foreach ($poLines as $lineData) {
            $po->lines()->create($lineData);
        }
        
        return $po;
    }

    /**
     * Create PO for vendor from selections
     */
    private function createPOForVendorFromSelections($pr, $vendor, $selections)
    {
        $poNumber = $this->poNumberGenerator->generate();
        $currency = $vendor->preferred_currency ?? config('app.base_currency', 'USD');
        $baseCurrency = config('app.base_currency', 'USD');
        
        // Get exchange rate
        $exchangeRate = $this->getExchangeRateForCurrency($currency, $baseCurrency);
        
        // Calculate totals
        $subtotal = 0;
        $taxTotal = 0;
        
        foreach ($selections as $selection) {
            $lineSubtotal = $selection['unit_price'] * $selection['quantity'];
            $subtotal += $lineSubtotal;
            // Add tax calculation if needed
        }
        
        $totalAmount = $subtotal + $taxTotal;
        
        // Create PO
        $po = PurchaseOrder::create([
            'po_number' => $poNumber,
            'po_date' => now(),
            'vendor_id' => $vendor->vendor_id,
            'payment_terms' => $vendor->payment_term . ' days',
            'delivery_terms' => null,
            'expected_delivery' => null,
            'status' => 'draft',
            'total_amount' => $totalAmount,
            'tax_amount' => $taxTotal,
            'currency_code' => $currency,
            'exchange_rate' => $exchangeRate,
            'base_currency_total' => $totalAmount * $exchangeRate,
            'base_currency_tax' => $taxTotal * $exchangeRate,
            'base_currency' => $baseCurrency,
            'reference_document' => "PR-{$pr->pr_number}"
        ]);
        
        // Create PO lines
        foreach ($selections as $selection) {
            $prLine = PRLine::findOrFail($selection['pr_line_id']);
            
            $lineSubtotal = $selection['unit_price'] * $selection['quantity'];
            $lineTax = 0; // Calculate if needed
            $lineTotal = $lineSubtotal + $lineTax;
            
            $po->lines()->create([
                'item_id' => $prLine->item_id,
                'unit_price' => $selection['unit_price'],
                'quantity' => $selection['quantity'],
                'uom_id' => $prLine->uom_id,
                'subtotal' => $lineSubtotal,
                'tax' => $lineTax,
                'total' => $lineTotal,
                'base_currency_unit_price' => $selection['unit_price'] * $exchangeRate,
                'base_currency_subtotal' => $lineSubtotal * $exchangeRate,
                'base_currency_tax' => $lineTax * $exchangeRate,
                'base_currency_total' => $lineTotal * $exchangeRate
            ]);
        }
        
        return $po->load(['vendor', 'lines.item']);
    }

    /**
     * Validate quantity split
     */
    private function validateQuantitySplit($pr, $vendorSelections)
    {
        $selectionsByLine = collect($vendorSelections)->groupBy('pr_line_id');
        
        foreach ($pr->lines as $prLine) {
            $lineSelections = $selectionsByLine->get($prLine->line_id, collect());
            $totalSelectedQty = $lineSelections->sum('quantity');
            
            if (abs($totalSelectedQty - $prLine->quantity) > 0.001) {
                throw new \Exception("Quantity mismatch for item {$prLine->item->name}. Required: {$prLine->quantity}, Selected: {$totalSelectedQty}");
            }
        }
    }

    /**
     * Get unit price for line based on pricing source
     */
    private function getUnitPriceForLine($prLine, $vendor, $pricingSource, $manualPricesMap)
    {
        switch ($pricingSource) {
            case 'manual':
                $manualPrice = $manualPricesMap->get($prLine->line_id);
                return $manualPrice ? $manualPrice['unit_price'] : 0;
                
            case 'contract':
                // Get price from contract (simplified - you might need more complex logic)
                return $prLine->item->getBestPurchasePriceInCurrency(
                    $vendor->vendor_id, 
                    $prLine->quantity, 
                    $vendor->preferred_currency ?? 'USD'
                );
                
            case 'quotation':
                // Get price from valid quotation
                $quotation = VendorQuotation::where('vendor_id', $vendor->vendor_id)
                    ->where('status', 'received')
                    ->where('validity_date', '>=', now())
                    ->whereHas('lines', function($q) use ($prLine) {
                        $q->where('item_id', $prLine->item_id);
                    })->first();
                
                if ($quotation) {
                    $quotationLine = $quotation->lines()
                        ->where('item_id', $prLine->item_id)
                        ->first();
                    return $quotationLine ? $quotationLine->unit_price : 0;
                }
                return 0;
                
            case 'item_pricing':
                return $prLine->item->getBestPurchasePriceInCurrency(
                    $vendor->vendor_id, 
                    $prLine->quantity, 
                    $vendor->preferred_currency ?? 'USD'
                );
                
            default:
                return 0;
        }
    }

    /**
     * Get exchange rate for currency
     */
    private function getExchangeRateForCurrency($fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }
        
        $rate = CurrencyRate::getCurrentRate($fromCurrency, $toCurrency);
        return $rate ?? 1.0;
    }
}
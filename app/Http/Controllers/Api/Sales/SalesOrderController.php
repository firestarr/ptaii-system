<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SOLine;
use App\Models\Sales\SalesQuotation;
use App\Models\Sales\SalesQuotationLine;
use App\Models\Sales\DeliveryLine;
use App\Models\Item;
use App\Models\Sales\Customer;
use App\Models\CurrencyRate;
use App\Models\ItemStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    /**
     * Display a listing of the sales orders.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $orders = SalesOrder::with(['customer', 'salesQuotation', 'deliveries', 'salesInvoices'])->get();
        return response()->json(['data' => $orders], 200);
    }

    /**
     * Store a newly created sales order in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'so_number' => 'required|unique:SalesOrder,so_number',
            'so_date' => 'required|date',
            'customer_id' => 'required|exists:Customer,customer_id',
            'quotation_id' => 'nullable|exists:SalesQuotation,quotation_id',
            'payment_terms' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'expected_delivery' => 'nullable|date',
            'status' => 'required|string|max:50',
            'currency_code' => 'nullable|string|size:3', // New field for currency
            'lines' => 'required|array',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.uom_id' => 'required|exists:unit_of_measures,uom_id',
            'lines.*.discount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get the customer to check for preferred currency
            $customer = Customer::find($request->customer_id);
            
            // Determine currency to use (from request, customer preference, or system default)
            $currencyCode = $request->currency_code ?? $customer->preferred_currency ?? config('app.base_currency', 'USD');
            $baseCurrency = config('app.base_currency', 'USD');
            
            // Get exchange rate
            $exchangeRate = 1.0; // Default for base currency
            
            if ($currencyCode !== $baseCurrency) {
                $rate = CurrencyRate::where('from_currency', $currencyCode)
                    ->where('to_currency', $baseCurrency)
                    ->where('is_active', true)
                    ->where('effective_date', '<=', $request->so_date)
                    ->where(function($query) use ($request) {
                        $query->where('end_date', '>=', $request->so_date)
                              ->orWhereNull('end_date');
                    })
                    ->orderBy('effective_date', 'desc')
                    ->first();
                    
                if (!$rate) {
                    // Try to find a reverse rate
                    $reverseRate = CurrencyRate::where('from_currency', $baseCurrency)
                        ->where('to_currency', $currencyCode)
                        ->where('is_active', true)
                        ->where('effective_date', '<=', $request->so_date)
                        ->where(function($query) use ($request) {
                            $query->where('end_date', '>=', $request->so_date)
                                  ->orWhereNull('end_date');
                        })
                        ->orderBy('effective_date', 'desc')
                        ->first();
                        
                    if ($reverseRate) {
                        $exchangeRate = 1 / $reverseRate->rate;
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'No exchange rate found for the specified currency on the sales date'
                        ], 422);
                    }
                } else {
                    $exchangeRate = $rate->rate;
                }
            }

            $totalAmount = 0;
            $taxAmount = 0;

            // Create sales order
            $salesOrder = SalesOrder::create([
                'so_number' => $request->so_number,
                'so_date' => $request->so_date,
                'customer_id' => $request->customer_id,
                'quotation_id' => $request->quotation_id,
                'payment_terms' => $request->payment_terms,
                'delivery_terms' => $request->delivery_terms,
                'expected_delivery' => $request->expected_delivery,
                'status' => $request->status,
                'total_amount' => 0, // Will be updated later
                'tax_amount' => 0,    // Will be updated later
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_currency_total' => 0, // Will be updated later
                'base_currency_tax' => 0    // Will be updated later
            ]);

            // Create sales order lines
            foreach ($request->lines as $line) {
                // Get the item
                $item = Item::find($line['item_id']);
                
                // Check if the item is sellable
                if (!$item->is_sellable) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Item ' . $item->name . ' is not sellable'
                    ], 422);
                }
                
                // If unit_price is not provided, get the best sale price for this customer and quantity in order currency
                $unitPrice = $line['unit_price'] ?? $item->getBestSalePriceInCurrency($request->customer_id, $line['quantity'], $currencyCode);
                
                $subtotal = $unitPrice * $line['quantity'];
                $discount = isset($line['discount']) ? $line['discount'] : 0;
                $tax = isset($line['tax']) ? $line['tax'] : 0;
                $total = $subtotal - $discount + $tax;

                // Calculate base currency values
                $baseUnitPrice = $unitPrice * $exchangeRate;
                $baseSubtotal = $subtotal * $exchangeRate;
                $baseDiscount = $discount * $exchangeRate;
                $baseTax = $tax * $exchangeRate;
                $baseTotal = $total * $exchangeRate;

                SOLine::create([
                    'so_id' => $salesOrder->so_id,
                    'item_id' => $line['item_id'],
                    'unit_price' => $unitPrice,
                    'quantity' => $line['quantity'],
                    'uom_id' => $line['uom_id'],
                    'discount' => $discount,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    // New multicurrency fields
                    'base_currency_unit_price' => $baseUnitPrice,
                    'base_currency_subtotal' => $baseSubtotal,
                    'base_currency_discount' => $baseDiscount,
                    'base_currency_tax' => $baseTax,
                    'base_currency_total' => $baseTotal
                ]);

                $totalAmount += $total;
                $taxAmount += $tax;
            }

            // Update totals
            $baseCurrencyTotal = $totalAmount * $exchangeRate;
            $baseCurrencyTax = $taxAmount * $exchangeRate;
            
            $salesOrder->update([
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'base_currency_total' => $baseCurrencyTotal,
                'base_currency_tax' => $baseCurrencyTax
            ]);

            // If created from quotation, update quotation status
            if ($request->quotation_id) {
                $quotation = SalesQuotation::find($request->quotation_id);
                $quotation->update(['status' => 'Converted']);
            }

            DB::commit();

            return response()->json([
                'data' => $salesOrder->load('salesOrderLines'),
                'message' => 'Sales order created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create sales order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a sales order from an existing quotation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function createFromQuotation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quotation_id' => 'required|exists:SalesQuotation,quotation_id',
            'so_number' => 'required|unique:SalesOrder,so_number',
            'so_date' => 'required|date',
            'expected_delivery' => 'nullable|date',
            'status' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get the quotation
            $quotation = SalesQuotation::with('salesQuotationLines')->find($request->quotation_id);

            if ($quotation->status === 'Converted') {
                return response()->json(['message' => 'This quotation has already been converted to a sales order'], 400);
            }

            $totalAmount = 0;
            $taxAmount = 0;

            // Create sales order
            $salesOrder = SalesOrder::create([
                'so_number' => $request->so_number,
                'so_date' => $request->so_date,
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->quotation_id,
                'payment_terms' => $quotation->payment_terms,
                'delivery_terms' => $quotation->delivery_terms,
                'expected_delivery' => $request->expected_delivery,
                'status' => $request->status,
                'total_amount' => 0, // Will be updated later
                'tax_amount' => 0    // Will be updated later
            ]);

            // Create sales order lines from quotation lines
            foreach ($quotation->salesQuotationLines as $quotationLine) {
                // Check if the item is still sellable
                $item = Item::find($quotationLine->item_id);
                if (!$item->is_sellable) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Item ' . $item->name . ' is no longer sellable'
                    ], 422);
                }
                
                SOLine::create([
                    'so_id' => $salesOrder->so_id,
                    'item_id' => $quotationLine->item_id,
                    'unit_price' => $quotationLine->unit_price,
                    'quantity' => $quotationLine->quantity,
                    'uom_id' => $quotationLine->uom_id,
                    'discount' => $quotationLine->discount,
                    'subtotal' => $quotationLine->subtotal,
                    'tax' => $quotationLine->tax,
                    'total' => $quotationLine->total
                ]);

                $totalAmount += $quotationLine->total;
                $taxAmount += $quotationLine->tax;
            }

            // Update totals
            $salesOrder->update([
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount
            ]);

            // Update quotation status
            $quotation->update(['status' => 'Converted']);

            DB::commit();

            return response()->json([
                'data' => $salesOrder->load('salesOrderLines'),
                'message' => 'Sales order created from quotation successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create sales order from quotation', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified sales order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $order = SalesOrder::with([
            'customer',
            'salesQuotation',
            'salesOrderLines.item' => function ($query) {
                $query->select('item_id', 'item_code', 'name'); // explicitly select item_code
            },
            'salesOrderLines.unitOfMeasure',
            'salesOrderLines.deliveryLines', // eager load delivery lines for each sales order line
            'deliveries',
            'salesInvoices'
        ])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Sales order not found'], 404);
        }

        return response()->json(['data' => $order], 200);
    }

    /**
     * Update the specified sales order in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $order = SalesOrder::find($id);

        if (!$order) {
            return response()->json(['message' => 'Sales order not found'], 404);
        }

        // Check if order can be updated (not delivered or invoiced)
        if (in_array($order->status, ['Delivered', 'Invoiced', 'Closed'])) {
            return response()->json(['message' => 'Cannot update a ' . $order->status . ' sales order'], 400);
        }

        $validatorRules = [
            'so_number' => 'required|unique:SalesOrder,so_number,' . $id . ',so_id',
            'so_date' => 'required|date',
            'customer_id' => 'required|exists:Customer,customer_id',
            'payment_terms' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'expected_delivery' => 'nullable|date',
            'status' => 'required|string|max:50',
            'currency_code' => 'nullable|string|size:3', // Add validation for currency
            'lines' => 'required|array',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.uom_id' => 'required|exists:unit_of_measures,uom_id',
            'lines.*.discount' => 'nullable|numeric|min:0',
            'lines.*.tax' => 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $validatorRules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Check if currency is changing
            $currencyCode = $request->currency_code ?? $order->currency_code;
            $baseCurrency = config('app.base_currency', 'USD');
            $exchangeRate = $order->exchange_rate;
            $currencyChanged = $currencyCode !== $order->currency_code;
            
            if ($currencyChanged) {
                // Currency is changing, get new exchange rate
                if ($currencyCode !== $baseCurrency) {
                    $rate = CurrencyRate::where('from_currency', $currencyCode)
                        ->where('to_currency', $baseCurrency)
                        ->where('is_active', true)
                        ->where('effective_date', '<=', $request->so_date)
                        ->where(function($query) use ($request) {
                            $query->where('end_date', '>=', $request->so_date)
                                ->orWhereNull('end_date');
                        })
                        ->orderBy('effective_date', 'desc')
                        ->first();
                        
                    if (!$rate) {
                        // Try to find a reverse rate
                        $reverseRate = CurrencyRate::where('from_currency', $baseCurrency)
                            ->where('to_currency', $currencyCode)
                            ->where('is_active', true)
                            ->where('effective_date', '<=', $request->so_date)
                            ->where(function($query) use ($request) {
                                $query->where('end_date', '>=', $request->so_date)
                                    ->orWhereNull('end_date');
                            })
                            ->orderBy('effective_date', 'desc')
                            ->first();
                            
                        if ($reverseRate) {
                            $exchangeRate = 1 / $reverseRate->rate;
                        } else {
                            DB::rollBack();
                            return response()->json([
                                'message' => 'No exchange rate found for the specified currency'
                            ], 422);
                        }
                    } else {
                        $exchangeRate = $rate->rate;
                    }
                } else {
                    // Converting to base currency
                    $exchangeRate = 1.0;
                }
            }

            // Update main order fields
            $order->update([
                'so_number' => $request->so_number,
                'so_date' => $request->so_date,
                'customer_id' => $request->customer_id,
                'payment_terms' => $request->payment_terms,
                'delivery_terms' => $request->delivery_terms,
                'expected_delivery' => $request->expected_delivery,
                'status' => $request->status,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate
            ]);

            $existingLineIds = $order->salesOrderLines()->pluck('line_id')->toArray();
            $receivedLineIds = [];

            $totalAmount = 0;
            $taxAmount = 0;

            foreach ($request->lines as $line) {
                // Get the item
                $item = Item::find($line['item_id']);
                
                // Check if the item is sellable
                if (!$item->is_sellable) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Item ' . $item->name . ' is not sellable'
                    ], 422);
                }
                
                // If unit_price is not provided, get the best sale price for this customer and quantity
                $unitPrice = isset($line['unit_price']) ? $line['unit_price'] : $item->getBestSalePrice($order->customer_id, $line['quantity']);
                
                // If currency changed, convert the unit price
                if ($currencyChanged && isset($line['line_id'])) {
                    $orderLine = SOLine::find($line['line_id']);
                    if ($orderLine) {
                        // Convert from base currency to new currency
                        $unitPrice = $orderLine->base_currency_unit_price / $exchangeRate;
                    }
                }
                
                $subtotal = $unitPrice * $line['quantity'];
                $discount = $line['discount'] ?? 0;
                $tax = $line['tax'] ?? 0;
                $total = $subtotal - $discount + $tax;

                // Calculate base currency values
                $baseUnitPrice = $unitPrice * $exchangeRate;
                $baseSubtotal = $subtotal * $exchangeRate;
                $baseDiscount = $discount * $exchangeRate;
                $baseTax = $tax * $exchangeRate;
                $baseTotal = $total * $exchangeRate;

                if (isset($line['line_id']) && in_array($line['line_id'], $existingLineIds)) {
                    // Update existing line
                    $orderLine = SOLine::find($line['line_id']);
                    $orderLine->update([
                        'item_id' => $line['item_id'],
                        'unit_price' => $unitPrice,
                        'quantity' => $line['quantity'],
                        'uom_id' => $line['uom_id'],
                        'discount' => $discount,
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'total' => $total,
                        'base_currency_unit_price' => $baseUnitPrice,
                        'base_currency_subtotal' => $baseSubtotal,
                        'base_currency_discount' => $baseDiscount,
                        'base_currency_tax' => $baseTax,
                        'base_currency_total' => $baseTotal
                    ]);
                    $receivedLineIds[] = $line['line_id'];
                } else {
                    // Create new line
                    $newLine = SOLine::create([
                        'so_id' => $order->so_id,
                        'item_id' => $line['item_id'],
                        'unit_price' => $unitPrice,
                        'quantity' => $line['quantity'],
                        'uom_id' => $line['uom_id'],
                        'discount' => $discount,
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'total' => $total,
                        'base_currency_unit_price' => $baseUnitPrice,
                        'base_currency_subtotal' => $baseSubtotal,
                        'base_currency_discount' => $baseDiscount,
                        'base_currency_tax' => $baseTax,
                        'base_currency_total' => $baseTotal
                    ]);
                    $receivedLineIds[] = $newLine->line_id;
                }

                $totalAmount += $total;
                $taxAmount += $tax;
            }

            // Delete lines that were removed
            $linesToDelete = array_diff($existingLineIds, $receivedLineIds);
            if (!empty($linesToDelete)) {
                SOLine::whereIn('line_id', $linesToDelete)->delete();
            }

            // Calculate base currency totals
            $baseCurrencyTotal = $totalAmount * $exchangeRate;
            $baseCurrencyTax = $taxAmount * $exchangeRate;

            // Update order totals
            $order->update([
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'base_currency_total' => $baseCurrencyTotal,
                'base_currency_tax' => $baseCurrencyTax
            ]);

            DB::commit();

            return response()->json([
                'data' => $order->load('salesOrderLines'),
                'message' => 'Sales order updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified sales order from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $order = SalesOrder::find($id);

        if (!$order) {
            return response()->json(['message' => 'Sales order not found'], 404);
        }

        // Check if order can be deleted (no deliveries or invoices)
        if ($order->deliveries->count() > 0) {
            return response()->json(['message' => 'Cannot delete order with related deliveries'], 400);
        }

        if ($order->salesInvoices->count() > 0) {
            return response()->json(['message' => 'Cannot delete order with related invoices'], 400);
        }

        try {
            DB::beginTransaction();

            // Delete related order lines
            $order->salesOrderLines()->delete();

            // Delete the order
            $order->delete();

            DB::commit();

            return response()->json(['message' => 'Sales order deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete sales order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a new line to the specified sales order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addLine(Request $request, $id)
    {
        $order = SalesOrder::find($id);

        if (!$order) {
            return response()->json(['message' => 'Sales order not found'], 404);
        }

        // Check if order can be updated (not delivered or invoiced)
        if (in_array($order->status, ['Delivered', 'Invoiced', 'Closed'])) {
            return response()->json(['message' => 'Cannot update a ' . $order->status . ' sales order'], 400);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'unit_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'uom_id' => 'required|exists:unit_of_measures,uom_id',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Get the item
            $item = Item::find($request->item_id);
            
            // Check if the item is sellable
            if (!$item->is_sellable) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Item ' . $item->name . ' is not sellable'
                ], 422);
            }
            
            // If unit_price is not provided, get the best sale price for this customer and quantity
            $unitPrice = $request->unit_price ?? $item->getBestSalePrice($order->customer_id, $request->quantity);

            $subtotal = $unitPrice * $request->quantity;
            $discount = $request->discount ?? 0;
            $tax = $request->tax ?? 0;
            $total = $subtotal - $discount + $tax;

            $line = SOLine::create([
                'so_id' => $id,
                'item_id' => $request->item_id,
                'unit_price' => $unitPrice,
                'quantity' => $request->quantity,
                'uom_id' => $request->uom_id,
                'discount' => $discount,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total
            ]);

            // Update order totals
            $order->total_amount += $total;
            $order->tax_amount += $tax;
            $order->save();

            DB::commit();

            return response()->json(['data' => $line, 'message' => 'Line added to sales order successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add line to sales order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a line in the specified sales order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  int  $lineId
     * @return \Illuminate\Http\Response
     */
    public function updateLine(Request $request, $id, $lineId)
    {
        $order = SalesOrder::find($id);

        if (!$order) {
            return response()->json(['message' => 'Sales order not found'], 404);
        }

        // Check if order can be updated (not delivered or invoiced)
        if (in_array($order->status, ['Delivered', 'Invoiced', 'Closed'])) {
            return response()->json(['message' => 'Cannot update a ' . $order->status . ' sales order'], 400);
        }

        $line = SOLine::where('so_id', $id)->where('line_id', $lineId)->first();

        if (!$line) {
            return response()->json(['message' => 'Order line not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'unit_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'uom_id' => 'required|exists:unit_of_measures,uom_id',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Get the item
            $item = Item::find($request->item_id);
            
            // Check if the item is sellable
            if (!$item->is_sellable) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Item ' . $item->name . ' is not sellable'
                ], 422);
            }
            
            // If unit_price is not provided, get the best sale price for this customer and quantity
            $unitPrice = $request->unit_price ?? $item->getBestSalePrice($order->customer_id, $request->quantity);

            // Calculate new values
            $subtotal = $unitPrice * $request->quantity;
            $discount = $request->discount ?? 0;
            $tax = $request->tax ?? 0;
            $total = $subtotal - $discount + $tax;

            // Update order totals
            $order->total_amount = $order->total_amount - $line->total + $total;
            $order->tax_amount = $order->tax_amount - $line->tax + $tax;
            $order->save();

            // Update line
            $line->update([
                'item_id' => $request->item_id,
                'unit_price' => $unitPrice,
                'quantity' => $request->quantity,
                'uom_id' => $request->uom_id,
                'discount' => $discount,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total
            ]);

            DB::commit();

            return response()->json(['data' => $line, 'message' => 'Order line updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update order line', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove a line from the specified sales order.
     *
     * @param  int  $id
     * @param  int  $lineId
     * @return \Illuminate\Http\Response
     */
    public function removeLine($id, $lineId)
    {
        $order = SalesOrder::find($id);

        if (!$order) {
            return response()->json(['message' => 'Sales order not found'], 404);
        }

        // Check if order can be updated (not delivered or invoiced)
        if (in_array($order->status, ['Delivered', 'Invoiced', 'Closed'])) {
            return response()->json(['message' => 'Cannot update a ' . $order->status . ' sales order'], 400);
        }

        $line = SOLine::where('so_id', $id)->where('line_id', $lineId)->first();

        if (!$line) {
            return response()->json(['message' => 'Order line not found'], 404);
        }

        try {
            DB::beginTransaction();

            // Update order totals
            $order->total_amount -= $line->total;
            $order->tax_amount -= $line->tax;
            $order->save();

            // Delete the line
            $line->delete();

            DB::commit();

            return response()->json(['message' => 'Order line removed successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to remove order line', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Convert sales order currency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function convertCurrency(Request $request, $id)
    {
        $salesOrder = SalesOrder::find($id);
        
        if (!$salesOrder) {
            return response()->json(['message' => 'Sales order not found'], 404);
        }
        
        // Only allow currency conversion for draft and confirmed orders
        if (!in_array($salesOrder->status, ['Draft', 'Confirmed'])) {
            return response()->json([
                'message' => 'Only Draft or Confirmed sales orders can have their currency converted'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'currency_code' => 'required|string|size:3',
            'use_exchange_rate_date' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Don't convert if already in the target currency
        if ($salesOrder->currency_code === $request->currency_code) {
            return response()->json([
                'message' => 'Sales order is already in the specified currency',
                'data' => $salesOrder
            ]);
        }
        
        $baseCurrency = config('app.base_currency', 'USD');
        
        try {
            DB::beginTransaction();
            
            // Determine which exchange rate to use
            $useExchangeRateDate = $request->use_exchange_rate_date ?? true;
            $exchangeRateDate = $useExchangeRateDate ? $salesOrder->so_date : now()->format('Y-m-d');
            
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
            
            // Update order totals
            $newTotalAmount = $salesOrder->base_currency_total / $newExchangeRate;
            $newTaxAmount = $salesOrder->base_currency_tax / $newExchangeRate;
            
            // Update sales order
            $salesOrder->update([
                'currency_code' => $request->currency_code,
                'exchange_rate' => $newExchangeRate,
                'total_amount' => $newTotalAmount,
                'tax_amount' => $newTaxAmount
            ]);
            
            // Update all line items
            foreach ($salesOrder->salesOrderLines as $line) {
                $newUnitPrice = $line->base_currency_unit_price / $newExchangeRate;
                $newSubtotal = $line->base_currency_subtotal / $newExchangeRate;
                $newDiscount = $line->base_currency_discount / $newExchangeRate;
                $newTax = $line->base_currency_tax / $newExchangeRate;
                $newTotal = $line->base_currency_total / $newExchangeRate;
                
                $line->update([
                    'unit_price' => $newUnitPrice,
                    'subtotal' => $newSubtotal,
                    'discount' => $newDiscount,
                    'tax' => $newTax,
                    'total' => $newTotal
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Sales order currency converted successfully',
                'data' => $salesOrder->fresh()->load('salesOrderLines')
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to convert sales order currency', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get outstanding items for a specific sales order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getOutstandingItems($id)
    {
        $order = SalesOrder::with(['salesOrderLines.item', 'customer'])->find($id);
        
        if (!$order) {
            return response()->json(['message' => 'Sales order tidak ditemukan'], 404);
        }
        
        $outstandingItems = [];
        $allDelivered = true;
        
        foreach ($order->salesOrderLines as $line) {
            $orderedQty = $line->quantity;
            $deliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                ->where('DeliveryLine.so_line_id', $line->line_id)
                ->where('Delivery.status', 'Completed')
                ->sum('DeliveryLine.delivered_quantity');
            
            $outstandingQty = $orderedQty - $deliveredQty;
            
            // Hanya masukkan item yang masih memiliki outstanding quantity
            if ($outstandingQty > 0) {
                $allDelivered = false;
                
                // Get available stock in warehouses
                $warehouseStocks = ItemStock::where('item_id', $line->item_id)
                    ->where('quantity', '>', 0)
                    ->with('warehouse')
                    ->get()
                    ->map(function($stock) {
                        return [
                            'warehouse_id' => $stock->warehouse_id,
                            'warehouse_name' => $stock->warehouse->name,
                            'available_quantity' => $stock->quantity - $stock->reserved_quantity,
                            'total_quantity' => $stock->quantity
                        ];
                    });
                
                $outstandingItems[] = [
                    'so_id' => $order->so_id,
                    'so_number' => $order->so_number,
                    'so_line_id' => $line->line_id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item->name,
                    'item_code' => $line->item->item_code,
                    'ordered_quantity' => $orderedQty,
                    'delivered_quantity' => $deliveredQty,
                    'outstanding_quantity' => $outstandingQty,
                    'uom_id' => $line->uom_id,
                    'warehouse_stocks' => $warehouseStocks
                ];
            }
        }
        
        return response()->json([
            'data' => [
                'so_id' => $order->so_id,
                'so_number' => $order->so_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer->name,
                'status' => $order->status,
                'is_fully_delivered' => $allDelivered,
                'outstanding_items' => $outstandingItems
            ]
        ], 200);
    }

    /**
     * Get all outstanding sales orders.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAllOutstandingSalesOrders()
    {
        // Ambil semua sales order yang belum fully delivered
        $orders = SalesOrder::whereNotIn('status', ['Delivered', 'Closed', 'Cancelled'])
                    ->with(['customer'])
                    ->get();
        
        $outstandingSalesOrders = [];
        
        foreach ($orders as $order) {
            $hasOutstanding = false;
            $outstandingItems = [];
            $totalOutstandingQty = 0;
            
            // Hitung outstanding quantity untuk setiap line item
            foreach ($order->salesOrderLines as $line) {
                $orderedQty = $line->quantity;
                $deliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                    ->where('DeliveryLine.so_line_id', $line->line_id)
                    ->where('Delivery.status', 'Completed')
                    ->sum('DeliveryLine.delivered_quantity');
                
                $outstandingQty = $orderedQty - $deliveredQty;
                
                if ($outstandingQty > 0) {
                    $hasOutstanding = true;
                    $totalOutstandingQty += $outstandingQty;
                    $outstandingItems[] = [
                        'so_line_id' => $line->line_id,
                        'item_id' => $line->item_id,
                        'item_name' => $line->item->name,
                        'item_code' => $line->item->item_code,
                        'ordered_quantity' => $orderedQty,
                        'delivered_quantity' => $deliveredQty,
                        'outstanding_quantity' => $outstandingQty
                    ];
                }
            }
            
            // Hanya tambahkan sales order yang memiliki outstanding items
            if ($hasOutstanding) {
                $outstandingSalesOrders[] = [
                    'so_id' => $order->so_id,
                    'so_number' => $order->so_number,
                    'so_date' => $order->so_date,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer->name,
                    'status' => $order->status,
                    'outstanding_items_count' => count($outstandingItems),
                    'total_outstanding_quantity' => $totalOutstandingQty,
                ];
            }
        }
        
        return response()->json([
            'data' => $outstandingSalesOrders
        ], 200);
    }
}
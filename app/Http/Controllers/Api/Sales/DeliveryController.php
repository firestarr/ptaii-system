<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Delivery;
use App\Models\Sales\DeliveryLine;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SOLine;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransaction;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Display a listing of the deliveries.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $deliveries = Delivery::with(['customer', 'salesOrder'])->get();
        return response()->json(['data' => $deliveries], 200);
    }

    /**
     * Mendapatkan outstanding items dari sales order untuk delivery.
     *
     * @param  int  $soId
     * @return \Illuminate\Http\Response
     */
    public function getOutstandingItemsForDelivery($soId)
    {
        $salesOrder = SalesOrder::with(['salesOrderLines.item', 'salesOrderLines.unitOfMeasure', 'customer'])->find($soId);

        if (!$salesOrder) {
            return response()->json(['message' => 'Sales order tidak ditemukan'], 404);
        }

        $outstandingItems = [];

        foreach ($salesOrder->salesOrderLines as $line) {
            $orderedQty = $line->quantity;
            $deliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                ->where('DeliveryLine.so_line_id', $line->line_id)
                ->sum('DeliveryLine.delivered_quantity');

            $outstandingQty = $orderedQty - $deliveredQty;

            if ($outstandingQty > 0) {
                // Get warehouse inventory for this item
                $warehouseStocks = ItemStock::where('item_id', $line->item_id)
                    ->where('quantity', '>', 0)
                    ->with('warehouse')
                    ->get()
                    ->map(function ($stock) {
                        return [
                            'warehouse_id' => $stock->warehouse_id,
                            'warehouse_name' => $stock->warehouse->name,
                            'available_quantity' => $stock->quantity - $stock->reserved_quantity,
                            'total_quantity' => $stock->quantity
                        ];
                    });

                $outstandingItems[] = [
                    'so_line_id' => $line->line_id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item->name,
                    'item_code' => $line->item->item_code,
                    'uom_id' => $line->uom_id,
                    'uom_name' => $line->unitOfMeasure ? $line->unitOfMeasure->name : '',
                    'ordered_quantity' => $orderedQty,
                    'delivered_quantity' => $deliveredQty,
                    'outstanding_quantity' => $outstandingQty,
                    'warehouse_stocks' => $warehouseStocks
                ];
            }
        }

        return response()->json([
            'data' => [
                'so_id' => $salesOrder->so_id,
                'so_number' => $salesOrder->so_number,
                'customer_id' => $salesOrder->customer_id,
                'customer_name' => $salesOrder->customer->name,
                'outstanding_items' => $outstandingItems
            ]
        ], 200);
    }

    /**
     * Mendapatkan semua sales order dengan outstanding items.
     *
     * @return \Illuminate\Http\Response
     */
    public function getOutstandingSalesOrders()
    {
        $salesOrders = SalesOrder::whereNotIn('status', ['Delivered', 'Closed', 'Cancelled'])
            ->with('customer')
            ->get();

        $result = [];

        foreach ($salesOrders as $order) {
            $hasOutstanding = false;
            $totalOutstandingQty = 0;

            // Periksa apakah SO memiliki outstanding items
            foreach ($order->salesOrderLines as $line) {
                $orderedQty = $line->quantity;
                $deliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                    ->where('DeliveryLine.so_line_id', $line->line_id)
                    ->sum('DeliveryLine.delivered_quantity');

                $outstandingQty = $orderedQty - $deliveredQty;

                if ($outstandingQty > 0) {
                    $hasOutstanding = true;
                    $totalOutstandingQty += $outstandingQty;
                }
            }

            if ($hasOutstanding) {
                $result[] = [
                    'so_id' => $order->so_id,
                    'so_number' => $order->so_number,
                    'so_date' => $order->so_date,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer->name,
                    'status' => $order->status,
                    'outstanding_quantity' => $totalOutstandingQty
                ];
            }
        }

        return response()->json(['data' => $result], 200);
    }

    /**
     * Store a newly created delivery from outstanding items of multiple sales orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeFromOutstanding(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_number' => 'required|unique:Delivery,delivery_number',
            'delivery_date' => 'required|date',
            'shipping_method' => 'nullable|string|max:50',
            'tracking_number' => 'nullable|string|max:50',
            'items' => 'required|array',
            'items.*.so_line_id' => 'required|exists:SOLine,line_id',
            'items.*.delivered_quantity' => 'required|numeric|min:0.01',
            'items.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'items.*.batch_number' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get stock validation settings
        $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
        $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

        try {
            DB::beginTransaction();

            // Mengelompokkan item berdasarkan SO ID untuk membuat delivery terpisah untuk setiap SO
            $itemsBySO = [];

            foreach ($request->items as $item) {
                $soLine = SOLine::find($item['so_line_id']);
                if (!isset($itemsBySO[$soLine->so_id])) {
                    $itemsBySO[$soLine->so_id] = [];
                }
                $itemsBySO[$soLine->so_id][] = $item;
            }

            $createdDeliveries = [];

            // Proses masing-masing SO secara terpisah
            $deliveryCount = 0;
            foreach ($itemsBySO as $soId => $items) {
                $salesOrder = SalesOrder::find($soId);
                $deliveryNumber = $request->delivery_number;

                // Jika ada lebih dari satu SO, tambahkan suffix
                if (count($itemsBySO) > 1) {
                    $deliveryNumber .= '-' . chr(65 + $deliveryCount); // Tambahkan A, B, C, dst.
                }

                // Buat delivery header
                $delivery = Delivery::create([
                    'delivery_number' => $deliveryNumber,
                    'delivery_date' => $request->delivery_date,
                    'so_id' => $soId,
                    'customer_id' => $salesOrder->customer_id,
                    'status' => 'Pending',
                    'shipping_method' => $request->shipping_method,
                    'tracking_number' => $request->tracking_number
                ]);

                // Proses setiap item untuk delivery ini
                foreach ($items as $item) {
                    $soLine = SOLine::find($item['so_line_id']);

                    // Hitung jumlah yang sudah dikirim sebelumnya
                    $previouslyDeliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                        ->where('DeliveryLine.so_line_id', $item['so_line_id'])
                        ->sum('DeliveryLine.delivered_quantity');

                    // Hitung outstanding quantity
                    $outstandingQty = $soLine->quantity - $previouslyDeliveredQty;

                    // Validasi jumlah yang dikirim tidak melebihi outstanding
                    if ($item['delivered_quantity'] > $outstandingQty) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Jumlah pengiriman melebihi outstanding quantity untuk item ' .
                                $soLine->item_id . ' (Outstanding: ' . $outstandingQty . ')'
                        ], 400);
                    }

                    // Validasi ketersediaan stok jika validasi diaktifkan
                    if ($enforceStockValidation) {
                        $itemStock = ItemStock::where('item_id', $soLine->item_id)
                            ->where('warehouse_id', $item['warehouse_id'])
                            ->first();

                        if (!$itemStock) {
                            // Buat itemStock baru jika belum ada
                            $itemStock = ItemStock::create([
                                'item_id' => $soLine->item_id,
                                'warehouse_id' => $item['warehouse_id'],
                                'quantity' => 0,
                                'reserved_quantity' => 0
                            ]);
                        }

                        // Jika stok tidak boleh negatif, validasi ketersediaan
                        if (!$allowNegativeStock && $itemStock->available_quantity < $item['delivered_quantity']) {
                            DB::rollBack();
                            return response()->json([
                                'message' => 'Stok tersedia tidak mencukupi di warehouse yang dipilih untuk item ' .
                                    $soLine->item_id . ' (Tersedia: ' . $itemStock->available_quantity . ')'
                            ], 400);
                        }

                        // Reservasi stok
                        $itemStock->increment('reserved_quantity', $item['delivered_quantity']);

                        // Set reservasi reference berdasarkan apakah negative stock diperbolehkan
                        $reservationReference = $allowNegativeStock ?
                            'SO-' . $soLine->so_id . ' (Negative Stock Allowed)' :
                            'SO-' . $soLine->so_id;
                    } else {
                        $reservationReference = null;
                    }

                    // Buat delivery line
                    DeliveryLine::create([
                        'delivery_id' => $delivery->delivery_id,
                        'so_line_id' => $item['so_line_id'],
                        'item_id' => $soLine->item_id,
                        'delivered_quantity' => $item['delivered_quantity'],
                        'warehouse_id' => $item['warehouse_id'],
                        'batch_number' => $item['batch_number'] ?? null,
                        'reservation_reference' => $reservationReference
                    ]);
                }

                $createdDeliveries[] = $delivery->load('deliveryLines');
                $deliveryCount++;

                // Update status SO
                $this->updateSalesOrderStatus($soId);
            }

            DB::commit();

            return response()->json([
                'data' => $createdDeliveries,
                'message' => 'Delivery orders berhasil dibuat'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat delivery orders', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update status sales order berdasarkan pengiriman.
     *
     * @param  int  $soId
     * @return void
     */
    private function updateSalesOrderStatus($soId)
    {
        $salesOrder = SalesOrder::with('salesOrderLines')->find($soId);

        // Periksa apakah semua item sudah terkirim sepenuhnya
        $allDelivered = true;

        foreach ($salesOrder->salesOrderLines as $line) {
            $orderedQty = $line->quantity;
            $deliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                ->where('DeliveryLine.so_line_id', $line->line_id)
                ->where('Delivery.status', 'Completed')
                ->sum('DeliveryLine.delivered_quantity');

            if ($deliveredQty < $orderedQty) {
                $allDelivered = false;
                break;
            }
        }

        // Update status SO
        if ($allDelivered) {
            $salesOrder->update(['status' => 'Delivered']);
        } else {
            // Jika sebagian sudah dikirim
            $anyDelivered = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                ->join('SOLine', 'DeliveryLine.so_line_id', '=', 'SOLine.line_id')
                ->where('SOLine.so_id', $soId)
                ->where('Delivery.status', 'Completed')
                ->exists();

            if ($anyDelivered && $salesOrder->status !== 'Delivered') {
                $salesOrder->update(['status' => 'Delivering']);
            }
        }
    }

    /**
     * Store a newly created delivery in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_number' => 'required|unique:Delivery,delivery_number',
            'delivery_date' => 'required|date',
            'so_id' => 'required|exists:SalesOrder,so_id',
            'shipping_method' => 'nullable|string|max:50',
            'tracking_number' => 'nullable|string|max:50',
            'lines' => 'required|array',
            'lines.*.so_line_id' => 'required|exists:SOLine,line_id',
            'lines.*.delivered_quantity' => 'required|numeric|min:0',
            'lines.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'lines.*.batch_number' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get stock validation settings
        $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
        $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

        try {
            DB::beginTransaction();

            // Get the sales order
            $salesOrder = SalesOrder::find($request->so_id);

            // Create delivery
            $delivery = Delivery::create([
                'delivery_number' => $request->delivery_number,
                'delivery_date' => $request->delivery_date,
                'so_id' => $request->so_id,
                'customer_id' => $salesOrder->customer_id,
                'status' => 'Pending',
                'shipping_method' => $request->shipping_method,
                'tracking_number' => $request->tracking_number
            ]);

            // Create delivery lines
            foreach ($request->lines as $line) {
                $soLine = SOLine::find($line['so_line_id']);

                // Hitung jumlah yang sudah dikirim sebelumnya
                $previouslyDeliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                    ->where('DeliveryLine.so_line_id', $line['so_line_id'])
                    ->sum('DeliveryLine.delivered_quantity');

                // Hitung outstanding quantity
                $outstandingQty = $soLine->quantity - $previouslyDeliveredQty;

                // Validate if the delivered quantity is valid
                if ($line['delivered_quantity'] > $outstandingQty) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Delivered quantity exceeds outstanding quantity for item ' .
                            $soLine->item_id . ' (Outstanding: ' . $outstandingQty . ')'
                    ], 400);
                }

                // Validate stock availability if stock validation is enabled
                if ($enforceStockValidation) {
                    $itemStock = ItemStock::where('item_id', $soLine->item_id)
                        ->where('warehouse_id', $line['warehouse_id'])
                        ->first();

                    if (!$itemStock) {
                        // Create item stock record if it doesn't exist
                        $itemStock = ItemStock::create([
                            'item_id' => $soLine->item_id,
                            'warehouse_id' => $line['warehouse_id'],
                            'quantity' => 0,
                            'reserved_quantity' => 0
                        ]);
                    }

                    // If negative stock is not allowed, validate stock availability
                    if (!$allowNegativeStock && $itemStock->available_quantity < $line['delivered_quantity']) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Insufficient available stock in warehouse for item ' .
                                $soLine->item_id . ' (Available: ' . $itemStock->available_quantity . ')'
                        ], 400);
                    }

                    // Reserve stock
                    $itemStock->increment('reserved_quantity', $line['delivered_quantity']);

                    // Set reservation reference based on whether negative stock is allowed
                    $reservationReference = $allowNegativeStock ?
                        'SO-' . $salesOrder->so_id . ' (Negative Stock Allowed)' :
                        'SO-' . $salesOrder->so_id;
                } else {
                    $reservationReference = null;
                }

                // Create delivery line
                DeliveryLine::create([
                    'delivery_id' => $delivery->delivery_id,
                    'so_line_id' => $line['so_line_id'],
                    'item_id' => $soLine->item_id,
                    'delivered_quantity' => $line['delivered_quantity'],
                    'warehouse_id' => $line['warehouse_id'],
                    'batch_number' => $line['batch_number'] ?? null,
                    'reservation_reference' => $reservationReference
                ]);
            }

            // Update sales order status
            $salesOrder->update(['status' => 'Delivering']);

            DB::commit();

            return response()->json([
                'data' => $delivery->load('deliveryLines'),
                'message' => 'Delivery created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create delivery', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified delivery.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid delivery ID'], 400);
        }

        $delivery = Delivery::with([
            'customer',
            'salesOrder',
            'deliveryLines.item',
            'deliveryLines.warehouse',
            'deliveryLines.salesOrderLine'
        ])->find($id);

        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        return response()->json(['data' => $delivery], 200);
    }

    /**
     * Update the specified delivery in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid delivery ID'], 400);
        }

        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        // Check if delivery can be updated (not completed)
        if ($delivery->status === 'Completed') {
            return response()->json(['message' => 'Cannot update a completed delivery'], 400);
        }

        $validator = Validator::make($request->all(), [
            'delivery_number' => 'required|unique:Delivery,delivery_number,' . $id . ',delivery_id',
            'delivery_date' => 'required|date',
            'shipping_method' => 'nullable|string|max:50',
            'tracking_number' => 'nullable|string|max:50',
            'status' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $delivery->update($request->all());

            // If status is changed to 'Completed', process stock and update the sales order status
            if ($request->status === 'Completed' && $delivery->status !== 'Completed') {
                $this->processCompletedDelivery($delivery);
            }

            DB::commit();

            return response()->json(['data' => $delivery, 'message' => 'Delivery updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update delivery', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Process a completed delivery by updating stock and sales order status.
     *
     * @param  \App\Models\Sales\Delivery  $delivery
     * @return void
     * @throws \Exception
     */
    private function processCompletedDelivery($delivery)
    {
        // Get stock validation settings
        $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
        $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

        // Load delivery lines if not already loaded
        if (!$delivery->relationLoaded('deliveryLines')) {
            $delivery->load('deliveryLines');
        }

        // Process each delivery line
        foreach ($delivery->deliveryLines as $line) {
            if ($enforceStockValidation) {
                // Get or create item stock record
                $itemStock = ItemStock::firstOrNew([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $line->warehouse_id
                ]);

                if (!$itemStock->exists) {
                    $itemStock->quantity = 0;
                    $itemStock->reserved_quantity = 0;
                    $itemStock->save();
                }

                // If negative stock is not allowed, validate stock availability
                if (!$allowNegativeStock && $itemStock->quantity < $line->delivered_quantity) {
                    throw new \Exception('Insufficient stock for item ' . $line->item_id . ' in warehouse ' . $line->warehouse_id);
                }

                // ===== UPDATED: Use Odoo-style stock transaction =====
                // Create stock transaction (always positive quantity, direction determined by move_type)
                $transaction = StockTransaction::create([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $line->warehouse_id,
                    'dest_warehouse_id' => null,
                    'transaction_type' => StockTransaction::TYPE_ISSUE,
                    'move_type' => StockTransaction::MOVE_TYPE_OUT, // Outgoing move
                    'quantity' => $line->delivered_quantity, // Always positive
                    'transaction_date' => now(),
                    'reference_document' => 'delivery',
                    'reference_number' => $delivery->delivery_number,
                    'origin' => "SO-{$delivery->so_id}",
                    'batch_id' => null,
                    'state' => StockTransaction::STATE_DRAFT,
                    'notes' => $allowNegativeStock ? 'Negative stock allowed' : null
                ]);

                // Auto-confirm the transaction to update stock
                $transaction->markAsDone();
                // ===== END UPDATE =====

                // Decrease reserved quantity if this delivery was using reserved stock
                if ($line->reservation_reference) {
                    $itemStock->decrement('reserved_quantity', $line->delivered_quantity);
                }
            }
        }

        // Update sales order status
        $this->updateSalesOrderStatus($delivery->so_id);
    }

    /**
     * Remove the specified delivery from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid delivery ID'], 400);
        }

        $delivery = Delivery::with('deliveryLines')->find($id);

        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        // Check if delivery can be deleted (not completed)
        if ($delivery->status === 'Completed') {
            return response()->json(['message' => 'Cannot delete a completed delivery'], 400);
        }

        try {
            DB::beginTransaction();

            // Get stock validation settings
            $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';

            // Release reserved stock if stock validation is enabled
            if ($enforceStockValidation) {
                foreach ($delivery->deliveryLines as $line) {
                    if ($line->reservation_reference) {
                        // Get item stock record
                        $itemStock = ItemStock::where('item_id', $line->item_id)
                            ->where('warehouse_id', $line->warehouse_id)
                            ->first();

                        if ($itemStock) {
                            // Release reservation
                            $itemStock->decrement('reserved_quantity', $line->delivered_quantity);
                        }
                    }
                }
            }

            // Delete related delivery lines
            $delivery->deliveryLines()->delete();

            // Delete the delivery
            $delivery->delete();

            // Update sales order status if needed
            $salesOrder = SalesOrder::find($delivery->so_id);
            $remainingDeliveries = Delivery::where('so_id', $delivery->so_id)->count();

            if ($remainingDeliveries === 0) {
                $salesOrder->update(['status' => 'Confirmed']);
            }

            DB::commit();

            return response()->json(['message' => 'Delivery deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete delivery', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark a delivery as completed and update inventory.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function complete($id)
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid delivery ID'], 400);
        }

        $delivery = Delivery::with('deliveryLines')->find($id);

        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        if ($delivery->status === 'Completed') {
            return response()->json(['message' => 'Delivery already completed'], 400);
        }

        try {
            DB::beginTransaction();

            // Get stock validation settings
            $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
            $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

            // Process each delivery line
            foreach ($delivery->deliveryLines as $line) {
                if ($enforceStockValidation) {
                    // Get or create item stock record
                    $itemStock = ItemStock::firstOrNew([
                        'item_id' => $line->item_id,
                        'warehouse_id' => $line->warehouse_id
                    ]);

                    if (!$itemStock->exists) {
                        $itemStock->quantity = 0;
                        $itemStock->reserved_quantity = 0;
                        $itemStock->save();
                    }

                    // If negative stock is not allowed, validate stock availability
                    if (!$allowNegativeStock && $itemStock->quantity < $line->delivered_quantity) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Insufficient stock for item ' . $line->item_id .
                                ' in warehouse ' . $line->warehouse_id .
                                ' (Available: ' . $itemStock->quantity . ')'
                        ], 400);
                    }

                    // ===== UPDATED: Use Odoo-style stock transaction =====
                    // Create and confirm stock transaction
                    $transaction = StockTransaction::create([
                        'item_id' => $line->item_id,
                        'warehouse_id' => $line->warehouse_id,
                        'dest_warehouse_id' => null,
                        'transaction_type' => StockTransaction::TYPE_ISSUE,
                        'move_type' => StockTransaction::MOVE_TYPE_OUT,
                        'quantity' => $line->delivered_quantity, // Always positive
                        'transaction_date' => now(),
                        'reference_document' => 'delivery',
                        'reference_number' => $delivery->delivery_number,
                        'origin' => "SO-{$delivery->so_id}",
                        'batch_id' => null,
                        'state' => StockTransaction::STATE_DRAFT,
                        'notes' => $allowNegativeStock ? 'Negative stock allowed' : null
                    ]);

                    // Auto-confirm to update stock
                    $transaction->markAsDone();
                    // ===== END UPDATE =====

                    // Decrease reserved quantity if this delivery was using reserved stock
                    if ($line->reservation_reference) {
                        $itemStock->fresh()->decrement('reserved_quantity', $line->delivered_quantity);
                    }
                }
            }

            // Update delivery status
            $delivery->status = 'Completed';
            $delivery->save();

            // Update sales order status
            $this->updateSalesOrderStatus($delivery->so_id);

            DB::commit();

            return response()->json(['message' => 'Delivery completed successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to complete delivery', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a list of deliveries for a specific sales order.
     *
     * @param  int  $soId
     * @return \Illuminate\Http\Response
     */
    public function getDeliveriesBySalesOrder($soId)
    {
        $salesOrder = SalesOrder::find($soId);

        if (!$salesOrder) {
            return response()->json(['message' => 'Sales order not found'], 404);
        }

        $deliveries = Delivery::with([
            'deliveryLines.item',
            'deliveryLines.warehouse'
        ])
            ->where('so_id', $soId)
            ->get();

        // Calculate delivery progress
        $orderLines = $salesOrder->salesOrderLines;
        $totalOrderedQty = $orderLines->sum('quantity');
        $totalDeliveredQty = DeliveryLine::leftJoin('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
            ->where('Delivery.so_id', $soId)
            ->whereIn('Delivery.status', ['Completed', 'Delivered', 'Invoiced'])
            ->sum('DeliveryLine.delivered_quantity');

        $deliveryProgress = $totalOrderedQty > 0 ?
            round(($totalDeliveredQty / $totalOrderedQty) * 100, 2) : 0;

        return response()->json([
            'data' => [
                'sales_order' => [
                    'so_id' => $salesOrder->so_id,
                    'so_number' => $salesOrder->so_number,
                    'status' => $salesOrder->status
                ],
                'delivery_progress' => $deliveryProgress,
                'total_ordered_quantity' => $totalOrderedQty,
                'total_delivered_quantity' => $totalDeliveredQty,
                'pending_quantity' => $totalOrderedQty - $totalDeliveredQty,
                'deliveries' => $deliveries
            ]
        ], 200);
    }

    /**
     * Get a list of all incomplete deliveries.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPendingDeliveries()
    {
        $pendingDeliveries = Delivery::with(['customer', 'salesOrder'])
            ->where('status', 'Pending')
            ->orderBy('delivery_date')
            ->get();

        return response()->json(['data' => $pendingDeliveries], 200);
    }

    /**
     * Add a line to an existing delivery.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addLine(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid delivery ID'], 400);
        }

        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        if ($delivery->status === 'Completed') {
            return response()->json(['message' => 'Cannot modify a completed delivery'], 400);
        }

        $validator = Validator::make($request->all(), [
            'so_line_id' => 'required|exists:SOLine,line_id',
            'delivered_quantity' => 'required|numeric|min:0.01',
            'warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'batch_number' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $soLine = SOLine::find($request->so_line_id);

            // Verify the sales order line belongs to the same sales order
            if ($soLine->so_id != $delivery->so_id) {
                DB::rollBack();
                return response()->json([
                    'message' => 'The sales order line does not belong to the delivery\'s sales order'
                ], 400);
            }

            // Calculate outstanding quantity
            $previouslyDeliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                ->where('DeliveryLine.so_line_id', $request->so_line_id)
                ->sum('DeliveryLine.delivered_quantity');

            $outstandingQty = $soLine->quantity - $previouslyDeliveredQty;

            // Validate delivery quantity
            if ($request->delivered_quantity > $outstandingQty) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Delivered quantity exceeds outstanding quantity (Available: ' . $outstandingQty . ')'
                ], 400);
            }

            // Get stock validation settings
            $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
            $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

            // Validate stock availability if required
            if ($enforceStockValidation) {
                $itemStock = ItemStock::where('item_id', $soLine->item_id)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->first();

                if (!$itemStock) {
                    $itemStock = ItemStock::create([
                        'item_id' => $soLine->item_id,
                        'warehouse_id' => $request->warehouse_id,
                        'quantity' => 0,
                        'reserved_quantity' => 0
                    ]);
                }

                if (!$allowNegativeStock && $itemStock->available_quantity < $request->delivered_quantity) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Insufficient available stock in warehouse (Available: ' .
                            $itemStock->available_quantity . ')'
                    ], 400);
                }

                // Reserve stock
                $itemStock->increment('reserved_quantity', $request->delivered_quantity);

                $reservationReference = $allowNegativeStock ?
                    'SO-' . $soLine->so_id . ' (Negative Stock Allowed)' :
                    'SO-' . $soLine->so_id;
            } else {
                $reservationReference = null;
            }

            // Create delivery line
            $deliveryLine = DeliveryLine::create([
                'delivery_id' => $delivery->delivery_id,
                'so_line_id' => $request->so_line_id,
                'item_id' => $soLine->item_id,
                'delivered_quantity' => $request->delivered_quantity,
                'warehouse_id' => $request->warehouse_id,
                'batch_number' => $request->batch_number,
                'reservation_reference' => $reservationReference
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Delivery line added successfully',
                'data' => $deliveryLine
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add delivery line', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove a line from a delivery.
     *
     * @param  int  $id
     * @param  int  $lineId
     * @return \Illuminate\Http\Response
     */
    public function removeLine($id, $lineId)
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid delivery ID'], 400);
        }

        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        if ($delivery->status === 'Completed') {
            return response()->json(['message' => 'Cannot modify a completed delivery'], 400);
        }

        $line = DeliveryLine::where('delivery_id', $id)
            ->where('line_id', $lineId)
            ->first();

        if (!$line) {
            return response()->json(['message' => 'Delivery line not found'], 404);
        }

        try {
            DB::beginTransaction();

            // Get stock validation settings
            $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';

            // Release reserved stock if applicable
            if ($enforceStockValidation && $line->reservation_reference) {
                $itemStock = ItemStock::where('item_id', $line->item_id)
                    ->where('warehouse_id', $line->warehouse_id)
                    ->first();

                if ($itemStock) {
                    $itemStock->decrement('reserved_quantity', $line->delivered_quantity);
                }
            }

            // Delete the line
            $line->delete();

            DB::commit();

            return response()->json(['message' => 'Delivery line removed successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to remove delivery line', 'error' => $e->getMessage()], 500);
        }
    }
}
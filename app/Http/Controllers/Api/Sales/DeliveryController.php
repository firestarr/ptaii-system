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
use App\Models\Warehouse;
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
        try {
            $deliveries = Delivery::with(['customer', 'salesOrder'])
                ->orderBy('delivery_date', 'desc')
                ->get();

            return response()->json([
                'data' => $deliveries,
                'message' => 'Deliveries retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve deliveries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified delivery with enhanced stock information.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid delivery ID'], 400);
        }

        try {
            $delivery = Delivery::with([
                'customer',
                'salesOrder',
                'deliveryLines' => function($query) {
                    $query->with([
                        'item' => function($q) {
                            $q->with('unitOfMeasure');
                        },
                        'warehouse',
                        'salesOrderLine' => function($q) {
                            $q->with([
                                'salesOrder' => function($sq) {
                                    $sq->with('customer');
                                },
                                'unitOfMeasure'
                            ]);
                        }
                    ]);
                }
            ])->find($id);

            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // Get consolidated sales orders info
            $consolidatedSOs = collect();
            $soIds = [];

            foreach ($delivery->deliveryLines as $line) {
                if ($line->salesOrderLine && $line->salesOrderLine->salesOrder) {
                    $so = $line->salesOrderLine->salesOrder;
                    if (!in_array($so->so_id, $soIds)) {
                        $soIds[] = $so->so_id;
                        $consolidatedSOs->push([
                            'so_id' => $so->so_id,
                            'so_number' => $so->so_number,
                            'so_date' => $so->so_date,
                            'customer_name' => $so->customer->name ?? 'N/A',
                            'status' => $so->status
                        ]);
                    }
                }
            }

            // Enhanced delivery lines with comprehensive stock data
            $enrichedLines = $delivery->deliveryLines->map(function($line) {
                $lineData = $line->toArray();
                
                // Add warehouse information with fallback
                if ($line->warehouse) {
                    $lineData['warehouse'] = [
                        'warehouse_id' => $line->warehouse->warehouse_id,
                        'name' => $line->warehouse->name,
                        'code' => $line->warehouse->code ?? ''
                    ];
                } else if ($line->warehouse_id) {
                    $warehouse = Warehouse::find($line->warehouse_id);
                    if ($warehouse) {
                        $lineData['warehouse'] = [
                            'warehouse_id' => $warehouse->warehouse_id,
                            'name' => $warehouse->name,
                            'code' => $warehouse->code ?? ''
                        ];
                    }
                }

                // ===== NEW: Add comprehensive stock information =====
                $lineData['stock_info'] = $this->getItemStockInfo($line->item_id, $line->warehouse_id);
                
                // ===== NEW: Add available warehouses with stock =====
                $lineData['available_warehouses'] = $this->getAvailableWarehousesForItem($line->item_id);

                // Add sales order line information
                if ($line->salesOrderLine) {
                    $lineData['sales_order_line'] = [
                        'line_id' => $line->salesOrderLine->line_id,
                        'quantity' => $line->salesOrderLine->quantity,
                        'unit_price' => $line->salesOrderLine->unit_price,
                        'sales_order' => $line->salesOrderLine->salesOrder ? [
                            'so_id' => $line->salesOrderLine->salesOrder->so_id,
                            'so_number' => $line->salesOrderLine->salesOrder->so_number,
                            'so_date' => $line->salesOrderLine->salesOrder->so_date
                        ] : null
                    ];
                }

                // Add item information with UOM
                if ($line->item) {
                    $lineData['item'] = [
                        'item_id' => $line->item->item_id,
                        'item_code' => $line->item->item_code,
                        'name' => $line->item->name,
                        'description' => $line->item->description,
                        'unit_of_measure' => $line->item->unitOfMeasure ? [
                            'uom_id' => $line->item->unitOfMeasure->uom_id,
                            'name' => $line->item->unitOfMeasure->name,
                            'symbol' => $line->item->unitOfMeasure->symbol
                        ] : null
                    ];
                }

                return $lineData;
            });

            $response = [
                'data' => array_merge($delivery->toArray(), [
                    'delivery_lines' => $enrichedLines,
                    'consolidated_sales_orders' => $consolidatedSOs,
                    'consolidation_summary' => [
                        'total_sos' => $consolidatedSOs->count(),
                        'total_items' => $delivery->deliveryLines->count(),
                        'total_quantity' => $delivery->deliveryLines->sum('delivered_quantity')
                    ]
                ])
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get comprehensive stock information for an item in a specific warehouse.
     *
     * @param  int  $itemId
     * @param  int  $warehouseId
     * @return array
     */
    private function getItemStockInfo($itemId, $warehouseId)
    {
        try {
            $itemStock = ItemStock::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->with('warehouse')
                ->first();

            if (!$itemStock) {
                return [
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => Warehouse::find($warehouseId)?->name ?? 'Unknown',
                    'total_quantity' => 0,
                    'reserved_quantity' => 0,
                    'available_quantity' => 0,
                    'status' => 'no_stock'
                ];
            }

            $availableQty = $itemStock->quantity - $itemStock->reserved_quantity;
            
            // Determine stock status
            $status = 'in_stock';
            if ($itemStock->quantity <= 0) {
                $status = 'out_of_stock';
            } elseif ($availableQty <= 0) {
                $status = 'fully_reserved';
            } elseif ($availableQty < 10) { // You can make this configurable
                $status = 'low_stock';
            }

            return [
                'warehouse_id' => $itemStock->warehouse_id,
                'warehouse_name' => $itemStock->warehouse->name ?? 'Unknown',
                'total_quantity' => $itemStock->quantity,
                'reserved_quantity' => $itemStock->reserved_quantity,
                'available_quantity' => $availableQty,
                'status' => $status,
                'last_updated' => $itemStock->updated_at
            ];

        } catch (\Exception $e) {
            return [
                'warehouse_id' => $warehouseId,
                'warehouse_name' => 'Error',
                'total_quantity' => 0,
                'reserved_quantity' => 0,
                'available_quantity' => 0,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all available warehouses with stock for an item.
     *
     * @param  int  $itemId
     * @return array
     */
    private function getAvailableWarehousesForItem($itemId)
    {
        try {
            $stocks = ItemStock::where('item_id', $itemId)
                ->where('quantity', '>', 0)
                ->with('warehouse')
                ->get();

            return $stocks->map(function($stock) {
                return [
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse->name,
                    'warehouse_code' => $stock->warehouse->code ?? '',
                    'total_quantity' => $stock->quantity,
                    'reserved_quantity' => $stock->reserved_quantity,
                    'available_quantity' => $stock->quantity - $stock->reserved_quantity
                ];
            })->toArray();

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get real-time stock information for specific items and warehouses.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getStockInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.warehouse_id' => 'required|integer|exists:warehouses,warehouse_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $stockInfo = [];

            foreach ($request->items as $item) {
                $stockInfo[] = array_merge($item, [
                    'stock_info' => $this->getItemStockInfo($item['item_id'], $item['warehouse_id'])
                ]);
            }

            return response()->json([
                'data' => $stockInfo,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve stock information',
                'error' => $e->getMessage()
            ], 500);
        }
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
     * Get outstanding items for multiple sales orders (for consolidated delivery)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getOutstandingItemsForSOs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'so_ids' => 'required|array',
            'so_ids.*' => 'required|integer|exists:SalesOrder,so_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $outstandingItems = [];

            foreach ($request->so_ids as $soId) {
                $salesOrder = SalesOrder::with([
                    'salesOrderLines.item.unitOfMeasure',
                    'customer'
                ])->find($soId);

                if (!$salesOrder) continue;

                foreach ($salesOrder->salesOrderLines as $line) {
                    $orderedQty = $line->quantity;
                    $deliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                        ->where('DeliveryLine.so_line_id', $line->line_id)
                        ->where('Delivery.status', '!=', 'Cancelled')
                        ->sum('DeliveryLine.delivered_quantity');

                    $outstandingQty = $orderedQty - $deliveredQty;

                    if ($outstandingQty > 0) {
                        // Get warehouse stocks for this item
                        $warehouseStocks = ItemStock::where('item_id', $line->item_id)
                            ->where('quantity', '>', 0)
                            ->with('warehouse')
                            ->get()
                            ->map(function ($stock) {
                                return [
                                    'warehouse_id' => $stock->warehouse_id,
                                    'warehouse_name' => $stock->warehouse->name,
                                    'warehouse_code' => $stock->warehouse->code ?? '',
                                    'available_quantity' => $stock->quantity - $stock->reserved_quantity,
                                    'total_quantity' => $stock->quantity,
                                    'reserved_quantity' => $stock->reserved_quantity
                                ];
                            });

                        $outstandingItems[] = [
                            'so_line_id' => $line->line_id,
                            'so_id' => $salesOrder->so_id,
                            'so_number' => $salesOrder->so_number,
                            'customer_id' => $salesOrder->customer_id,
                            'customer_name' => $salesOrder->customer->name,
                            'item_id' => $line->item_id,
                            'item_name' => $line->item->name,
                            'item_code' => $line->item->item_code,
                            'uom_id' => $line->uom_id,
                            'uom_name' => $line->unitOfMeasure ? $line->unitOfMeasure->name : '',
                            'uom_symbol' => $line->unitOfMeasure ? $line->unitOfMeasure->symbol : '',
                            'ordered_quantity' => $orderedQty,
                            'delivered_quantity' => $deliveredQty,
                            'outstanding_quantity' => $outstandingQty,
                            'unit_price' => $line->unit_price,
                            'warehouse_stocks' => $warehouseStocks
                        ];
                    }
                }
            }

            return response()->json([
                'data' => $outstandingItems,
                'summary' => [
                    'total_items' => count($outstandingItems),
                    'total_outstanding_quantity' => array_sum(array_column($outstandingItems, 'outstanding_quantity'))
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve outstanding items',
                'error' => $e->getMessage()
            ], 500);
        }
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
     * Update consolidated delivery lines
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateConsolidatedLines(Request $request, $id)
    {
        $delivery = Delivery::find($id);

        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        if ($delivery->status === 'Completed') {
            return response()->json(['message' => 'Cannot modify completed delivery'], 400);
        }

        $validator = Validator::make($request->all(), [
            'delivery_lines' => 'required|array',
            'delivery_lines.*.line_id' => 'nullable|integer',
            'delivery_lines.*.so_line_id' => 'required|integer|exists:SOLine,line_id',
            'delivery_lines.*.delivered_quantity' => 'required|numeric|min:0.01',
            'delivery_lines.*.warehouse_id' => 'required|integer|exists:warehouses,warehouse_id',
            'delivery_lines.*.batch_number' => 'nullable|string|max:50',
            'delivery_lines.*.is_new' => 'boolean',
            'removed_lines' => 'array',
            'removed_lines.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Handle removed lines
            if (!empty($request->removed_lines)) {
                DeliveryLine::whereIn('line_id', $request->removed_lines)
                    ->where('delivery_id', $delivery->delivery_id)
                    ->delete();
            }

            // Handle updated/new lines
            foreach ($request->delivery_lines as $lineData) {
                if (!empty($lineData['is_new']) || empty($lineData['line_id'])) {
                    // Create new line
                    $soLine = SOLine::find($lineData['so_line_id']);
                    
                    DeliveryLine::create([
                        'delivery_id' => $delivery->delivery_id,
                        'so_line_id' => $lineData['so_line_id'],
                        'item_id' => $soLine->item_id,
                        'delivered_quantity' => $lineData['delivered_quantity'],
                        'warehouse_id' => $lineData['warehouse_id'],
                        'batch_number' => $lineData['batch_number']
                    ]);
                } else {
                    // Update existing line
                    DeliveryLine::where('line_id', $lineData['line_id'])
                        ->where('delivery_id', $delivery->delivery_id)
                        ->update([
                            'delivered_quantity' => $lineData['delivered_quantity'],
                            'warehouse_id' => $lineData['warehouse_id'],
                            'batch_number' => $lineData['batch_number']
                        ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Delivery lines updated successfully',
                'data' => $delivery->fresh(['deliveryLines.item', 'deliveryLines.warehouse'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update delivery lines',
                'error' => $e->getMessage()
            ], 500);
        }
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

                // Create stock transaction
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

    // Additional methods for line management and other operations...

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
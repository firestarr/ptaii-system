<?php
// app/Http/Controllers/Api/Sales/ConsolidatedDeliveryController.php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Delivery;
use App\Models\Sales\DeliveryLine;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SOLine;
use App\Models\Sales\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransaction;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ConsolidatedDeliveryController extends Controller
{
    /**
     * Get customers with outstanding sales orders
     *
     * @return \Illuminate\Http\Response
     */
    public function getCustomersWithOutstanding()
    {
        try {
            $customers = Customer::select([
                'customer_id',
                'customer_code', 
                'name',
                'email',
                'phone'
            ])
            ->whereHas('salesOrders', function($query) {
                $query->whereNotIn('status', ['Delivered', 'Closed', 'Cancelled'])
                      ->whereHas('salesOrderLines', function($lineQuery) {
                          $lineQuery->whereRaw('
                              quantity > COALESCE((
                                  SELECT SUM(delivered_quantity)
                                  FROM "DeliveryLine"
                                  INNER JOIN "Delivery" ON "DeliveryLine".delivery_id = "Delivery".delivery_id
                                  WHERE "DeliveryLine".so_line_id = "SOLine".line_id
                                  AND "Delivery".status IN (?, ?)
                              ), 0)
                          ', ['Completed', 'Delivered']);
                      });
            })
            ->withCount([
                'salesOrders as outstanding_sos' => function($query) {
                    $query->whereNotIn('status', ['Delivered', 'Closed', 'Cancelled'])
                          ->whereHas('salesOrderLines', function($lineQuery) {
                              $lineQuery->whereRaw('
                              quantity > COALESCE((
                                  SELECT SUM(delivered_quantity)
                                  FROM "DeliveryLine"
                                  INNER JOIN "Delivery" ON "DeliveryLine".delivery_id = "Delivery".delivery_id
                                  WHERE "DeliveryLine".so_line_id = "SOLine".line_id
                                  AND "Delivery".status IN (?, ?)
                                  ), 0)
                              ', ['Completed', 'Delivered']);
                          });
                }
            ])
            ->get()
            ->map(function($customer) {
                // Calculate total outstanding items using proper PostgreSQL syntax
                $outstandingItems = DB::table('SOLine')
                    ->join('SalesOrder', 'SOLine.so_id', '=', 'SalesOrder.so_id')
                    ->where('SalesOrder.customer_id', $customer->customer_id)
                    ->whereNotIn('SalesOrder.status', ['Delivered', 'Closed', 'Cancelled'])
                    ->whereRaw('
                        "SOLine".quantity > COALESCE((
                            SELECT SUM("DeliveryLine".delivered_quantity) 
                            FROM "DeliveryLine" 
                            INNER JOIN "Delivery" ON "DeliveryLine".delivery_id = "Delivery".delivery_id
                            WHERE "DeliveryLine".so_line_id = "SOLine".line_id 
                            AND "Delivery".status IN (?, ?)
                        ), 0)
                    ', ['Completed', 'Delivered'])
                    ->count();
                
                $customer->total_outstanding_items = $outstandingItems;
                return $customer;
            })
            ->filter(function($customer) {
                return $customer->outstanding_sos > 0;
            });

            return response()->json([
                'data' => $customers->values()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load customers with outstanding orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get outstanding sales orders for a specific customer
     *
     * @param int $customerId
     * @return \Illuminate\Http\Response
     */
    public function getCustomerOutstandingSOs($customerId)
    {
        try {
            $salesOrders = SalesOrder::with(['customer'])
                ->where('customer_id', $customerId)
                ->whereNotIn('status', ['Delivered', 'Closed', 'Cancelled'])
                ->whereHas('salesOrderLines', function($lineQuery) {
                    $lineQuery->whereRaw('
                        "SOLine".quantity > COALESCE((
                            SELECT SUM("DeliveryLine".delivered_quantity) 
                            FROM "DeliveryLine" 
                            INNER JOIN "Delivery" ON "DeliveryLine".delivery_id = "Delivery".delivery_id
                            WHERE "DeliveryLine".so_line_id = "SOLine".line_id 
                            AND "Delivery".status IN (?, ?)
                        ), 0)
                    ', ['Completed', 'Delivered']);
                })
                ->get()
                ->map(function($so) {
                    // Calculate outstanding items and quantity for each SO
                    $outstandingItems = SOLine::where('so_id', $so->so_id)
                        ->whereRaw('
                            "SOLine".quantity > COALESCE((
                                SELECT SUM("DeliveryLine".delivered_quantity) 
                                FROM "DeliveryLine" 
                                INNER JOIN "Delivery" ON "DeliveryLine".delivery_id = "Delivery".delivery_id
                                WHERE "DeliveryLine".so_line_id = "SOLine".line_id 
                                AND "Delivery".status IN (?, ?)
                            ), 0)
                        ', ['Completed', 'Delivered'])
                        ->get();

                    $so->outstanding_items_count = $outstandingItems->count();
                    $so->outstanding_quantity = $outstandingItems->sum(function($line) {
                        $deliveredQty = DeliveryLine::join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                            ->where('DeliveryLine.so_line_id', $line->line_id)
                            ->whereIn('Delivery.status', ['Completed', 'Delivered'])
                            ->sum('DeliveryLine.delivered_quantity');
                        
                        return $line->quantity - $deliveredQty;
                    });

                    return $so;
                });

            return response()->json([
                'data' => $salesOrders
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load customer sales orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all outstanding items for selected sales orders (for delivery creation)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getOutstandingItemsForSOs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'so_ids' => 'required|array|min:1',
            'so_ids.*' => 'required|exists:SalesOrder,so_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $outstandingItems = [];

            foreach ($request->so_ids as $soId) {
                $salesOrder = SalesOrder::with(['salesOrderLines.item.unitOfMeasure', 'customer'])->find($soId);

                if (!$salesOrder) {
                    continue;
                }

                foreach ($salesOrder->salesOrderLines as $line) {
                    $orderedQty = $line->quantity;
                    
                    // Calculate delivered quantity using PostgreSQL syntax
                    $deliveredQty = DB::table('DeliveryLine')
                        ->join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                        ->where('DeliveryLine.so_line_id', $line->line_id)
                        ->whereIn('Delivery.status', ['Completed', 'Delivered'])
                        ->sum('DeliveryLine.delivered_quantity') ?? 0;

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
                            'so_id' => $salesOrder->so_id,
                            'so_number' => $salesOrder->so_number,
                            'so_line_id' => $line->line_id,
                            'item_id' => $line->item_id,
                            'item_name' => $line->item->name,
                            'item_code' => $line->item->item_code,
                            'uom_id' => $line->uom_id,
                            'uom_name' => $line->unitOfMeasure ? $line->unitOfMeasure->name : '',
                            'ordered_quantity' => $orderedQty,
                            'delivered_quantity' => $deliveredQty,
                            'outstanding_quantity' => $outstandingQty,
                            'warehouse_stocks' => $warehouseStocks,
                            'customer_name' => $salesOrder->customer->name
                        ];
                    }
                }
            }

            return response()->json([
                'data' => $outstandingItems
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load outstanding items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a true consolidated delivery (single delivery record for multiple SOs)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function createConsolidatedDelivery(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'delivery_number' => 'required|unique:Delivery,delivery_number',
            'delivery_date' => 'required|date',
            'customer_id' => 'required|exists:Customer,customer_id',
            'shipping_method' => 'nullable|string|max:50',
            'tracking_number' => 'nullable|string|max:50',
            'is_consolidated' => 'required|boolean',
            'consolidated_so_ids' => 'required|array|min:2',
            'consolidated_so_ids.*' => 'required|exists:SalesOrder,so_id',
            'delivery_lines' => 'required|array|min:1',
            'delivery_lines.*.so_line_id' => 'required|exists:SOLine,line_id',
            'delivery_lines.*.delivered_quantity' => 'required|numeric|min:0.01',
            'delivery_lines.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'delivery_lines.*.batch_number' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get stock validation settings
        $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
        $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

        try {
            DB::beginTransaction();

            // Validate that all SOs belong to the same customer
            $customerIds = SalesOrder::whereIn('so_id', $request->consolidated_so_ids)
                ->distinct()
                ->pluck('customer_id');

            if ($customerIds->count() > 1 || !$customerIds->contains($request->customer_id)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'All sales orders must belong to the same customer'
                ], 400);
            }

            // Get SO numbers for notes
            $soNumbers = SalesOrder::whereIn('so_id', $request->consolidated_so_ids)
                ->pluck('so_number')
                ->toArray();

            // Create single consolidated delivery record
            $delivery = Delivery::create([
                'delivery_number' => $request->delivery_number,
                'delivery_date' => $request->delivery_date,
                'so_id' => null, // NULL for consolidated deliveries
                'customer_id' => $request->customer_id,
                'status' => 'Pending',
                'shipping_method' => $request->shipping_method,
                'tracking_number' => $request->tracking_number,
                'is_consolidated' => true,
                'consolidated_so_ids' => json_encode($request->consolidated_so_ids),
                'notes' => 'Consolidated delivery from ' . count($request->consolidated_so_ids) . ' sales orders: ' . implode(', ', $soNumbers)
            ]);

            // If the fields don't exist in the model, you can store them in a separate way
            // or add them to the database schema

            // Process each delivery line
            foreach ($request->delivery_lines as $lineData) {
                $soLine = SOLine::find($lineData['so_line_id']);

                // Validate SO line belongs to one of the consolidated SOs
                if (!in_array($soLine->so_id, $request->consolidated_so_ids)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'SO Line ' . $lineData['so_line_id'] . ' does not belong to selected sales orders'
                    ], 400);
                }

                // Calculate outstanding quantity using PostgreSQL syntax
                $previouslyDeliveredQty = DB::table('DeliveryLine')
                    ->join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                    ->where('DeliveryLine.so_line_id', $lineData['so_line_id'])
                    ->whereIn('Delivery.status', ['Completed', 'Delivered'])
                    ->sum('DeliveryLine.delivered_quantity') ?? 0;

                $outstandingQty = $soLine->quantity - $previouslyDeliveredQty;

                // Validate delivery quantity
                if ($lineData['delivered_quantity'] > $outstandingQty) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Delivered quantity exceeds outstanding quantity for SO Line ' . 
                                   $lineData['so_line_id'] . ' (Outstanding: ' . $outstandingQty . ')'
                    ], 400);
                }

                // Validate stock availability if required
                if ($enforceStockValidation) {
                    $itemStock = ItemStock::where('item_id', $soLine->item_id)
                        ->where('warehouse_id', $lineData['warehouse_id'])
                        ->first();

                    if (!$itemStock) {
                        $itemStock = ItemStock::create([
                            'item_id' => $soLine->item_id,
                            'warehouse_id' => $lineData['warehouse_id'],
                            'quantity' => 0,
                            'reserved_quantity' => 0
                        ]);
                    }

                    $availableQty = $itemStock->quantity - $itemStock->reserved_quantity;
                    if (!$allowNegativeStock && $availableQty < $lineData['delivered_quantity']) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Insufficient available stock for item in SO Line ' . 
                                       $lineData['so_line_id'] . ' (Available: ' . $availableQty . ')'
                        ], 400);
                    }

                    // Reserve stock
                    $itemStock->increment('reserved_quantity', $lineData['delivered_quantity']);
                }

                // Create delivery line
                DeliveryLine::create([
                    'delivery_id' => $delivery->delivery_id,
                    'so_line_id' => $lineData['so_line_id'],
                    'item_id' => $soLine->item_id,
                    'delivered_quantity' => $lineData['delivered_quantity'],
                    'warehouse_id' => $lineData['warehouse_id'],
                    'batch_number' => $lineData['batch_number'] ?? null
                ]);
            }

            // Update status of all consolidated sales orders
            SalesOrder::whereIn('so_id', $request->consolidated_so_ids)
                ->update(['status' => 'Delivering']);

            DB::commit();

            // Load the created delivery with relationships
            $delivery = $delivery->fresh()->load([
                'customer',
                'deliveryLines.item',
                'deliveryLines.warehouse',
                'deliveryLines.salesOrderLine.salesOrder'
            ]);

            // Add consolidated SO details
            $consolidatedSOs = SalesOrder::whereIn('so_id', $request->consolidated_so_ids)->get();

            return response()->json([
                'message' => 'Consolidated delivery created successfully',
                'delivery' => $delivery,
                'consolidated_sales_orders' => $consolidatedSOs,
                'consolidation_summary' => [
                    'total_sos' => count($request->consolidated_so_ids),
                    'so_numbers' => $soNumbers,
                    'total_items' => count($request->delivery_lines),
                    'estimated_time_saved' => max(0, (count($request->consolidated_so_ids) - 1) * 15)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create consolidated delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery details with consolidated information
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getConsolidatedDeliveryDetail($id)
    {
        try {
            $delivery = Delivery::with([
                'customer',
                'deliveryLines.item',
                'deliveryLines.warehouse',
                'deliveryLines.salesOrderLine.salesOrder'
            ])->find($id);

            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // For each delivery line, fetch warehouse stocks for the item
            foreach ($delivery->deliveryLines as $line) {
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
                $line->warehouse_stocks = $warehouseStocks;
            }

            // Get unique SOs from delivery lines
            $consolidatedSOIds = $delivery->deliveryLines
                ->pluck('salesOrderLine.so_id')
                ->unique()
                ->values()
                ->toArray();

            if (count($consolidatedSOIds) > 1) {
                $consolidatedSOs = SalesOrder::whereIn('so_id', $consolidatedSOIds)->get();
                
                $delivery->consolidated_sales_orders = $consolidatedSOs;
                $delivery->is_consolidated = true;
                
                // Add consolidation metrics
                $delivery->consolidation_metrics = [
                    'total_sos' => count($consolidatedSOIds),
                    'so_numbers' => $consolidatedSOs->pluck('so_number'),
                    'estimated_time_saved' => max(0, (count($consolidatedSOIds) - 1) * 15),
                    'estimated_cost_saved' => max(0, (count($consolidatedSOIds) - 1) * 10)
                ];
            }

            return response()->json(['data' => $delivery], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load delivery details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get consolidated delivery metrics for dashboard
     *
     * @return \Illuminate\Http\Response
     */
    public function getConsolidatedMetrics()
    {
        try {
            // Get metrics for last 30 days
            $thirtyDaysAgo = now()->subDays(30);

            $totalDeliveries = Delivery::where('created_at', '>=', $thirtyDaysAgo)->count();
            
            // Count deliveries that have lines from multiple SOs (consolidated)
            $consolidatedDeliveries = DB::table('Delivery')
                ->join('DeliveryLine', 'Delivery.delivery_id', '=', 'DeliveryLine.delivery_id')
                ->join('SOLine', 'DeliveryLine.so_line_id', '=', 'SOLine.line_id')
                ->where('Delivery.created_at', '>=', $thirtyDaysAgo)
                ->groupBy('Delivery.delivery_id')
                ->havingRaw('COUNT(DISTINCT "SOLine".so_id) > 1')
                ->count();

            $consolidationRate = $totalDeliveries > 0 ? 
                round(($consolidatedDeliveries / $totalDeliveries) * 100, 2) : 0;

            // Calculate other metrics
            $totalSOsProcessed = DB::table('Delivery')
                ->join('DeliveryLine', 'Delivery.delivery_id', '=', 'DeliveryLine.delivery_id')
                ->join('SOLine', 'DeliveryLine.so_line_id', '=', 'SOLine.line_id')
                ->where('Delivery.created_at', '>=', $thirtyDaysAgo)
                ->distinct('SOLine.so_id')
                ->count('SOLine.so_id');

            $avgSOsPerConsolidated = $consolidatedDeliveries > 0 ? 
                round($totalSOsProcessed / max($consolidatedDeliveries, 1), 1) : 0;

            $totalTimeSaved = max(0, ($totalSOsProcessed - $totalDeliveries) * 15);
            $totalCostSaved = max(0, ($totalSOsProcessed - $totalDeliveries) * 10);

            $metrics = [
                'total_deliveries' => $totalDeliveries,
                'consolidated_deliveries' => $consolidatedDeliveries,
                'consolidation_rate' => $consolidationRate,
                'total_sos_processed' => $totalSOsProcessed,
                'avg_sos_per_consolidated' => $avgSOsPerConsolidated,
                'total_time_saved_minutes' => $totalTimeSaved,
                'total_cost_saved' => $totalCostSaved,
                'efficiency_improvement' => min(50, $consolidationRate * 0.5)
            ];

            return response()->json(['metrics' => $metrics], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load consolidated metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete consolidated delivery and update stock
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function completeConsolidatedDelivery($id)
    {
        try {
            $delivery = Delivery::with('deliveryLines.salesOrderLine')->find($id);

            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            if ($delivery->status === 'Completed') {
                return response()->json(['message' => 'Delivery already completed'], 400);
            }

            DB::beginTransaction();

            // Get stock validation settings
            $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
            $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

            // Process each delivery line
            foreach ($delivery->deliveryLines as $line) {
                if ($enforceStockValidation) {
                    // Create stock transaction
                    $transaction = StockTransaction::create([
                        'item_id' => $line->item_id,
                        'warehouse_id' => $line->warehouse_id,
                        'dest_warehouse_id' => null,
                        'transaction_type' => StockTransaction::TYPE_ISSUE,
                        'move_type' => StockTransaction::MOVE_TYPE_OUT,
                        'quantity' => $line->delivered_quantity,
                        'transaction_date' => now(),
                        'reference_document' => 'consolidated_delivery',
                        'reference_number' => $delivery->delivery_number,
                        'origin' => "SO-{$line->salesOrderLine->so_id}",
                        'batch_id' => null,
                        'state' => StockTransaction::STATE_DRAFT,
                        'notes' => $allowNegativeStock ? 'Negative stock allowed' : null
                    ]);

                    // Auto-confirm to update stock
                    $transaction->markAsDone();

                    // Release reserved stock
                    $itemStock = ItemStock::where('item_id', $line->item_id)
                        ->where('warehouse_id', $line->warehouse_id)
                        ->first();

                    if ($itemStock) {
                        $itemStock->decrement('reserved_quantity', $line->delivered_quantity);
                    }
                }
            }

            // Update delivery status
            $delivery->update(['status' => 'Completed']);

            // Update consolidated sales orders status
            $consolidatedSOIds = $delivery->deliveryLines
                ->pluck('salesOrderLine.so_id')
                ->unique()
                ->values()
                ->toArray();
                
            foreach ($consolidatedSOIds as $soId) {
                $this->updateSalesOrderStatus($soId);
            }

            DB::commit();

            return response()->json([
                'message' => 'Consolidated delivery completed successfully',
                'delivery' => $delivery->fresh()
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to complete consolidated delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update sales order status based on delivery completion
     *
     * @param int $soId
     * @return void
     */
    private function updateSalesOrderStatus($soId)
    {
        $salesOrder = SalesOrder::with('salesOrderLines')->find($soId);

        if (!$salesOrder) {
            return;
        }

        // Check if all items are fully delivered
        $allDelivered = true;

        foreach ($salesOrder->salesOrderLines as $line) {
            $orderedQty = $line->quantity;
            
            $deliveredQty = DB::table('DeliveryLine')
                ->join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                ->where('DeliveryLine.so_line_id', $line->line_id)
                ->whereIn('Delivery.status', ['Completed', 'Delivered'])
                ->sum('DeliveryLine.delivered_quantity') ?? 0;

            if ($deliveredQty < $orderedQty) {
                $allDelivered = false;
                break;
            }
        }

        // Update SO status
        if ($allDelivered) {
            $salesOrder->update(['status' => 'Delivered']);
        } else {
            // Check if any items have been delivered
            $anyDelivered = DB::table('DeliveryLine')
                ->join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                ->join('SOLine', 'DeliveryLine.so_line_id', '=', 'SOLine.line_id')
                ->where('SOLine.so_id', $soId)
                ->whereIn('Delivery.status', ['Completed', 'Delivered'])
                ->exists();

            if ($anyDelivered && $salesOrder->status !== 'Delivered') {
                $salesOrder->update(['status' => 'Delivering']);
            }
        }
    }
    // Tambahkan methods berikut ke ConsolidatedDeliveryController.php

    /**
     * Update delivery lines for consolidated delivery
     *
     * @param \Illuminate\Http\Request $request
     * @param int $deliveryId
     * @return \Illuminate\Http\Response
     */
    public function updateDeliveryLines(Request $request, $deliveryId)
    {
        $validator = Validator::make($request->all(), [
            'delivery_lines' => 'sometimes|array',
            'delivery_lines.*.line_id' => 'nullable|exists:DeliveryLine,line_id',
            'delivery_lines.*.so_line_id' => 'required|exists:SOLine,line_id',
            'delivery_lines.*.delivered_quantity' => 'required|numeric|min:0.01',
            'delivery_lines.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'delivery_lines.*.batch_number' => 'nullable|string|max:50',
            'delivery_lines.*.is_new' => 'sometimes|boolean',
            'removed_lines' => 'sometimes|array',
            'removed_lines.*' => 'exists:DeliveryLine,line_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $delivery = Delivery::find($deliveryId);
            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // Check if delivery can be modified
            if ($delivery->status === 'Completed') {
                return response()->json(['message' => 'Cannot modify completed delivery'], 400);
            }

            // Get stock validation settings
            $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
            $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

            // Process removed lines first
            if ($request->has('removed_lines') && !empty($request->removed_lines)) {
                foreach ($request->removed_lines as $lineId) {
                    $line = DeliveryLine::find($lineId);
                    if ($line && $line->delivery_id == $deliveryId) {
                        // Release reserved stock if applicable
                        if ($enforceStockValidation) {
                            $itemStock = ItemStock::where('item_id', $line->item_id)
                                ->where('warehouse_id', $line->warehouse_id)
                                ->first();
                            
                            if ($itemStock) {
                                $itemStock->decrement('reserved_quantity', $line->delivered_quantity);
                            }
                        }
                        
                        $line->delete();
                    }
                }
            }

            // Process new/updated lines
            if ($request->has('delivery_lines')) {
                foreach ($request->delivery_lines as $lineData) {
                    $soLine = SOLine::find($lineData['so_line_id']);
                    
                    // Validate SO line belongs to consolidated SOs
                    $consolidatedSOIds = $this->getConsolidatedSOIds($delivery);
                    if (!in_array($soLine->so_id, $consolidatedSOIds)) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'SO Line does not belong to consolidated sales orders'
                        ], 400);
                    }

                    // Calculate outstanding quantity
                    $existingDeliveredQty = DB::table('DeliveryLine')
                        ->join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                        ->where('DeliveryLine.so_line_id', $lineData['so_line_id'])
                        ->where('Delivery.delivery_id', '!=', $deliveryId) // Exclude current delivery
                        ->whereIn('Delivery.status', ['Completed', 'Delivered'])
                        ->sum('DeliveryLine.delivered_quantity') ?? 0;

                    $outstandingQty = $soLine->quantity - $existingDeliveredQty;

                    // Validate delivery quantity
                    if ($lineData['delivered_quantity'] > $outstandingQty) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Delivered quantity exceeds outstanding quantity for SO Line ' . 
                                    $lineData['so_line_id'] . ' (Outstanding: ' . $outstandingQty . ')'
                        ], 400);
                    }

                    // Validate stock availability
                    if ($enforceStockValidation) {
                        $itemStock = ItemStock::where('item_id', $soLine->item_id)
                            ->where('warehouse_id', $lineData['warehouse_id'])
                            ->first();

                        if (!$itemStock) {
                            $itemStock = ItemStock::create([
                                'item_id' => $soLine->item_id,
                                'warehouse_id' => $lineData['warehouse_id'],
                                'quantity' => 0,
                                'reserved_quantity' => 0
                            ]);
                        }

                        $availableQty = $itemStock->quantity - $itemStock->reserved_quantity;
                        if (!$allowNegativeStock && $availableQty < $lineData['delivered_quantity']) {
                            DB::rollBack();
                            return response()->json([
                                'message' => 'Insufficient available stock for item in SO Line ' . 
                                        $lineData['so_line_id'] . ' (Available: ' . $availableQty . ')'
                            ], 400);
                        }
                    }

                    // Create or update delivery line
                    if (!empty($lineData['line_id']) && !$lineData['is_new']) {
                        // Update existing line
                        $deliveryLine = DeliveryLine::find($lineData['line_id']);
                        if ($deliveryLine && $deliveryLine->delivery_id == $deliveryId) {
                            // Release old reservation and create new one
                            if ($enforceStockValidation) {
                                $oldStock = ItemStock::where('item_id', $deliveryLine->item_id)
                                    ->where('warehouse_id', $deliveryLine->warehouse_id)
                                    ->first();
                                if ($oldStock) {
                                    $oldStock->decrement('reserved_quantity', $deliveryLine->delivered_quantity);
                                }

                                $newStock = ItemStock::where('item_id', $soLine->item_id)
                                    ->where('warehouse_id', $lineData['warehouse_id'])
                                    ->first();
                                if ($newStock) {
                                    $newStock->increment('reserved_quantity', $lineData['delivered_quantity']);
                                }
                            }

                            $deliveryLine->update([
                                'so_line_id' => $lineData['so_line_id'],
                                'item_id' => $soLine->item_id,
                                'delivered_quantity' => $lineData['delivered_quantity'],
                                'warehouse_id' => $lineData['warehouse_id'],
                                'batch_number' => $lineData['batch_number'] ?? null
                            ]);
                        }
                    } else {
                        // Create new line
                        if ($enforceStockValidation) {
                            $itemStock = ItemStock::where('item_id', $soLine->item_id)
                                ->where('warehouse_id', $lineData['warehouse_id'])
                                ->first();
                            if ($itemStock) {
                                $itemStock->increment('reserved_quantity', $lineData['delivered_quantity']);
                            }
                        }

                        DeliveryLine::create([
                            'delivery_id' => $deliveryId,
                            'so_line_id' => $lineData['so_line_id'],
                            'item_id' => $soLine->item_id,
                            'delivered_quantity' => $lineData['delivered_quantity'],
                            'warehouse_id' => $lineData['warehouse_id'],
                            'batch_number' => $lineData['batch_number'] ?? null
                        ]);
                    }
                }
            }

            DB::commit();

            // Reload delivery with updated lines
            $updatedDelivery = Delivery::with([
                'customer',
                'deliveryLines.item',
                'deliveryLines.warehouse',
                'deliveryLines.salesOrderLine.salesOrder'
            ])->find($deliveryId);

            return response()->json([
                'message' => 'Delivery lines updated successfully',
                'delivery' => $updatedDelivery
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
     * Get available items for adding to consolidated delivery
     *
     * @param int $deliveryId
     * @return \Illuminate\Http\Response
     */
    public function getAvailableItemsForDelivery($deliveryId)
    {
        try {
            $delivery = Delivery::find($deliveryId);
            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            // Get consolidated SO IDs
            $consolidatedSOIds = $this->getConsolidatedSOIds($delivery);
            
            if (empty($consolidatedSOIds)) {
                return response()->json(['data' => []], 200);
            }

            // Get current delivery line SO line IDs
            $existingSOLineIds = $delivery->deliveryLines->pluck('so_line_id')->toArray();

            // Get all outstanding items from consolidated SOs
            $outstandingItems = [];

            foreach ($consolidatedSOIds as $soId) {
                $salesOrder = SalesOrder::with(['salesOrderLines.item.unitOfMeasure', 'customer'])->find($soId);

                if (!$salesOrder) continue;

                foreach ($salesOrder->salesOrderLines as $line) {
                    // Skip if already in delivery
                    if (in_array($line->line_id, $existingSOLineIds)) {
                        continue;
                    }

                    $orderedQty = $line->quantity;
                    
                    // Calculate delivered quantity from other deliveries
                    $deliveredQty = DB::table('DeliveryLine')
                        ->join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                        ->where('DeliveryLine.so_line_id', $line->line_id)
                        ->where('Delivery.delivery_id', '!=', $deliveryId) // Exclude current delivery
                        ->whereIn('Delivery.status', ['Completed', 'Delivered', 'Pending'])
                        ->sum('DeliveryLine.delivered_quantity') ?? 0;

                    $outstandingQty = $orderedQty - $deliveredQty;

                    if ($outstandingQty > 0) {
                        // Get warehouse inventory
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
                            'so_id' => $salesOrder->so_id,
                            'so_number' => $salesOrder->so_number,
                            'so_line_id' => $line->line_id,
                            'item_id' => $line->item_id,
                            'item_name' => $line->item->name,
                            'item_code' => $line->item->item_code,
                            'uom_id' => $line->uom_id,
                            'uom_name' => $line->unitOfMeasure ? $line->unitOfMeasure->name : '',
                            'ordered_quantity' => $orderedQty,
                            'delivered_quantity' => $deliveredQty,
                            'outstanding_quantity' => $outstandingQty,
                            'warehouse_stocks' => $warehouseStocks,
                            'customer_name' => $salesOrder->customer->name
                        ];
                    }
                }
            }

            return response()->json(['data' => $outstandingItems], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load available items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add new items to consolidated delivery
     *
     * @param \Illuminate\Http\Request $request
     * @param int $deliveryId
     * @return \Illuminate\Http\Response
     */
    public function addItemsToDelivery(Request $request, $deliveryId)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.so_line_id' => 'required|exists:SOLine,line_id',
            'items.*.delivered_quantity' => 'required|numeric|min:0.01',
            'items.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'items.*.batch_number' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $delivery = Delivery::find($deliveryId);
            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            if ($delivery->status === 'Completed') {
                return response()->json(['message' => 'Cannot modify completed delivery'], 400);
            }

            // Get stock validation settings
            $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
            $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

            $consolidatedSOIds = $this->getConsolidatedSOIds($delivery);
            $addedItems = [];

            foreach ($request->items as $itemData) {
                $soLine = SOLine::find($itemData['so_line_id']);
                
                // Validate SO line belongs to consolidated SOs
                if (!in_array($soLine->so_id, $consolidatedSOIds)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'SO Line ' . $itemData['so_line_id'] . ' does not belong to consolidated sales orders'
                    ], 400);
                }

                // Check if item already exists in delivery
                $existingLine = DeliveryLine::where('delivery_id', $deliveryId)
                    ->where('so_line_id', $itemData['so_line_id'])
                    ->first();

                if ($existingLine) {
                    continue; // Skip if already exists
                }

                // Calculate outstanding quantity
                $existingDeliveredQty = DB::table('DeliveryLine')
                    ->join('Delivery', 'DeliveryLine.delivery_id', '=', 'Delivery.delivery_id')
                    ->where('DeliveryLine.so_line_id', $itemData['so_line_id'])
                    ->where('Delivery.delivery_id', '!=', $deliveryId)
                    ->whereIn('Delivery.status', ['Completed', 'Delivered', 'Pending'])
                    ->sum('DeliveryLine.delivered_quantity') ?? 0;

                $outstandingQty = $soLine->quantity - $existingDeliveredQty;

                // Validate delivery quantity
                if ($itemData['delivered_quantity'] > $outstandingQty) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Delivered quantity exceeds outstanding quantity for SO Line ' . 
                                $itemData['so_line_id'] . ' (Outstanding: ' . $outstandingQty . ')'
                    ], 400);
                }

                // Validate stock availability
                if ($enforceStockValidation) {
                    $itemStock = ItemStock::where('item_id', $soLine->item_id)
                        ->where('warehouse_id', $itemData['warehouse_id'])
                        ->first();

                    if (!$itemStock) {
                        $itemStock = ItemStock::create([
                            'item_id' => $soLine->item_id,
                            'warehouse_id' => $itemData['warehouse_id'],
                            'quantity' => 0,
                            'reserved_quantity' => 0
                        ]);
                    }

                    $availableQty = $itemStock->quantity - $itemStock->reserved_quantity;
                    if (!$allowNegativeStock && $availableQty < $itemData['delivered_quantity']) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Insufficient available stock for item in SO Line ' . 
                                    $itemData['so_line_id'] . ' (Available: ' . $availableQty . ')'
                        ], 400);
                    }

                    // Reserve stock
                    $itemStock->increment('reserved_quantity', $itemData['delivered_quantity']);
                }

                // Create delivery line
                $deliveryLine = DeliveryLine::create([
                    'delivery_id' => $deliveryId,
                    'so_line_id' => $itemData['so_line_id'],
                    'item_id' => $soLine->item_id,
                    'delivered_quantity' => $itemData['delivered_quantity'],
                    'warehouse_id' => $itemData['warehouse_id'],
                    'batch_number' => $itemData['batch_number'] ?? null
                ]);

                $addedItems[] = $deliveryLine;
            }

            DB::commit();

            return response()->json([
                'message' => count($addedItems) . ' item(s) added to delivery successfully',
                'added_items' => $addedItems
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add items to delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update consolidated delivery header information
     *
     * @param \Illuminate\Http\Request $request
     * @param int $deliveryId
     * @return \Illuminate\Http\Response
     */
    public function updateConsolidatedDeliveryHeader(Request $request, $deliveryId)
    {
        $validator = Validator::make($request->all(), [
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

            $delivery = Delivery::find($deliveryId);
            if (!$delivery) {
                return response()->json(['message' => 'Delivery not found'], 404);
            }

            $oldStatus = $delivery->status;

            // Update delivery header
            $delivery->update([
                'delivery_date' => $request->delivery_date,
                'shipping_method' => $request->shipping_method,
                'tracking_number' => $request->tracking_number,
                'status' => $request->status
            ]);

            // If status changed to Completed, process completion
            if ($request->status === 'Completed' && $oldStatus !== 'Completed') {
                $this->processConsolidatedDeliveryCompletion($delivery);
            }

            DB::commit();

            return response()->json([
                'message' => 'Delivery header updated successfully',
                'delivery' => $delivery->fresh()
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update delivery header',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process consolidated delivery completion
     *
     * @param \App\Models\Sales\Delivery $delivery
     * @return void
     * @throws \Exception
     */
    private function processConsolidatedDeliveryCompletion($delivery)
    {
        // Get stock validation settings
        $enforceStockValidation = SystemSetting::getValue('inventory_enforce_stock_validation', 'true') === 'true';
        $allowNegativeStock = SystemSetting::getValue('inventory_allow_negative_stock', 'false') === 'true';

        // Load delivery lines if not already loaded
        if (!$delivery->relationLoaded('deliveryLines')) {
            $delivery->load('deliveryLines.salesOrderLine');
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
                    'move_type' => StockTransaction::MOVE_TYPE_OUT,
                    'quantity' => $line->delivered_quantity,
                    'transaction_date' => now(),
                    'reference_document' => 'consolidated_delivery',
                    'reference_number' => $delivery->delivery_number,
                    'origin' => "Consolidated-SO-{$line->salesOrderLine->so_id}",
                    'batch_id' => null,
                    'state' => StockTransaction::STATE_DRAFT,
                    'notes' => $allowNegativeStock ? 'Negative stock allowed' : null
                ]);

                // Auto-confirm to update stock
                $transaction->markAsDone();

                // Release reserved stock
                $itemStock->fresh()->decrement('reserved_quantity', $line->delivered_quantity);
            }
        }

        // Update consolidated sales orders status
        $consolidatedSOIds = $this->getConsolidatedSOIds($delivery);
        foreach ($consolidatedSOIds as $soId) {
            $this->updateSalesOrderStatus($soId);
        }
    }

    /**
     * Get consolidated SO IDs from delivery
     *
     * @param \App\Models\Sales\Delivery $delivery
     * @return array
     */
    private function getConsolidatedSOIds($delivery)
    {
        // Check if consolidated_so_ids field exists and has data
        if (!empty($delivery->consolidated_so_ids)) {
            if (is_array($delivery->consolidated_so_ids)) {
                return $delivery->consolidated_so_ids;
            } elseif (is_string($delivery->consolidated_so_ids)) {
                $decoded = json_decode($delivery->consolidated_so_ids, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        // Fallback: get unique SO IDs from delivery lines
        if ($delivery->relationLoaded('deliveryLines')) {
            $delivery->load('deliveryLines.salesOrderLine');
            return $delivery->deliveryLines->map(function ($line) {
                return $line->salesOrderLine ? $line->salesOrderLine->so_id : null;
            })->filter()->unique()->values()->all();
        }

        // If no delivery lines loaded, query them
        $soIds = DB::table('DeliveryLine')
            ->join('SOLine', 'DeliveryLine.so_line_id', '=', 'SOLine.line_id')
            ->where('DeliveryLine.delivery_id', $delivery->delivery_id)
            ->distinct()
            ->pluck('SOLine.so_id')
            ->toArray();

        return $soIds;
    }
}
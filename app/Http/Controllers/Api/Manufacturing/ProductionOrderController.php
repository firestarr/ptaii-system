<?php

namespace App\Http\Controllers\Api\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionConsumption;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\StockTransaction;

class ProductionOrderController extends Controller
{
    // Define warehouse IDs as class constants for maintainability
    const FINISHED_GOODS_WAREHOUSE_ID = 2;
    const RAW_MATERIALS_WAREHOUSE_ID = 1;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ProductionOrder::with(['workOrder.item']);
        if ($request->has('wo_id')) {
            $query->where('wo_id', $request->wo_id);
        }
        $productionOrders = $query->get();
        return response()->json(['data' => $productionOrders]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wo_id' => 'required|integer|exists:work_orders,wo_id',
            'production_number' => 'required|string|max:50|unique:production_orders,production_number',
            'production_date' => 'required|date',
            'planned_quantity' => 'required|numeric',
            'actual_quantity' => 'sometimes|numeric',
            'status' => 'required|string|max:50',
            'consumptions' => 'sometimes|array',
            'consumptions.*.item_id' => 'required|integer|exists:items,item_id',
            'consumptions.*.planned_quantity' => 'required|numeric',
            'consumptions.*.actual_quantity' => 'sometimes|nullable|numeric',
            'consumptions.*.warehouse_id' => 'required|integer|exists:warehouses,warehouse_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Additional validation: planned_quantity must not exceed work order planned_quantity considering existing production orders
        $workOrder = WorkOrder::find($request->wo_id);
        if (!$workOrder) {
            return response()->json(['errors' => ['wo_id' => ['Work order not found']]], 422);
        }
        $existingPlannedQtySum = ProductionOrder::where('wo_id', $request->wo_id)->sum('planned_quantity');
        $totalPlannedQty = $existingPlannedQtySum + $request->planned_quantity;
        if ($totalPlannedQty > $workOrder->planned_quantity) {
            return response()->json(['errors' => ['planned_quantity' => ['Planned quantity exceeds the remaining quantity of the work order. Remaining quantity: ' . ($workOrder->planned_quantity - $existingPlannedQtySum)]]], 422);
        }

        DB::beginTransaction();
        try {
            $productionOrder = ProductionOrder::create([
                'wo_id' => $request->wo_id,
                'production_number' => $request->production_number,
                'production_date' => $request->production_date,
                'planned_quantity' => $request->planned_quantity,
                'actual_quantity' => $request->actual_quantity ?? 0,
                'status' => $request->status,
            ]);

            // Create consumption entries if provided and not empty
            if ($request->has('consumptions') && !empty($request->consumptions)) {
                foreach ($request->consumptions as $consumption) {
                    ProductionConsumption::create([
                        'production_id' => $productionOrder->production_id,
                        'item_id' => $consumption['item_id'],
                        'planned_quantity' => $consumption['planned_quantity'],
                        'actual_quantity' => $consumption['actual_quantity'] ?? 0,
                        'variance' => isset($consumption['actual_quantity']) 
                            ? $consumption['planned_quantity'] - $consumption['actual_quantity'] 
                            : 0,
                        'warehouse_id' => $consumption['warehouse_id'],
                    ]);
                }
            } else {
                // Auto-generate consumption entries from BOM if no consumptions provided or empty
                $workOrder = WorkOrder::with('bom.bomLines')->find($request->wo_id);
                if ($workOrder && $workOrder->bom) {
                    foreach ($workOrder->bom->bomLines as $bomLine) {
                        // Calculate planned quantity considering yield and shrinkage if applicable
                        $baseQty = $bomLine->quantity * ($request->planned_quantity / $workOrder->bom->standard_quantity);
                        $plannedQty = $baseQty;

                        if ($bomLine->is_yield_based && $bomLine->yield_ratio > 0) {
                            // Adjust base quantity by dividing by yield ratio
                            $plannedQty = $baseQty / $bomLine->yield_ratio;

                            // Apply shrinkage factor if defined
                            if ($bomLine->shrinkage_factor > 0) {
                                $plannedQty = $plannedQty / (1 - $bomLine->shrinkage_factor);
                            }
                        }

                        // Round up planned quantity to avoid decimals
                        $plannedQty = ceil($plannedQty);

                        ProductionConsumption::create([
                            'production_id' => $productionOrder->production_id,
                            'item_id' => $bomLine->item_id,
                            'planned_quantity' => $plannedQty,
                            'actual_quantity' => 0,
                            'variance' => $plannedQty,
                            'warehouse_id' => $request->default_warehouse_id ?? 1, // Default warehouse if provided
                        ]);
                    }
                }
            }

            DB::commit();
            
            return response()->json([
                'data' => $productionOrder->load(['workOrder', 'productionConsumptions.item']),
                'message' => 'Production order created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create production order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $productionOrder = ProductionOrder::with([
            'workOrder.item',
            'productionConsumptions.item',
            'productionConsumptions.warehouse',
        ])->find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }
        
        return response()->json(['data' => $productionOrder]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $productionOrder = ProductionOrder::find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'wo_id' => 'sometimes|required|integer|exists:work_orders,wo_id',
            'production_number' => 'sometimes|required|string|max:50|unique:production_orders,production_number,' . $id . ',production_id',
            'production_date' => 'sometimes|required|date',
            'planned_quantity' => 'sometimes|required|numeric',
            'actual_quantity' => 'sometimes|numeric',
            'status' => 'sometimes|required|string|max:50',
            'consumptions' => 'sometimes|array',
            'consumptions.*.item_id' => 'required_with:consumptions|integer|exists:items,item_id',
            'consumptions.*.planned_quantity' => 'required_with:consumptions|numeric',
            'consumptions.*.actual_quantity' => 'sometimes|nullable|numeric',
            'consumptions.*.warehouse_id' => 'required_with:consumptions|integer|exists:warehouses,warehouse_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Additional validation: planned_quantity must not exceed work order planned_quantity considering existing production orders
        if ($request->has('planned_quantity')) {
            $workOrder = WorkOrder::find($productionOrder->wo_id);
            if (!$workOrder) {
                return response()->json(['errors' => ['wo_id' => ['Work order not found']]], 422);
            }
            $existingPlannedQtySum = ProductionOrder::where('wo_id', $productionOrder->wo_id)
                ->where('production_id', '!=', $productionOrder->production_id)
                ->sum('planned_quantity');
            $totalPlannedQty = $existingPlannedQtySum + $request->planned_quantity;
            if ($totalPlannedQty > $workOrder->planned_quantity) {
                return response()->json(['errors' => ['planned_quantity' => ['Planned quantity exceeds the remaining quantity of the work order. Remaining quantity: ' . ($workOrder->planned_quantity - $existingPlannedQtySum)]]], 422);
            }
        }

        DB::beginTransaction();
        try {
            $productionOrder->update($request->all());

            // Update or recreate consumption entries if provided
            if ($request->has('consumptions') && !empty($request->consumptions)) {
                // Delete existing consumptions
                $productionOrder->productionConsumptions()->delete();

                // Create new consumption entries from request
                foreach ($request->consumptions as $consumption) {
                    ProductionConsumption::create([
                        'production_id' => $productionOrder->production_id,
                        'item_id' => $consumption['item_id'],
                        'planned_quantity' => $consumption['planned_quantity'],
                        'actual_quantity' => $consumption['actual_quantity'] ?? 0,
                        'variance' => isset($consumption['actual_quantity']) 
                            ? $consumption['planned_quantity'] - $consumption['actual_quantity'] 
                            : 0,
                        'warehouse_id' => $consumption['warehouse_id'],
                    ]);
                }
            } else {
                // Auto-generate consumption entries from BOM if no consumptions provided or empty
                $workOrder = WorkOrder::with('bom.bomLines')->find($productionOrder->wo_id);
                if ($workOrder && $workOrder->bom) {
                    // Delete existing consumptions
                    $productionOrder->productionConsumptions()->delete();

                    foreach ($workOrder->bom->bomLines as $bomLine) {
                        // Calculate planned quantity considering yield and shrinkage if applicable
                        $baseQty = $bomLine->quantity * ($productionOrder->planned_quantity / $workOrder->bom->standard_quantity);
                        $plannedQty = $baseQty;

                        if ($bomLine->is_yield_based && $bomLine->yield_ratio > 0) {
                            // Adjust base quantity by dividing by yield ratio
                            $plannedQty = $baseQty / $bomLine->yield_ratio;

                            // Apply shrinkage factor if defined
                            if ($bomLine->shrinkage_factor > 0) {
                                $plannedQty = $plannedQty / (1 - $bomLine->shrinkage_factor);
                            }
                        }

                        // Round up planned quantity to avoid decimals
                        $plannedQty = ceil($plannedQty);

                        ProductionConsumption::create([
                            'production_id' => $productionOrder->production_id,
                            'item_id' => $bomLine->item_id,
                            'planned_quantity' => $plannedQty,
                            'actual_quantity' => 0,
                            'variance' => $plannedQty,
                            'warehouse_id' => $request->default_warehouse_id ?? 1, // Default warehouse if provided
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'data' => $productionOrder->load(['workOrder', 'productionConsumptions.item']),
                'message' => 'Production order updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update production order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $productionOrder = ProductionOrder::find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }

        DB::beginTransaction();
        try {
            // Delete production consumptions first
            $productionOrder->productionConsumptions()->delete();
            
            // Then delete the production order
            $productionOrder->delete();
            
            DB::commit();
            return response()->json(['message' => 'Production order deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete production order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Complete the production order and update inventory.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function complete(Request $request, $id)
    {
        $productionOrder = ProductionOrder::with(['workOrder.item', 'productionConsumptions.item'])
            ->find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }
        
        // Validate that we have an actual quantity
        $validator = Validator::make($request->all(), [
            'actual_quantity' => 'required|numeric|min:0',
            'consumptions' => 'sometimes|array',
            'consumptions.*.consumption_id' => 'required|integer',
            'consumptions.*.actual_quantity' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Validate stock availability before processing
        $stockErrors = [];
        $consumptionsMap = [];
        
        if ($request->has('consumptions') && is_array($request->consumptions)) {
            foreach ($request->consumptions as $consumption) {
                if (isset($consumption['consumption_id'])) {
                    $consumptionsMap[$consumption['consumption_id']] = $consumption['actual_quantity'];
                }
            }
        }
        
        // Check stock availability for each consumption
        foreach ($productionOrder->productionConsumptions as $consumption) {
            if (!isset($consumptionsMap[$consumption->consumption_id])) {
                continue;
            }
            
            $actualConsumption = $consumptionsMap[$consumption->consumption_id];
            
            // Get current stock for the item in the specified warehouse
            $itemStock = ItemStock::where('item_id', $consumption->item_id)
                ->where('warehouse_id', $consumption->warehouse_id)
                ->first();
            
            $availableStock = $itemStock ? $itemStock->quantity : 0;
            
            if ($actualConsumption > $availableStock) {
                $item = Item::find($consumption->item_id);
                $stockErrors[] = sprintf(
                    'Insufficient stock for %s. Required: %s, Available: %s',
                    $item->name,
                    $actualConsumption,
                    $availableStock
                );
            }
        }
        
        // Return validation errors if stock is insufficient
        if (!empty($stockErrors)) {
            return response()->json([
                'message' => 'Stock validation failed',
                'errors' => ['stock' => $stockErrors]
            ], 422);
        }
        
        DB::beginTransaction();
        try {
            // Update production order status
            $productionOrder->actual_quantity = $request->actual_quantity;
            $productionOrder->status = 'Completed';
            $productionOrder->save();
            
            // Get the finished product and update inventory
            $finishedItem = $productionOrder->workOrder->item;
            $finishedGoodsWarehouseId = self::FINISHED_GOODS_WAREHOUSE_ID;
            
            // Create stock transaction for the finished product
            StockTransaction::create([
                'item_id' => $finishedItem->item_id,
                'warehouse_id' => $finishedGoodsWarehouseId,
                'transaction_type' => 'receive',
                'quantity' => $request->actual_quantity,
                'transaction_date' => now(),
                'reference_document' => 'production_order',
                'reference_number' => $productionOrder->production_number
            ]);
            
            // Update item's total stock
            $finishedItem->current_stock += $request->actual_quantity;
            $finishedItem->save();
            
            // Update ItemStock for finished product
            $finishedItemStock = ItemStock::firstOrNew([
                'item_id' => $finishedItem->item_id,
                'warehouse_id' => $finishedGoodsWarehouseId
            ]);
            
            $finishedItemStock->quantity = ($finishedItemStock->quantity ?? 0) + $request->actual_quantity;
            $finishedItemStock->save();
            
            // Process material consumptions
            $rawMaterialsWarehouseId = self::RAW_MATERIALS_WAREHOUSE_ID;
            
            foreach ($productionOrder->productionConsumptions as $consumption) {
                // Skip if no actual quantity was consumed
                if (!isset($consumptionsMap[$consumption->consumption_id])) {
                    continue;
                }
                
                $actualConsumption = $consumptionsMap[$consumption->consumption_id];
                
                // Update consumption record
                $consumption->actual_quantity = $actualConsumption;
                $consumption->variance = $consumption->planned_quantity - $actualConsumption;
                $consumption->warehouse_id = $consumption->warehouse_id ?: $rawMaterialsWarehouseId;
                $consumption->save();
                
                // Create stock transaction for consumed material (negative quantity)
                StockTransaction::create([
                    'item_id' => $consumption->item_id,
                    'warehouse_id' => $consumption->warehouse_id,
                    'transaction_type' => 'issue',
                    'quantity' => -$actualConsumption,
                    'transaction_date' => now(),
                    'reference_document' => 'production_order',
                    'reference_number' => $productionOrder->production_number
                ]);
                
                // Update material item's total stock
                $materialItem = $consumption->item;
                $materialItem->current_stock -= $actualConsumption;
                $materialItem->save();
                
                // Update ItemStock for material
                $materialItemStock = ItemStock::where('item_id', $consumption->item_id)
                    ->where('warehouse_id', $consumption->warehouse_id)
                    ->first();
                
                if ($materialItemStock) {
                    $materialItemStock->quantity = max(0, $materialItemStock->quantity - $actualConsumption);
                    $materialItemStock->save();
                }
            }
            
            // Update work order progress if needed
            $workOrder = $productionOrder->workOrder;
            $totalProduced = $workOrder->productionOrders()
                ->where('status', 'Completed')
                ->sum('actual_quantity');
                
            if ($totalProduced >= $workOrder->planned_quantity) {
                $workOrder->status = 'Completed';
            } else {
                $workOrder->status = 'In Progress';
            }
            $workOrder->save();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Production order completed successfully',
                'data' => $productionOrder->fresh(['workOrder', 'productionConsumptions.item'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to complete production order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
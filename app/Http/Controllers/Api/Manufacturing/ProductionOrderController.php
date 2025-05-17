<?php

namespace App\Http\Controllers\Api\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionConsumption;
use App\Models\Item;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductionOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $productionOrders = ProductionOrder::with(['workOrder.item'])->get();
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
}
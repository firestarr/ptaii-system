<?php

namespace App\Http\Controllers\Api\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionConsumption;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ProductionConsumptionController extends Controller
{
    /**
     * Display a listing of the resource for a specific production order.
     *
     * @param  int  $productionId
     * @return \Illuminate\Http\Response
     */
    public function index($productionId)
    {
        $productionOrder = ProductionOrder::find($productionId);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }
        
$consumptions = ProductionConsumption::with(['item', 'warehouse'])
            ->where('production_id', $productionId)
            ->get();
            
        return response()->json(['data' => $consumptions]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $productionId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $productionId)
    {
        $productionOrder = ProductionOrder::find($productionId);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }

$validator = Validator::make($request->all(), [
            'item_id' => 'required|integer|exists:items,item_id',
            'planned_quantity' => 'required|numeric',
            'actual_quantity' => 'sometimes|nullable|numeric',
            'warehouse_id' => 'required|integer|exists:warehouses,warehouse_id',
            //'location_id' => 'sometimes|nullable|integer|exists:warehouse_locations,location_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plannedQty = $request->planned_quantity;
        $actualQty = $request->actual_quantity ?? 0;

        $consumption = new ProductionConsumption();
        $consumption->production_id = $productionId;
        $consumption->item_id = $request->item_id;
        $consumption->planned_quantity = $plannedQty;
        $consumption->actual_quantity = $actualQty;
        $consumption->variance = $plannedQty - $actualQty;
        $consumption->warehouse_id = $request->warehouse_id;
        //$consumption->location_id = $request->location_id;
        $consumption->save();

return response()->json([
            'data' => $consumption->load(['item', 'warehouse']), 
            'message' => 'Production consumption created successfully'
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $productionId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($productionId, $id)
    {
$consumption = ProductionConsumption::with(['item', 'warehouse'])
            ->where('production_id', $productionId)
            ->where('consumption_id', $id)
            ->first();
        
        if (!$consumption) {
            return response()->json(['message' => 'Production consumption not found'], 404);
        }
        
        return response()->json(['data' => $consumption]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $productionId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $productionId, $id)
    {
        $consumption = ProductionConsumption::where('production_id', $productionId)
            ->where('consumption_id', $id)
            ->first();
        
        if (!$consumption) {
            return response()->json(['message' => 'Production consumption not found'], 404);
        }

$validator = Validator::make($request->all(), [
            'item_id' => 'sometimes|required|integer|exists:items,item_id',
            'planned_quantity' => 'sometimes|required|numeric',
            'actual_quantity' => 'sometimes|nullable|numeric',
            'warehouse_id' => 'sometimes|required|integer|exists:warehouses,warehouse_id',
            //'location_id' => 'sometimes|nullable|integer|exists:warehouse_locations,location_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update the consumption with the provided data
        $consumption->fill($request->except('variance'));
        
        // Calculate variance if needed
        if ($request->has('planned_quantity') || $request->has('actual_quantity')) {
            $plannedQty = $request->has('planned_quantity') ? $request->planned_quantity : $consumption->planned_quantity;
            $actualQty = $request->has('actual_quantity') ? $request->actual_quantity : $consumption->actual_quantity;
            $consumption->variance = $plannedQty - $actualQty;
        }
        
        $consumption->save();
        
return response()->json([
            'data' => $consumption->load(['item', 'warehouse']), 
            'message' => 'Production consumption updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $productionId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($productionId, $id)
    {
        $consumption = ProductionConsumption::where('production_id', $productionId)
            ->where('consumption_id', $id)
            ->first();
        
        if (!$consumption) {
            return response()->json(['message' => 'Production consumption not found'], 404);
        }

        $consumption->delete();
        
        return response()->json(['message' => 'Production consumption deleted successfully']);
    }
}
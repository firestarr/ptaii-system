<?php

namespace App\Http\Controllers\Api\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = WorkOrder::with(['item', 'bom', 'routing']);
        if ($request->has('exclude_status')) {
            $query->where('status', '!=', $request->exclude_status);
        }
        $workOrders = $query->get();
        return response()->json(['data' => $workOrders]);
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
            'wo_number' => 'required|string|max:50|unique:work_orders,wo_number',
            'wo_date' => 'required|date',
            'item_id' => 'required|integer|exists:items,item_id',
            'bom_id' => 'required|integer|exists:boms,bom_id',
            'routing_id' => 'required|integer|exists:routings,routing_id',
            'planned_quantity' => 'required|numeric',
            'planned_start_date' => 'required|date',
            'planned_end_date' => 'required|date|after_or_equal:planned_start_date',
            'status' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $workOrder = WorkOrder::create($request->all());
            
            // Create work order operations based on routing operations
            $routingOperations = $workOrder->routing->routingOperations;
            foreach ($routingOperations as $routingOperation) {
                WorkOrderOperation::create([
                    'wo_id' => $workOrder->wo_id,
                    'routing_operation_id' => $routingOperation->operation_id,
                    'scheduled_start' => now(),
                    'scheduled_end' => now(),
                    'actual_start' => null,
                    'actual_end' => null,
                    'actual_labor_time' => 0,
                    'actual_machine_time' => 0,
                    'status' => 'Pending',
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'data' => $workOrder->load(['item', 'bom', 'routing', 'workOrderOperations']),
                'message' => 'Work order created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create work order', 'error' => $e->getMessage()], 500);
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
        $workOrder = WorkOrder::with([
            'item', 
            'bom.bomLines.item', 
            'routing.routingOperations.workCenter',
            'workOrderOperations.routingOperation'
        ])->find($id);
        
        if (!$workOrder) {
            return response()->json(['message' => 'Work order not found'], 404);
        }
        
        return response()->json(['data' => $workOrder]);
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
        $workOrder = WorkOrder::find($id);
        
        if (!$workOrder) {
            return response()->json(['message' => 'Work order not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'wo_number' => 'sometimes|required|string|max:50|unique:work_orders,wo_number,' . $id . ',wo_id',
            'wo_date' => 'sometimes|required|date',
            'item_id' => 'sometimes|required|integer|exists:items,item_id',
            'bom_id' => 'sometimes|required|integer|exists:boms,bom_id',
            'routing_id' => 'sometimes|required|integer|exists:routings,routing_id',
            'planned_quantity' => 'sometimes|required|numeric',
            'planned_start_date' => 'sometimes|required|date',
            'planned_end_date' => 'sometimes|required|date|after_or_equal:planned_start_date',
            'status' => 'sometimes|required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $workOrder->update($request->all());
        
        return response()->json([
            'data' => $workOrder, 
            'message' => 'Work order updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $workOrder = WorkOrder::find($id);
        
        if (!$workOrder) {
            return response()->json(['message' => 'Work order not found'], 404);
        }

        // Check if work order has production orders
        if ($workOrder->productionOrders()->count() > 0) {
            return response()->json(['message' => 'Cannot delete work order. It has associated production orders.'], 400);
        }

        DB::beginTransaction();
        try {
            // Delete work order operations first
            $workOrder->workOrderOperations()->delete();
            
            // Then delete the work order
            $workOrder->delete();
            
            DB::commit();
            return response()->json(['message' => 'Work order deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete work order', 'error' => $e->getMessage()], 500);
        }
    }
}
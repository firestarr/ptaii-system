<?php

namespace App\Http\Controllers\Api\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\Routing;
use App\Models\Manufacturing\RoutingOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RoutingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $routings = Routing::with(['item'])->get();
        return response()->json(['data' => $routings]);
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
            'item_id' => 'required|integer|exists:items,item_id',
            'routing_code' => 'required|string|max:50',
            'revision' => 'required|string|max:10',
            'effective_date' => 'required|date',
            'status' => 'required|string|max:50',
            'operations' => 'sometimes|array',
            'operations.*.workcenter_id' => 'required|integer|exists:work_centers,workcenter_id',
            'operations.*.operation_name' => 'required|string|max:100',
            'operations.*.sequence' => 'required|integer',
            'operations.*.setup_time' => 'required|numeric',
            'operations.*.run_time' => 'required|numeric',
            'operations.*.uom_id' => 'required|integer|exists:UnitOfMeasure,uom_id',
            'operations.*.labor_cost' => 'required|numeric',
            'operations.*.overhead_cost' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $routing = Routing::create([
                'item_id' => $request->item_id,
                'routing_code' => $request->routing_code,
                'revision' => $request->revision,
                'effective_date' => $request->effective_date,
                'status' => $request->status,
            ]);

            if ($request->has('operations')) {
                foreach ($request->operations as $operation) {
                    RoutingOperation::create([
                        'routing_id' => $routing->routing_id,
                        'workcenter_id' => $operation['workcenter_id'],
                        'operation_name' => $operation['operation_name'],
                        'sequence' => $operation['sequence'],
                        'setup_time' => $operation['setup_time'],
                        'run_time' => $operation['run_time'],
                        'uom_id' => $operation['uom_id'],
                        'labor_cost' => $operation['labor_cost'],
                        'overhead_cost' => $operation['overhead_cost'],
                    ]);
                }
            }

            DB::commit();
            
            return response()->json([
                'data' => $routing->load('routingOperations'), 
                'message' => 'Routing created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create routing', 'error' => $e->getMessage()], 500);
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
        $routing = Routing::with(['item', 'routingOperations.workCenter', 'routingOperations.unitOfMeasure'])->find($id);
        
        if (!$routing) {
            return response()->json(['message' => 'Routing not found'], 404);
        }
        
        return response()->json(['data' => $routing]);
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
        $routing = Routing::find($id);
        
        if (!$routing) {
            return response()->json(['message' => 'Routing not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => 'sometimes|required|integer|exists:items,item_id',
            'routing_code' => 'sometimes|required|string|max:50',
            'revision' => 'sometimes|required|string|max:10',
            'effective_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $routing->update($request->all());
        return response()->json(['data' => $routing, 'message' => 'Routing updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $routing = Routing::find($id);
        
        if (!$routing) {
            return response()->json(['message' => 'Routing not found'], 404);
        }

        // Check if routing is being used in Work Orders
        if ($routing->workOrders()->count() > 0) {
            return response()->json(['message' => 'Cannot delete routing. It is being used in Work Orders.'], 400);
        }

        DB::beginTransaction();
        try {
            // Delete routing operations first
            $routing->routingOperations()->delete();
            
            // Then delete the routing
            $routing->delete();
            
            DB::commit();
            return response()->json(['message' => 'Routing deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete routing', 'error' => $e->getMessage()], 500);
        }
    }
}
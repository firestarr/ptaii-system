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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Routing::with(['item']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('routing_code', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('revision', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('status', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('item', function ($itemQuery) use ($searchTerm) {
                      $itemQuery->where('name', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Item filter
        if ($request->has('item_id') && !empty($request->item_id)) {
            $query->where('item_id', $request->item_id);
        }

        // Sorting
        $sortField = $request->get('sort_field', 'routing_code');
        $sortOrder = $request->get('sort_order', 'asc');
        
        // Validate sort order
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'asc';
        
        // Handle sorting for related fields
        if ($sortField === 'item_name') {
            $query->join('items', 'routings.item_id', '=', 'items.item_id')
                  ->orderBy('items.name', $sortOrder)
                  ->select('routings.*');
        } else {
            // Validate sort field to prevent SQL injection
            $allowedSortFields = [
                'routing_code', 'revision', 'effective_date', 'status', 'created_at', 'updated_at'
            ];
            
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortOrder);
            } else {
                $query->orderBy('routing_code', 'asc');
            }
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $perPage = is_numeric($perPage) && $perPage > 0 && $perPage <= 100 ? $perPage : 10;

        $routings = $query->paginate($perPage);

        return response()->json([
            'data' => $routings->items(),
            'meta' => [
                'current_page' => $routings->currentPage(),
                'last_page' => $routings->lastPage(),
                'per_page' => $routings->perPage(),
                'from' => $routings->firstItem(),
                'to' => $routings->lastItem(),
                'total' => $routings->total(),
            ]
        ]);
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
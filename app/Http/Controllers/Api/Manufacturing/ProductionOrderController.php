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
    const WIP_WAREHOUSE_ID = 3; // Work in Progress warehouse
    
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
            'planned_quantity' => 'required|numeric|min:0.01',
            'actual_quantity' => 'sometimes|numeric|min:0',
            'status' => 'required|string|max:50',
            'consumptions' => 'sometimes|array',
            'consumptions.*.item_id' => 'required|integer|exists:items,item_id',
            'consumptions.*.planned_quantity' => 'required|numeric|min:0',
            'consumptions.*.actual_quantity' => 'sometimes|nullable|numeric|min:0',
            'consumptions.*.warehouse_id' => 'required|integer|exists:warehouses,warehouse_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validation for work order capacity
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

            // Create consumption entries
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
                // Auto-generate consumption entries from BOM
                $this->generateConsumptionsFromBOM($productionOrder, $request->default_warehouse_id ?? self::RAW_MATERIALS_WAREHOUSE_ID);
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

        // Prevent updates if materials are already issued
        if ($productionOrder->status === 'Materials Issued' || $productionOrder->status === 'In Progress' || $productionOrder->status === 'Completed') {
            return response()->json([
                'message' => 'Cannot update production order after materials are issued',
                'errors' => ['status' => ['Production order cannot be modified after materials are issued']]
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'wo_id' => 'sometimes|required|integer|exists:work_orders,wo_id',
            'production_number' => 'sometimes|required|string|max:50|unique:production_orders,production_number,' . $id . ',production_id',
            'production_date' => 'sometimes|required|date',
            'planned_quantity' => 'sometimes|required|numeric|min:0.01',
            'actual_quantity' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $productionOrder->update($request->all());
            
            // Regenerate consumptions if planned quantity changed
            if ($request->has('planned_quantity')) {
                $this->regenerateConsumptionsFromBOM($productionOrder, self::RAW_MATERIALS_WAREHOUSE_ID);
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

        // Prevent deletion if materials are already issued
        if ($productionOrder->status !== 'Draft' && $productionOrder->status !== 'Cancelled') {
            return response()->json([
                'message' => 'Cannot delete production order',
                'errors' => ['status' => ['Only draft or cancelled production orders can be deleted']]
            ], 422);
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
     * Issue materials for production (Step 1: Material consumption)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function issueMaterials(Request $request, $id)
    {
        $productionOrder = ProductionOrder::with(['workOrder.item', 'productionConsumptions.item'])
            ->find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }
        
        // Validate current status
        if ($productionOrder->status !== 'Draft') {
            return response()->json([
                'message' => 'Invalid operation',
                'errors' => ['status' => ['Materials can only be issued for draft production orders']]
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'consumptions' => 'required|array|min:1',
            'consumptions.*.consumption_id' => 'required|integer',
            'consumptions.*.actual_quantity' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate stock availability
        $stockErrors = [];
        $consumptionsMap = [];
        
        foreach ($request->consumptions as $consumption) {
            $consumptionsMap[$consumption['consumption_id']] = $consumption['actual_quantity'];
        }
        
        // Check stock availability for each consumption
        foreach ($productionOrder->productionConsumptions as $consumption) {
            if (!isset($consumptionsMap[$consumption->consumption_id])) {
                continue;
            }
            
            $actualConsumption = $consumptionsMap[$consumption->consumption_id];
            
            // Get current stock
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
        
        if (!empty($stockErrors)) {
            return response()->json([
                'message' => 'Stock validation failed',
                'errors' => ['stock' => $stockErrors]
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Process material consumptions (Issue materials from Raw Materials to WIP)
            foreach ($productionOrder->productionConsumptions as $consumption) {
                if (!isset($consumptionsMap[$consumption->consumption_id])) {
                    continue;
                }
                
                $actualConsumption = $consumptionsMap[$consumption->consumption_id];
                
                // Update consumption record
                $consumption->actual_quantity = $actualConsumption;
                $consumption->variance = $consumption->planned_quantity - $actualConsumption;
                $consumption->save();
                
                // Create stock transaction for material issue (Raw Materials -> WIP)
                $materialTransaction = StockTransaction::create([
                    'item_id' => $consumption->item_id,
                    'warehouse_id' => $consumption->warehouse_id, // Source: Raw Materials
                    'dest_warehouse_id' => self::WIP_WAREHOUSE_ID, // Destination: WIP
                    'transaction_type' => StockTransaction::TYPE_MANUFACTURING,
                    'move_type' => StockTransaction::MOVE_TYPE_INTERNAL, // Internal transfer
                    'quantity' => $actualConsumption,
                    'transaction_date' => now(),
                    'reference_document' => 'material_issue',
                    'reference_number' => $productionOrder->production_number,
                    'origin' => "WO-{$productionOrder->wo_id}",
                    'batch_id' => null,
                    'state' => StockTransaction::STATE_DRAFT,
                    'notes' => "Material issued for production"
                ]);

                // Auto-confirm to update stock
                $materialTransaction->markAsDone();
            }
            
            // Update production order status
            $productionOrder->status = 'Materials Issued';
            $productionOrder->save();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Materials issued successfully. Production can now begin.',
                'data' => $productionOrder->fresh(['workOrder', 'productionConsumptions.item'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to issue materials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start production (Step 2: Begin production process)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function startProduction(Request $request, $id)
    {
        $productionOrder = ProductionOrder::find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }
        
        // Validate current status
        if ($productionOrder->status !== 'Materials Issued') {
            return response()->json([
                'message' => 'Invalid operation',
                'errors' => ['status' => ['Production can only be started after materials are issued']]
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update production order status
            $productionOrder->status = 'In Progress';
            $productionOrder->save();
            
            // Update work order status if needed
            $workOrder = $productionOrder->workOrder;
            if ($workOrder->status === 'Draft') {
                $workOrder->status = 'In Progress';
                $workOrder->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Production started successfully',
                'data' => $productionOrder->fresh(['workOrder', 'productionConsumptions.item'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to start production',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete production (Step 3: Receive finished goods)
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
        
        // Validate current status
        if ($productionOrder->status !== 'In Progress') {
            return response()->json([
                'message' => 'Invalid operation',
                'errors' => ['status' => ['Production can only be completed from In Progress status']]
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'actual_quantity' => 'required|numeric|min:0.01',
            'quality_notes' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $actualQuantity = floatval($request->actual_quantity);
        
        DB::beginTransaction();
        try {
            // Update production order
            $productionOrder->actual_quantity = $actualQuantity;
            $productionOrder->status = 'Completed';
            $productionOrder->save();
            
            // Get the finished product
            $finishedItem = $productionOrder->workOrder->item;
            
            // Create stock transaction for finished product (WIP -> Finished Goods)
            $finishedProductTransaction = StockTransaction::create([
                'item_id' => $finishedItem->item_id,
                'warehouse_id' => self::WIP_WAREHOUSE_ID, // Source: WIP
                'dest_warehouse_id' => self::FINISHED_GOODS_WAREHOUSE_ID, // Destination: Finished Goods
                'transaction_type' => StockTransaction::TYPE_MANUFACTURING,
                'move_type' => StockTransaction::MOVE_TYPE_INTERNAL, // Internal transfer
                'quantity' => $actualQuantity,
                'transaction_date' => now(),
                'reference_document' => 'production_completion',
                'reference_number' => $productionOrder->production_number,
                'origin' => "WO-{$productionOrder->wo_id}",
                'batch_id' => null,
                'state' => StockTransaction::STATE_DRAFT,
                'notes' => "Finished product from production completion"
            ]);

            // Auto-confirm to update stock
            $finishedProductTransaction->markAsDone();
            
            // Update work order progress
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
                'message' => 'Production completed successfully. Finished goods received.',
                'data' => $productionOrder->fresh(['workOrder', 'productionConsumptions.item'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to complete production',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the status of the production order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        $productionOrder = ProductionOrder::find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Draft,Materials Issued,In Progress,Completed,Cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $currentStatus = $productionOrder->status;
        $newStatus = $request->status;

        // Define allowed status transitions
        $allowedTransitions = [
            'Draft' => ['Materials Issued', 'Cancelled'],
            'Materials Issued' => ['In Progress', 'Cancelled'],
            'In Progress' => ['Completed', 'Cancelled'],
            'Completed' => [], // Completed orders cannot be changed
            'Cancelled' => ['Draft'], // Can reactivate cancelled orders
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            return response()->json([
                'message' => 'Invalid status transition',
                'errors' => [
                    'status' => [
                        "Cannot change status from '{$currentStatus}' to '{$newStatus}'",
                        "Use specific endpoints: issueMaterials(), startProduction(), complete()",
                        "Or allowed manual transitions: " . implode(', ', $allowedTransitions[$currentStatus])
                    ]
                ]
            ], 422);
        }

        // For most transitions, recommend using specific endpoints
        if ($newStatus !== 'Cancelled' && $newStatus !== 'Draft') {
            return response()->json([
                'message' => 'Use specific endpoints',
                'errors' => [
                    'status' => [
                        'For better control, use specific endpoints:',
                        '- POST /production-orders/{id}/issue-materials',
                        '- POST /production-orders/{id}/start-production', 
                        '- POST /production-orders/{id}/complete'
                    ]
                ]
            ], 422);
        }

        DB::beginTransaction();
        try {
            $productionOrder->status = $newStatus;
            
            if ($newStatus === 'Cancelled') {
                $productionOrder->actual_quantity = 0;
            }
            
            if ($newStatus === 'Draft' && $currentStatus === 'Cancelled') {
                $productionOrder->actual_quantity = 0;
            }
            
            $productionOrder->save();

            DB::commit();

            return response()->json([
                'message' => $this->getStatusChangeMessage($currentStatus, $newStatus),
                'data' => $productionOrder->fresh(['workOrder', 'productionConsumptions.item'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update production order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate consumptions from BOM
     */
    private function generateConsumptionsFromBOM($productionOrder, $warehouseId)
    {
        $workOrder = WorkOrder::with('bom.bomLines')->find($productionOrder->wo_id);
        if ($workOrder && $workOrder->bom) {
            foreach ($workOrder->bom->bomLines as $bomLine) {
                $baseQty = $bomLine->quantity * ($productionOrder->planned_quantity / $workOrder->bom->standard_quantity);
                $plannedQty = $baseQty;

                if ($bomLine->is_yield_based && $bomLine->yield_ratio > 0) {
                    $plannedQty = $baseQty / $bomLine->yield_ratio;
                    if ($bomLine->shrinkage_factor > 0) {
                        $plannedQty = $plannedQty / (1 - $bomLine->shrinkage_factor);
                    }
                }

                $plannedQty = ceil($plannedQty);

                ProductionConsumption::create([
                    'production_id' => $productionOrder->production_id,
                    'item_id' => $bomLine->item_id,
                    'planned_quantity' => $plannedQty,
                    'actual_quantity' => 0,
                    'variance' => $plannedQty,
                    'warehouse_id' => $warehouseId,
                ]);
            }
        }
    }

    /**
     * Regenerate consumptions from BOM
     */
    private function regenerateConsumptionsFromBOM($productionOrder, $warehouseId)
    {
        // Delete existing consumptions
        $productionOrder->productionConsumptions()->delete();
        
        // Generate new ones
        $this->generateConsumptionsFromBOM($productionOrder, $warehouseId);
    }

    /**
     * Get material status for production order
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getMaterialStatus($id)
    {
        $productionOrder = ProductionOrder::with([
            'productionConsumptions.item',
            'productionConsumptions.warehouse'
        ])->find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }

        $materialStatus = [];
        $allMaterialsAvailable = true;
        $totalPlannedValue = 0;
        $totalActualValue = 0;

        foreach ($productionOrder->productionConsumptions as $consumption) {
            // Get current stock
            $itemStock = ItemStock::where('item_id', $consumption->item_id)
                ->where('warehouse_id', $consumption->warehouse_id)
                ->first();
            
            $availableStock = $itemStock ? $itemStock->quantity : 0;
            $isAvailable = $availableStock >= $consumption->planned_quantity;
            
            if (!$isAvailable) {
                $allMaterialsAvailable = false;
            }

            // Calculate estimated values
            $estimatedUnitPrice = $consumption->item->cost_price ?? 0;
            $plannedValue = $consumption->planned_quantity * $estimatedUnitPrice;
            $actualValue = $consumption->actual_quantity * $estimatedUnitPrice;
            
            $totalPlannedValue += $plannedValue;
            $totalActualValue += $actualValue;

            $materialStatus[] = [
                'consumption_id' => $consumption->consumption_id,
                'item_id' => $consumption->item_id,
                'item_code' => $consumption->item->item_code,
                'item_name' => $consumption->item->name,
                'planned_quantity' => $consumption->planned_quantity,
                'actual_quantity' => $consumption->actual_quantity,
                'variance' => $consumption->variance,
                'available_stock' => $availableStock,
                'is_available' => $isAvailable,
                'shortage' => max(0, $consumption->planned_quantity - $availableStock),
                'unit_price' => $estimatedUnitPrice,
                'planned_value' => $plannedValue,
                'actual_value' => $actualValue,
                'warehouse_name' => $consumption->warehouse->name ?? 'Unknown',
                'status' => $consumption->actual_quantity > 0 ? 'Issued' : 'Pending'
            ];
        }

        return response()->json([
            'data' => [
                'production_order_id' => $productionOrder->production_id,
                'production_number' => $productionOrder->production_number,
                'status' => $productionOrder->status,
                'all_materials_available' => $allMaterialsAvailable,
                'total_planned_value' => $totalPlannedValue,
                'total_actual_value' => $totalActualValue,
                'material_details' => $materialStatus
            ]
        ]);
    }

    /**
     * Get production summary
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getProductionSummary($id)
    {
        $productionOrder = ProductionOrder::with([
            'workOrder.item',
            'productionConsumptions.item'
        ])->find($id);
        
        if (!$productionOrder) {
            return response()->json(['message' => 'Production order not found'], 404);
        }

        // Calculate efficiency metrics
        $plannedQuantity = $productionOrder->planned_quantity;
        $actualQuantity = $productionOrder->actual_quantity;
        $efficiency = $plannedQuantity > 0 ? ($actualQuantity / $plannedQuantity) * 100 : 0;

        // Calculate material utilization
        $totalPlannedMaterials = $productionOrder->productionConsumptions->sum('planned_quantity');
        $totalActualMaterials = $productionOrder->productionConsumptions->sum('actual_quantity');
        $materialUtilization = $totalPlannedMaterials > 0 ? ($totalActualMaterials / $totalPlannedMaterials) * 100 : 0;

        // Calculate variances
        $quantityVariance = $actualQuantity - $plannedQuantity;
        $quantityVariancePercent = $plannedQuantity > 0 ? ($quantityVariance / $plannedQuantity) * 100 : 0;

        // Production timeline
        $timeline = [
            'created' => $productionOrder->created_at,
            'materials_issued' => null,
            'production_started' => null,
            'completed' => null
        ];

        // Get stock transactions to determine timeline
        $transactions = StockTransaction::where('reference_number', $productionOrder->production_number)
            ->orderBy('transaction_date')
            ->get();

        foreach ($transactions as $transaction) {
            if ($transaction->reference_document === 'material_issue') {
                $timeline['materials_issued'] = $transaction->transaction_date;
            } elseif ($transaction->reference_document === 'production_completion') {
                $timeline['completed'] = $transaction->transaction_date;
            }
        }

        // Estimate production start time (after materials issued)
        if ($timeline['materials_issued'] && $productionOrder->status !== 'Materials Issued') {
            $timeline['production_started'] = $timeline['materials_issued'];
        }

        return response()->json([
            'data' => [
                'production_order' => [
                    'id' => $productionOrder->production_id,
                    'number' => $productionOrder->production_number,
                    'status' => $productionOrder->status,
                    'work_order_number' => $productionOrder->workOrder->wo_number,
                    'product_name' => $productionOrder->workOrder->item->name,
                    'product_code' => $productionOrder->workOrder->item->item_code,
                ],
                'quantities' => [
                    'planned' => $plannedQuantity,
                    'actual' => $actualQuantity,
                    'variance' => $quantityVariance,
                    'variance_percent' => round($quantityVariancePercent, 2),
                ],
                'metrics' => [
                    'production_efficiency' => round($efficiency, 2),
                    'material_utilization' => round($materialUtilization, 2),
                    'total_materials_planned' => $totalPlannedMaterials,
                    'total_materials_used' => $totalActualMaterials,
                ],
                'timeline' => $timeline,
                'material_summary' => $productionOrder->productionConsumptions->map(function ($consumption) {
                    return [
                        'item_name' => $consumption->item->name,
                        'planned' => $consumption->planned_quantity,
                        'actual' => $consumption->actual_quantity,
                        'variance' => $consumption->variance,
                        'variance_percent' => $consumption->planned_quantity > 0 
                            ? round(($consumption->variance / $consumption->planned_quantity) * 100, 2) 
                            : 0
                    ];
                })
            ]
        ]);
    }

    /**
     * Bulk issue materials for multiple production orders
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function bulkIssueMaterials(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'production_orders' => 'required|array|min:1',
            'production_orders.*.production_id' => 'required|integer',
            'production_orders.*.consumptions' => 'required|array',
            'production_orders.*.consumptions.*.consumption_id' => 'required|integer',
            'production_orders.*.consumptions.*.actual_quantity' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $results = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->production_orders as $orderData) {
                $productionOrder = ProductionOrder::find($orderData['production_id']);
                
                if (!$productionOrder) {
                    $errors[] = "Production order {$orderData['production_id']} not found";
                    continue;
                }

                if ($productionOrder->status !== 'Draft') {
                    $errors[] = "Production order {$productionOrder->production_number} is not in Draft status";
                    continue;
                }

                // Process this production order
                $fakeRequest = new Request(['consumptions' => $orderData['consumptions']]);
                $response = $this->issueMaterials($fakeRequest, $orderData['production_id']);
                
                if ($response->getStatusCode() === 200) {
                    $results[] = [
                        'production_id' => $orderData['production_id'],
                        'production_number' => $productionOrder->production_number,
                        'status' => 'success'
                    ];
                } else {
                    $errors[] = "Failed to issue materials for production order {$productionOrder->production_number}";
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Bulk operation failed',
                    'errors' => $errors
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => 'Materials issued successfully for all production orders',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Bulk operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get appropriate message for status change
     */
    private function getStatusChangeMessage($fromStatus, $toStatus)
    {
        $messages = [
            'Draft|Cancelled' => 'Production order cancelled successfully.',
            'Materials Issued|Cancelled' => 'Production cancelled. Materials remain in WIP warehouse.',
            'In Progress|Cancelled' => 'Production cancelled. Check WIP inventory for issued materials.',
            'Cancelled|Draft' => 'Production order reactivated successfully.',
        ];

        $key = $fromStatus . '|' . $toStatus;
        return $messages[$key] ?? 'Production order status updated successfully.';
    }
}
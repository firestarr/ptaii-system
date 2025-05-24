<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockTransaction;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StockTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = StockTransaction::with(['item', 'warehouse', 'destWarehouse', 'batch']);
        
        // Filter by item
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }
        
        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where(function($q) use ($request) {
                $q->where('warehouse_id', $request->warehouse_id)
                  ->orWhere('dest_warehouse_id', $request->warehouse_id);
            });
        }
        
        // Filter by transaction type
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        
        // Filter by move type
        if ($request->has('move_type')) {
            $query->where('move_type', $request->move_type);
        }
        
        // Filter by state
        if ($request->has('state')) {
            $query->where('state', $request->state);
        }
        
        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }
        
        // Pagination
        $perPage = $request->per_page ?? 15;
        $transactions = $query->orderBy('transaction_date', 'desc')
                           ->paginate($perPage);
        
        // Add computed fields
        $transactions->getCollection()->transform(function ($transaction) {
            $transaction->description = $transaction->description;
            $transaction->is_transfer = $transaction->isTransfer();
            return $transaction;
        });
        
        return response()->json([
            'success' => true,
            'data' => $transactions
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
            'item_id' => 'required|exists:items,item_id',
            'warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'dest_warehouse_id' => 'nullable|exists:warehouses,warehouse_id|different:warehouse_id',
            'transaction_type' => 'required|string|in:receive,issue,transfer,adjustment,return,manufacturing',
            'quantity' => 'required|numeric|min:0.01', // Always positive like Odoo
            'transaction_date' => 'required|date',
            'reference_document' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:50',
            'origin' => 'nullable|string|max:100',
            'batch_id' => 'nullable|exists:item_batches,batch_id',
            'notes' => 'nullable|string',
            'auto_confirm' => 'boolean' // Whether to automatically confirm the move
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Determine move_type based on transaction_type and dest_warehouse_id
        $moveType = $this->determineMoveType($request->transaction_type, $request->dest_warehouse_id);
        
        // Validate batch belongs to item
        if ($request->batch_id) {
            $isValidBatch = DB::table('item_batches')
                ->where('batch_id', $request->batch_id)
                ->where('item_id', $request->item_id)
                ->exists();
                
            if (!$isValidBatch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch does not belong to the specified item'
                ], 422);
            }
        }

        DB::beginTransaction();
        
        try {
            // Create the stock transaction
            $transaction = StockTransaction::create([
                'item_id' => $request->item_id,
                'warehouse_id' => $request->warehouse_id,
                'dest_warehouse_id' => $request->dest_warehouse_id,
                'transaction_type' => $request->transaction_type,
                'move_type' => $moveType,
                'quantity' => $request->quantity, // Always positive
                'transaction_date' => $request->transaction_date,
                'reference_document' => $request->reference_document,
                'reference_number' => $request->reference_number,
                'origin' => $request->origin,
                'batch_id' => $request->batch_id,
                'state' => StockTransaction::STATE_DRAFT,
                'notes' => $request->notes
            ]);
            
            // Auto-confirm if requested
            if ($request->auto_confirm) {
                $transaction->markAsDone();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock transaction created successfully',
                'data' => $transaction->load(['item', 'warehouse', 'destWarehouse'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock transaction',
                'error' => $e->getMessage()
            ], 500);
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
        $transaction = StockTransaction::with(['item', 'warehouse', 'destWarehouse', 'batch'])->find($id);
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Stock transaction not found'
            ], 404);
        }

        $transaction->description = $transaction->description;
        $transaction->is_transfer = $transaction->isTransfer();

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
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
        $transaction = StockTransaction::find($id);
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Stock transaction not found'
            ], 404);
        }

        // Only allow updating draft transactions
        if ($transaction->state !== StockTransaction::STATE_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft transactions can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reference_document' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:50',
            'origin' => 'nullable|string|max:100',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Stock transaction updated successfully',
            'data' => $transaction
        ]);
    }

    /**
     * Confirm/Complete a stock transaction
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function confirm($id)
    {
        $transaction = StockTransaction::find($id);
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Stock transaction not found'
            ], 404);
        }

        if ($transaction->state !== StockTransaction::STATE_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft transactions can be confirmed'
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            $transaction->markAsDone();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock transaction confirmed successfully',
                'data' => $transaction->fresh(['item', 'warehouse', 'destWarehouse'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm stock transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a stock transaction
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        $transaction = StockTransaction::find($id);
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Stock transaction not found'
            ], 404);
        }

        try {
            $transaction->cancel();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock transaction cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
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
        $transaction = StockTransaction::find($id);
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Stock transaction not found'
            ], 404);
        }

        // Only allow deleting draft transactions
        if ($transaction->state !== StockTransaction::STATE_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft transactions can be deleted'
            ], 403);
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock transaction deleted successfully'
        ]);
    }
    
    /**
     * Get stock movement history for an item (Odoo-style)
     *
     * @param  int  $itemId
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function itemMovement($itemId, Request $request)
    {
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }
        
        $query = StockTransaction::where('item_id', $itemId)
            ->with(['warehouse', 'destWarehouse', 'batch']);
        
        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }
        
        // Filter by warehouse (either source or destination)
        if ($request->has('warehouse_id')) {
            $query->where(function($q) use ($request) {
                $q->where('warehouse_id', $request->warehouse_id)
                  ->orWhere('dest_warehouse_id', $request->warehouse_id);
            });
        }
        
        // Filter by state
        if ($request->has('state')) {
            $query->where('state', $request->state);
        }
        
        // Pagination
        $perPage = $request->per_page ?? 15;
        $transactions = $query->orderBy('transaction_date', 'desc')
                           ->orderBy('created_at', 'desc')
                           ->paginate($perPage);
        
        // Add computed fields
        $transactions->getCollection()->transform(function ($transaction) use ($request) {
            $transaction->description = $transaction->description;
            $transaction->is_transfer = $transaction->isTransfer();
            
            // Calculate effect on specific warehouse if filtering by warehouse
            if ($request->has('warehouse_id')) {
                $warehouseId = $request->warehouse_id;
                
                if ($transaction->isTransfer()) {
                    if ($transaction->warehouse_id == $warehouseId) {
                        $transaction->warehouse_effect = -$transaction->quantity; // Outgoing
                    } elseif ($transaction->dest_warehouse_id == $warehouseId) {
                        $transaction->warehouse_effect = $transaction->quantity; // Incoming
                    } else {
                        $transaction->warehouse_effect = 0;
                    }
                } elseif ($transaction->warehouse_id == $warehouseId) {
                    $transaction->warehouse_effect = $transaction->stock_direction * $transaction->quantity;
                } else {
                    $transaction->warehouse_effect = 0;
                }
            }
            
            return $transaction;
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'item' => $item->only(['item_id', 'item_code', 'name']),
                'transactions' => $transactions
            ]
        ]);
    }
    
    /**
     * Create a stock transfer between warehouses (Odoo-style)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'from_warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'to_warehouse_id' => 'required|exists:warehouses,warehouse_id|different:from_warehouse_id',
            'quantity' => 'required|numeric|gt:0',
            'transaction_date' => 'required|date',
            'batch_id' => 'nullable|exists:item_batches,batch_id',
            'reference_number' => 'nullable|string|max:50',
            'origin' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'auto_confirm' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check available stock in source warehouse
        $sourceStock = ItemStock::where('item_id', $request->item_id)
            ->where('warehouse_id', $request->from_warehouse_id)
            ->first();
            
        if (!$sourceStock || $sourceStock->available_quantity < $request->quantity) {
            $available = $sourceStock ? $sourceStock->available_quantity : 0;
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock in source warehouse',
                'available' => $available
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Create single transfer transaction (Odoo-style)
            $transaction = StockTransaction::create([
                'item_id' => $request->item_id,
                'warehouse_id' => $request->from_warehouse_id,
                'dest_warehouse_id' => $request->to_warehouse_id,
                'transaction_type' => StockTransaction::TYPE_TRANSFER,
                'move_type' => StockTransaction::MOVE_TYPE_INTERNAL,
                'quantity' => $request->quantity, // Always positive
                'transaction_date' => $request->transaction_date,
                'reference_document' => 'stock_transfer',
                'reference_number' => $request->reference_number ?? 'TR' . time(),
                'origin' => $request->origin,
                'batch_id' => $request->batch_id,
                'state' => StockTransaction::STATE_DRAFT,
                'notes' => $request->notes
            ]);
            
            // Auto-confirm if requested
            if ($request->auto_confirm) {
                $transaction->markAsDone();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock transfer created successfully',
                'data' => $transaction->load(['item', 'warehouse', 'destWarehouse'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending stock transactions
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getPending(Request $request)
    {
        $query = StockTransaction::with(['item', 'warehouse', 'destWarehouse'])
            ->where('state', StockTransaction::STATE_DRAFT);
        
        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where(function($q) use ($request) {
                $q->where('warehouse_id', $request->warehouse_id)
                  ->orWhere('dest_warehouse_id', $request->warehouse_id);
            });
        }
        
        $transactions = $query->orderBy('transaction_date', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * Bulk confirm transactions
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkConfirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:stock_transactions,transaction_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $confirmed = 0;
            $errors = [];
            
            foreach ($request->transaction_ids as $id) {
                $transaction = StockTransaction::find($id);
                
                if ($transaction && $transaction->state === StockTransaction::STATE_DRAFT) {
                    try {
                        $transaction->markAsDone();
                        $confirmed++;
                    } catch (\Exception $e) {
                        $errors[] = "Transaction {$id}: " . $e->getMessage();
                    }
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully confirmed {$confirmed} transactions",
                'confirmed_count' => $confirmed,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk confirm transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine move type based on transaction type and destination
     *
     * @param string $transactionType
     * @param int|null $destWarehouseId
     * @return string
     */
    private function determineMoveType($transactionType, $destWarehouseId)
    {
        switch ($transactionType) {
            case StockTransaction::TYPE_RECEIVE:
            case StockTransaction::TYPE_RETURN:
                return StockTransaction::MOVE_TYPE_IN;
                
            case StockTransaction::TYPE_ISSUE:
                return StockTransaction::MOVE_TYPE_OUT;
                
            case StockTransaction::TYPE_TRANSFER:
                return StockTransaction::MOVE_TYPE_INTERNAL;
                
            case StockTransaction::TYPE_ADJUSTMENT:
                return StockTransaction::MOVE_TYPE_INTERNAL;
                
            case StockTransaction::TYPE_MANUFACTURING:
                return $destWarehouseId ? StockTransaction::MOVE_TYPE_INTERNAL : StockTransaction::MOVE_TYPE_OUT;
                
            default:
                return StockTransaction::MOVE_TYPE_INTERNAL;
        }
    }

    public function getWarehouseTransactions($warehouseId)
    {
        $transactions = StockTransaction::where(function($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId)
                      ->orWhere('dest_warehouse_id', $warehouseId);
            })
            ->with(['item', 'warehouse', 'destWarehouse', 'batch'])
            ->orderBy('transaction_date', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }
}
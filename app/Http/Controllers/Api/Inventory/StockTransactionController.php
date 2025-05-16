<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
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
        $query = StockTransaction::with(['item', 'warehouse', 'batch']);
        
        // Filter by item
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }
        
        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        
        // Filter by transaction type
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
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
            //'location_id' => 'nullable|exists:warehouse_locations,location_id',
            'transaction_type' => 'required|string|in:receive,issue,transfer,adjustment,return',
            'quantity' => 'required|numeric',
            'transaction_date' => 'required|date',
            'reference_document' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:50',
            'batch_id' => 'nullable|exists:item_batches,batch_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate location belongs to warehouse
        // if ($request->location_id) {
        //     $location = WarehouseLocation::find($request->location_id);
        //     if (!$location || $location->zone->warehouse_id != $request->warehouse_id) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Location does not belong to the specified warehouse'
        //         ], 422);
        //     }
        // }
        
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
            $transaction = StockTransaction::create($validator->validated());
            
            // Update the item's current stock
            $item = Item::find($request->item_id);
            $item->current_stock += $request->quantity;
            $item->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock transaction created successfully',
                'data' => $transaction
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
        $transaction = StockTransaction::with(['item', 'warehouse', 'batch'])->find($id);
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Stock transaction not found'
            ], 404);
        }

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

        // We should not allow updating critical fields that affect stock quantities
        // This could cause inconsistencies in inventory levels
        $validator = Validator::make($request->all(), [
            'reference_document' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:50'
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
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // In real-world ERP systems, stock transactions are rarely deleted
        // Instead, they are reversed with a new transaction
        return response()->json([
            'success' => false,
            'message' => 'Deleting stock transactions is not allowed. Create a reverse transaction instead.'
        ], 403);
    }
    
    /**
     * Get stock movement history for an item
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
            ->with(['warehouse', 'batch']);
        
        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }
        
        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        
        // Pagination
        $perPage = $request->per_page ?? 15;
        $transactions = $query->orderBy('transaction_date', 'desc')
                           ->paginate($perPage);
        
        // Calculate running balance
        $runningBalance = 0;
        $transactions->getCollection()->transform(function ($transaction) use (&$runningBalance) {
            $runningBalance += $transaction->quantity;
            $transaction->running_balance = $runningBalance;
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
     * Create a stock transfer between warehouses
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'from_warehouse_id' => 'required|exists:warehouses,warehouse_id',
            //'from_location_id' => 'nullable|exists:warehouse_locations,location_id',
            'to_warehouse_id' => 'required|exists:warehouses,warehouse_id',
            //'to_location_id' => 'nullable|exists:warehouse_locations,location_id',
            'quantity' => 'required|numeric|gt:0',
            'transaction_date' => 'required|date',
            'batch_id' => 'nullable|exists:item_batches,batch_id',
            'reference_number' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check that source and destination are different
        if ($request->from_warehouse_id == $request->to_warehouse_id/* && 
            $request->from_location_id == $request->to_location_id*/) {
            return response()->json([
                'success' => false,
                'message' => 'Source and destination must be different'
            ], 422);
        }

        // Validate locations belong to their respective warehouses
        // if ($request->from_location_id) {
        //     $location = WarehouseLocation::find($request->from_location_id);
        //     if (!$location || $location->zone->warehouse_id != $request->from_warehouse_id) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Source location does not belong to the source warehouse'
        //         ], 422);
        //     }
        // }
        
        // if ($request->to_location_id) {
        //     $location = WarehouseLocation::find($request->to_location_id);
        //     if (!$location || $location->zone->warehouse_id != $request->to_warehouse_id) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Destination location does not belong to the destination warehouse'
        //         ], 422);
        //     }
        // }

        // Check available stock in source warehouse
        $sourceStock = StockTransaction::where('item_id', $request->item_id)
            ->where('warehouse_id', $request->from_warehouse_id)
            // ->when($request->from_location_id, function ($query) use ($request) {
            //     return $query->where('location_id', $request->from_location_id);
            // })
            ->when($request->batch_id, function ($query) use ($request) {
                return $query->where('batch_id', $request->batch_id);
            })
            ->sum('quantity');
            
        if ($sourceStock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock in source location',
                'available' => $sourceStock
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Create outgoing transaction from source
            $outTransaction = StockTransaction::create([
                'item_id' => $request->item_id,
                'warehouse_id' => $request->from_warehouse_id,
                //'location_id' => $request->from_location_id,
                'transaction_type' => 'transfer',
                'quantity' => -$request->quantity, // Negative for outgoing
                'transaction_date' => $request->transaction_date,
                'reference_document' => 'stock_transfer',
                'reference_number' => $request->reference_number,
                'batch_id' => $request->batch_id
            ]);
            
            // Create incoming transaction to destination
            $inTransaction = StockTransaction::create([
                'item_id' => $request->item_id,
                'warehouse_id' => $request->to_warehouse_id,
                //'location_id' => $request->to_location_id,
                'transaction_type' => 'transfer',
                'quantity' => $request->quantity, // Positive for incoming
                'transaction_date' => $request->transaction_date,
                'reference_document' => 'stock_transfer',
                'reference_number' => $request->reference_number,
                'batch_id' => $request->batch_id
            ]);
            
            // The item's total stock doesn't change during a transfer
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock transfer completed successfully',
                'data' => [
                    'from' => $outTransaction,
                    'to' => $inTransaction
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete stock transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getWarehouseTransactions($warehouseId)
    {
        // Implementasi untuk mendapatkan transaksi berdasarkan warehouse
        $transactions = StockTransaction::where('warehouse_id', $warehouseId)
                        ->with(['item', 'warehouse', 'batch'])
                        ->orderBy('transaction_date', 'desc')
                        ->get();
        
        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }
}
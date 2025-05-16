<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemBatchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  int  $itemId
     * @return \Illuminate\Http\Response
     */
    public function index($itemId)
    {
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }
        
        $batches = $item->batches;
        
        return response()->json([
            'success' => true,
            'data' => $batches
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $itemId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $itemId)
    {
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'batch_number' => 'required|string|max:50',
            'expiry_date' => 'nullable|date',
            'manufacturing_date' => 'nullable|date|before_or_equal:today',
            'lot_number' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Create batch with item_id
        $data = $validator->validated();
        $data['item_id'] = $itemId;
        
        $batch = ItemBatch::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Item batch created successfully',
            'data' => $batch
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $itemId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($itemId, $id)
    {
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }
        
        $batch = $item->batches()->with('stockTransactions')->find($id);
        
        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Item batch not found'
            ], 404);
        }

        // Add expiry info
        $batchData = $batch->toArray();
        $batchData['is_expired'] = $batch->isExpired();
        $batchData['days_until_expiry'] = $batch->daysUntilExpiry();

        return response()->json([
            'success' => true,
            'data' => $batchData
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $itemId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $itemId, $id)
    {
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }
        
        $batch = $item->batches()->find($id);
        
        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Item batch not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'batch_number' => 'required|string|max:50',
            'expiry_date' => 'nullable|date',
            'manufacturing_date' => 'nullable|date|before_or_equal:today',
            'lot_number' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $batch->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Item batch updated successfully',
            'data' => $batch
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $itemId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($itemId, $id)
    {
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }
        
        $batch = $item->batches()->find($id);
        
        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Item batch not found'
            ], 404);
        }

        // Check if the batch has stock transactions
        if ($batch->stockTransactions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete batch with stock transactions'
            ], 422);
        }

        $batch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item batch deleted successfully'
        ]);
    }
    
    /**
     * Get near expiry batches
     *
     * @param  int  $days
     * @return \Illuminate\Http\Response
     */
public function nearExpiry($days = 30)
{
    $days = (int) $days;
    $date = now()->addDays($days)->format('Y-m-d');
    
    $batches = ItemBatch::where('expiry_date', '<=', $date)
        ->where('expiry_date', '>=', now()->format('Y-m-d'))
        ->with('item')
        ->get();
        
    $batchesData = $batches->map(function ($batch) {
        return [
            'batch_id' => $batch->batch_id,
            'batch_number' => $batch->batch_number,
            'item_id' => $batch->item_id,
            'item_name' => $batch->item->name,
            'item_code' => $batch->item->item_code,
            'expiry_date' => $batch->expiry_date,
            'days_until_expiry' => $batch->daysUntilExpiry()
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $batchesData
    ]);
}
}
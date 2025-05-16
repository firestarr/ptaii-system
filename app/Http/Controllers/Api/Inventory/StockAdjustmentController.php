<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = StockAdjustment::with('adjustmentLines');
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by date range with validation
        if ($request->has('start_date') && !empty($request->start_date) && strtotime($request->start_date) !== false) {
            $query->where('adjustment_date', '>=', $request->start_date);
        }
        
        if ($request->has('end_date') && !empty($request->end_date) && strtotime($request->end_date) !== false) {
            $query->where('adjustment_date', '<=', $request->end_date);
        }
        
        // Pagination
        $perPage = $request->per_page ?? 15;
        $adjustments = $query->orderBy('adjustment_date', 'desc')
                          ->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $adjustments->items(),
            'meta' => [
                'current_page' => $adjustments->currentPage(),
                'from' => $adjustments->firstItem(),
                'to' => $adjustments->lastItem(),
                'total' => $adjustments->total(),
                'last_page' => $adjustments->lastPage(),
            ],
            'links' => [
                'prev' => $adjustments->previousPageUrl(),
                'next' => $adjustments->nextPageUrl(),
            ],
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
            'adjustment_date' => 'required|date',
            'adjustment_reason' => 'nullable|string',
            'reference_document' => 'nullable|string|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            //'lines.*.location_id' => 'nullable|exists:warehouse_locations,location_id',
            'lines.*.book_quantity' => 'required|numeric',
            'lines.*.adjusted_quantity' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Create the stock adjustment header
            $adjustment = StockAdjustment::create([
                'adjustment_date' => $request->adjustment_date,
                'adjustment_reason' => $request->adjustment_reason,
                'reference_document' => $request->reference_document,
                'status' => StockAdjustment::STATUS_DRAFT
            ]);
            
            // Create the adjustment lines
            foreach ($request->lines as $line) {
                // Calculate variance
                $variance = $line['adjusted_quantity'] - $line['book_quantity'];
                
                // Create adjustment line
                StockAdjustmentLine::create([
                    'adjustment_id' => $adjustment->adjustment_id,
                    'item_id' => $line['item_id'],
                    'warehouse_id' => $line['warehouse_id'],
                    //'location_id' => $line['location_id'] ?? null,
                    'book_quantity' => $line['book_quantity'],
                    'adjusted_quantity' => $line['adjusted_quantity'],
                    'variance' => $variance
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment created successfully',
                'data' => $adjustment->load('adjustmentLines')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock adjustment',
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
        $adjustment = StockAdjustment::with([
            'adjustmentLines.item', 
            'adjustmentLines.warehouse', 
            'adjustmentLines.location'
        ])->find($id);
        
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment not found'
            ], 404);
        }

        // Calculate total variance
        $adjustment->total_variance = $adjustment->total_variance;

        return response()->json([
            'success' => true,
            'data' => $adjustment
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
        $adjustment = StockAdjustment::find($id);
        
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment not found'
            ], 404);
        }
        
        // Only allow updates for draft adjustments
        if ($adjustment->status != StockAdjustment::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft adjustments can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'adjustment_date' => 'required|date',
            'adjustment_reason' => 'nullable|string',
            'reference_document' => 'nullable|string|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.line_id' => 'nullable|exists:stock_adjustment_lines,line_id',
            'lines.*.item_id' => 'required|exists:items,item_id',
            'lines.*.warehouse_id' => 'required|exists:warehouses,warehouse_id',
            //'lines.*.location_id' => 'nullable|exists:warehouse_locations,location_id',
            'lines.*.book_quantity' => 'required|numeric',
            'lines.*.adjusted_quantity' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Update adjustment header
            $adjustment->update([
                'adjustment_date' => $request->adjustment_date,
                'adjustment_reason' => $request->adjustment_reason,
                'reference_document' => $request->reference_document
            ]);
            
            // Get current line IDs
            $currentLineIds = $adjustment->adjustmentLines()->pluck('line_id')->toArray();
            $updatedLineIds = [];
            
            // Process adjustment lines
            foreach ($request->lines as $lineData) {
                $variance = $lineData['adjusted_quantity'] - $lineData['book_quantity'];
                
                if (isset($lineData['line_id'])) {
                    // Update existing line
                    $line = StockAdjustmentLine::find($lineData['line_id']);
                    if ($line && $line->adjustment_id == $adjustment->adjustment_id) {
                        $line->update([
                            'item_id' => $lineData['item_id'],
                            'warehouse_id' => $lineData['warehouse_id'],
                            //'location_id' => $lineData['location_id'] ?? null,
                            'book_quantity' => $lineData['book_quantity'],
                            'adjusted_quantity' => $lineData['adjusted_quantity'],
                            'variance' => $variance
                        ]);
                        
                        $updatedLineIds[] = $line->line_id;
                    }
                } else {
                    // Create new line
                    $line = StockAdjustmentLine::create([
                        'adjustment_id' => $adjustment->adjustment_id,
                        'item_id' => $lineData['item_id'],
                        'warehouse_id' => $lineData['warehouse_id'],
                        //'location_id' => $lineData['location_id'] ?? null,
                        'book_quantity' => $lineData['book_quantity'],
                        'adjusted_quantity' => $lineData['adjusted_quantity'],
                        'variance' => $variance
                    ]);
                    
                    $updatedLineIds[] = $line->line_id;
                }
            }
            
            // Delete lines that were not included in the update
            $linesToDelete = array_diff($currentLineIds, $updatedLineIds);
            if (!empty($linesToDelete)) {
                StockAdjustmentLine::whereIn('line_id', $linesToDelete)->delete();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment updated successfully',
                'data' => $adjustment->fresh()->load('adjustmentLines')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock adjustment',
                'error' => $e->getMessage()
            ], 500);
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
        $adjustment = StockAdjustment::find($id);
        
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment not found'
            ], 404);
        }
        
        // Only allow deleting draft adjustments
        if ($adjustment->status != StockAdjustment::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft adjustments can be deleted'
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Delete the adjustment lines first
            $adjustment->adjustmentLines()->delete();
            
            // Delete the adjustment header
            $adjustment->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stock adjustment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit adjustment for approval
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function submit($id)
    {
        $adjustment = StockAdjustment::find($id);
        
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment not found'
            ], 404);
        }
        
        // Only allow submitting draft adjustments
        if ($adjustment->status != StockAdjustment::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft adjustments can be submitted'
            ], 422);
        }
        
        // Check if adjustment has lines
        if ($adjustment->adjustmentLines()->count() == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot submit adjustment with no lines'
            ], 422);
        }
        
        $adjustment->status = StockAdjustment::STATUS_PENDING;
        $adjustment->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment submitted for approval',
            'data' => $adjustment
        ]);
    }
    
    /**
     * Approve adjustment
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve($id)
    {
        $adjustment = StockAdjustment::find($id);
        
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment not found'
            ], 404);
        }
        
        // Only allow approving pending adjustments
        if ($adjustment->status != StockAdjustment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending adjustments can be approved'
            ], 422);
        }
        
        $adjustment->status = StockAdjustment::STATUS_APPROVED;
        $adjustment->save();
        
        // Process the adjustment (create stock transactions)
        $processed = $adjustment->process();
        
        if (!$processed) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process stock adjustment'
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment approved and processed',
            'data' => $adjustment->fresh()
        ]);
    }
    
    /**
     * Reject adjustment
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject(Request $request, $id)
    {
        $adjustment = StockAdjustment::find($id);
        
        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment not found'
            ], 404);
        }
        
        // Only allow rejecting pending adjustments
        if ($adjustment->status != StockAdjustment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending adjustments can be rejected'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $adjustment->status = StockAdjustment::STATUS_REJECTED;
        $adjustment->adjustment_reason = $request->rejection_reason;
        $adjustment->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment rejected',
            'data' => $adjustment
        ]);
    }
}
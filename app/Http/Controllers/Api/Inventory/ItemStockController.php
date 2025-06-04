<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ItemStock;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ItemStockController extends Controller
{
    /**
     * Display a listing of item stock.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $itemStocks = ItemStock::with(['item', 'warehouse'])
            ->where('quantity', '>', 0)
            ->get();

        return response()->json(['data' => $itemStocks], 200);
    }

    /**
     * Mendapatkan stock untuk item tertentu di semua warehouse
     *
     * @param  int  $itemId
     * @return \Illuminate\Http\Response
     */
    public function getItemStock($itemId)
    {
        $item = Item::find($itemId);
        
        if (!$item) {
            return response()->json(['message' => 'Item tidak ditemukan'], 404);
        }
        
        $itemStocks = ItemStock::with('warehouse')
            ->where('item_id', $itemId)
            ->where('quantity', '>', 0)
            ->get();
            
        return response()->json([
            'data' => [
                'item_id' => $item->item_id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'total_stock' => $itemStocks->sum('quantity'),
                'warehouse_stocks' => $itemStocks->map(function($stock) {
                    return [
                        'warehouse_id' => $stock->warehouse_id,
                        'warehouse_name' => $stock->warehouse->name,
                        'quantity' => $stock->quantity,
                        'reserved_quantity' => $stock->reserved_quantity,
                        'available_quantity' => $stock->quantity - $stock->reserved_quantity
                    ];
                })
            ]
        ], 200);
    }

    /**
     * Mendapatkan stock untuk warehouse tertentu
     *
     * @param  int  $warehouseId
     * @return \Illuminate\Http\Response
     */
    public function getWarehouseStock($warehouseId)
    {
        $warehouse = Warehouse::find($warehouseId);
        
        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse tidak ditemukan'], 404);
        }
        
        $itemStocks = ItemStock::with('item')
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->get();
            
        return response()->json([
            'data' => [
                'warehouse_id' => $warehouse->warehouse_id,
                'warehouse_name' => $warehouse->name,
                'warehouse_code' => $warehouse->code,
                'item_stocks' => $itemStocks->map(function($stock) {
                    return [
                        'item_id' => $stock->item_id,
                        'item_code' => $stock->item->item_code,
                        'item_name' => $stock->item->name,
                        'quantity' => $stock->quantity,
                        'reserved_quantity' => $stock->reserved_quantity,
                        'available_quantity' => $stock->quantity - $stock->reserved_quantity
                    ];
                })
            ]
        ], 200);
    }

    /**
     * Memindahkan stock antar warehouse (Updated to use Odoo-style)
     */
    public function transferStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'from_warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'to_warehouse_id' => 'required|exists:warehouses,warehouse_id|different:from_warehouse_id',
            'quantity' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Cek ketersediaan stock di warehouse asal
            $fromStock = ItemStock::where('item_id', $request->item_id)
                ->where('warehouse_id', $request->from_warehouse_id)
                ->first();
                
            if (!$fromStock || $fromStock->quantity < $request->quantity) {
                return response()->json([
                    'message' => 'Stock tidak mencukupi di warehouse asal'
                ], 400);
            }
            
            // ===== UPDATED: Use Odoo-style stock transaction =====
            // Create single transfer transaction (like Odoo)
            $transaction = StockTransaction::create([
                'item_id' => $request->item_id,
                'warehouse_id' => $request->from_warehouse_id,
                'dest_warehouse_id' => $request->to_warehouse_id,
                'transaction_type' => StockTransaction::TYPE_TRANSFER,
                'move_type' => StockTransaction::MOVE_TYPE_INTERNAL,
                'quantity' => $request->quantity, // Always positive
                'transaction_date' => now(),
                'reference_document' => 'stock_transfer',
                'reference_number' => $request->reference_number ?? 'TR' . time(),
                'origin' => 'Manual Transfer',
                'batch_id' => null,
                'state' => StockTransaction::STATE_DRAFT,
                'notes' => $request->notes
            ]);
            
            // Auto-confirm the transaction to update stock
            $transaction->markAsDone();
            // ===== END UPDATE =====
            
            DB::commit();
            
            // Get updated stock information
            $fromStockUpdated = ItemStock::where('item_id', $request->item_id)
                ->where('warehouse_id', $request->from_warehouse_id)
                ->first();
                
            $toStockUpdated = ItemStock::where('item_id', $request->item_id)
                ->where('warehouse_id', $request->to_warehouse_id)
                ->first();
            
            return response()->json([
                'message' => 'Stock berhasil dipindahkan',
                'data' => [
                    'transaction_id' => $transaction->transaction_id,
                    'from_warehouse' => [
                        'warehouse_id' => $request->from_warehouse_id,
                        'remaining_quantity' => $fromStockUpdated->quantity ?? 0
                    ],
                    'to_warehouse' => [
                        'warehouse_id' => $request->to_warehouse_id,
                        'new_quantity' => $toStockUpdated->quantity ?? 0
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memindahkan stock', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Menyesuaikan (adjust) stock item di warehouse tertentu (Updated to use Odoo-style)
     */
    public function adjustStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'new_quantity' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Ambil data stock current
            $itemStock = ItemStock::firstOrNew([
                'item_id' => $request->item_id,
                'warehouse_id' => $request->warehouse_id
            ]);
            
            $oldQuantity = $itemStock->quantity ?? 0;
            $adjustmentQuantity = $request->new_quantity - $oldQuantity;
            
            // Only create transaction if there's actually an adjustment
            if ($adjustmentQuantity != 0) {
                // ===== UPDATED: Use Odoo-style stock transaction =====
                // Determine move type based on adjustment direction
                $moveType = $adjustmentQuantity > 0 ? 
                    StockTransaction::MOVE_TYPE_IN : 
                    StockTransaction::MOVE_TYPE_OUT;
                
                $transaction = StockTransaction::create([
                    'item_id' => $request->item_id,
                    'warehouse_id' => $request->warehouse_id,
                    'dest_warehouse_id' => null,
                    'transaction_type' => StockTransaction::TYPE_ADJUSTMENT,
                    'move_type' => $moveType,
                    'quantity' => abs($adjustmentQuantity), // Always positive
                    'transaction_date' => now(),
                    'reference_document' => 'stock_adjustment',
                    'reference_number' => $request->reference_number ?? 'ADJ' . time(),
                    'origin' => 'Manual Adjustment',
                    'batch_id' => null,
                    'state' => StockTransaction::STATE_DRAFT,
                    'notes' => $request->reason . ($request->notes ? ' - ' . $request->notes : '')
                ]);
                
                // Auto-confirm the transaction to update stock
                $transaction->markAsDone();
                // ===== END UPDATE =====
            }
            
            DB::commit();
            
            // Get updated stock
            $updatedStock = ItemStock::where('item_id', $request->item_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->first();
            
            return response()->json([
                'message' => 'Stock berhasil disesuaikan',
                'data' => [
                    'item_id' => $request->item_id,
                    'warehouse_id' => $request->warehouse_id,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $updatedStock->quantity ?? 0,
                    'adjustment' => $adjustmentQuantity,
                    'transaction_id' => isset($transaction) ? $transaction->transaction_id : null
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyesuaikan stock', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mereservasi stock untuk pemenuhan SO atau kebutuhan lain
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function reserveStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'quantity' => 'required|numeric|min:0.01',
            'reference_type' => 'required|string|max:50',
            'reference_id' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Cek ketersediaan stock
            $itemStock = ItemStock::where('item_id', $request->item_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->first();
                
            if (!$itemStock || ($itemStock->quantity - $itemStock->reserved_quantity) < $request->quantity) {
                return response()->json([
                    'message' => 'Stock yang tersedia tidak mencukupi untuk direservasi'
                ], 400);
            }
            
            // Update reserved quantity
            $itemStock->reserved_quantity += $request->quantity;
            $itemStock->save();
            
            // Buat reservation record (jika ada model untuk ini)
            // StockReservation::create([
            //     'item_id' => $request->item_id,
            //     'warehouse_id' => $request->warehouse_id,
            //     'quantity' => $request->quantity,
            //     'reference_type' => $request->reference_type,
            //     'reference_id' => $request->reference_id,
            //     'reservation_date' => now()
            // ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Stock berhasil direservasi',
                'data' => [
                    'item_id' => $itemStock->item_id,
                    'warehouse_id' => $itemStock->warehouse_id,
                    'total_quantity' => $itemStock->quantity,
                    'reserved_quantity' => $itemStock->reserved_quantity,
                    'available_quantity' => $itemStock->quantity - $itemStock->reserved_quantity
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mereservasi stock', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Batalkan reservasi stock
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function releaseReservation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,item_id',
            'warehouse_id' => 'required|exists:warehouses,warehouse_id',
            'quantity' => 'required|numeric|min:0.01',
            'reference_type' => 'required|string|max:50',
            'reference_id' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Ambil data stock
            $itemStock = ItemStock::where('item_id', $request->item_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->first();
                
            if (!$itemStock || $itemStock->reserved_quantity < $request->quantity) {
                return response()->json([
                    'message' => 'Jumlah reservasi tidak mencukupi untuk dilepaskan'
                ], 400);
            }
            
            // Update reserved quantity
            $itemStock->reserved_quantity -= $request->quantity;
            $itemStock->save();
            
            // Hapus atau update reservation record (jika ada model untuk ini)
            // StockReservation::where([
            //     'item_id' => $request->item_id,
            //     'warehouse_id' => $request->warehouse_id,
            //     'reference_type' => $request->reference_type,
            //     'reference_id' => $request->reference_id,
            // ])->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Reservasi stock berhasil dilepaskan',
                'data' => [
                    'item_id' => $itemStock->item_id,
                    'warehouse_id' => $itemStock->warehouse_id,
                    'total_quantity' => $itemStock->quantity,
                    'reserved_quantity' => $itemStock->reserved_quantity,
                    'available_quantity' => $itemStock->quantity - $itemStock->reserved_quantity
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal melepaskan reservasi stock', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Display items with negative stock.
     *
     * @return \Illuminate\Http\Response
     */
    public function getNegativeStocks()
    {
        $negativeStocks = ItemStock::with(['item', 'warehouse'])
            ->where('quantity', '<', 0)
            ->get();

        $result = $negativeStocks->map(function($stock) {
            return [
                'stock_id' => $stock->stock_id,
                'item_id' => $stock->item_id,
                'item_code' => $stock->item->item_code,
                'item_name' => $stock->item->name,
                'warehouse_id' => $stock->warehouse_id,
                'warehouse_name' => $stock->warehouse->name,
                'quantity' => $stock->quantity,
                'reserved_quantity' => $stock->reserved_quantity,
                'available_quantity' => $stock->quantity - $stock->reserved_quantity
            ];
        });

        return response()->json([
            'data' => $result,
            'count' => count($result)
        ], 200);
    }

    /**
     * Get summary of negative stock value.
     *
     * @return \Illuminate\Http\Response
     */
    public function getNegativeStockSummary()
    {
        // Get all negative stocks
        $negativeStocks = ItemStock::with(['item', 'warehouse'])
            ->where('quantity', '<', 0)
            ->get();
            
        // Total negative quantity
        $totalNegativeQty = $negativeStocks->sum('quantity');
        
        // Total negative value (quantity * cost price)
        $totalNegativeValue = 0;
        foreach ($negativeStocks as $stock) {
            $cost = $stock->item->cost_price ?? 0;
            $totalNegativeValue += $stock->quantity * $cost;
        }
        
        // Count by warehouse
        $warehouseCounts = [];
        foreach ($negativeStocks->groupBy('warehouse_id') as $warehouseId => $stocks) {
            $warehouse = $stocks->first()->warehouse;
            $warehouseCounts[] = [
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouse->name,
                'item_count' => $stocks->count(),
                'total_negative_quantity' => $stocks->sum('quantity')
            ];
        }
        
        return response()->json([
            'data' => [
                'total_negative_items' => $negativeStocks->count(),
                'total_negative_quantity' => $totalNegativeQty,
                'total_negative_value' => $totalNegativeValue,
                'warehouse_summary' => $warehouseCounts
            ]
        ], 200);
    }
}
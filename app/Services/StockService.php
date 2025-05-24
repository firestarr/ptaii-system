<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransaction;
use App\Models\ItemBatch;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Increase stock level for an item (Odoo-style)
     * 
     * @param int $itemId
     * @param int $warehouseId
     * @param int|null $locationId (deprecated - kept for backward compatibility)
     * @param float $quantity
     * @param string $transactionType
     * @param string $referenceNumber
     * @param string|null $batchNumber
     * @param bool $autoConfirm
     * @return StockTransaction
     */
    public function increaseStock(
        $itemId, 
        $warehouseId, 
        $locationId = null, 
        $quantity, 
        $transactionType, 
        $referenceNumber,
        $batchNumber = null,
        $autoConfirm = true
    ) {
        // Create or update batch record if batch number is provided
        $batchId = null;
        if ($batchNumber) {
            $batch = ItemBatch::firstOrCreate(
                ['item_id' => $itemId, 'batch_number' => $batchNumber],
                [
                    'expiry_date' => null, 
                    'manufacturing_date' => null, 
                    'lot_number' => null
                ]
            );
            $batchId = $batch->batch_id;
        }
        
        // Determine move type based on transaction type
        $moveType = $this->getMoveTypeForIncrease($transactionType);
        
        // Create stock transaction (Odoo-style)
        $transaction = StockTransaction::create([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'dest_warehouse_id' => null, // For simple incoming moves
            'transaction_type' => $transactionType,
            'move_type' => $moveType,
            'quantity' => $quantity, // Always positive
            'transaction_date' => now(),
            'reference_document' => $transactionType,
            'reference_number' => $referenceNumber,
            'batch_id' => $batchId,
            'state' => StockTransaction::STATE_DRAFT,
            'origin' => "Stock increase via {$transactionType}"
        ]);
        
        // Auto-confirm if requested
        if ($autoConfirm) {
            $transaction->markAsDone();
        }
        
        return $transaction;
    }
    
    /**
     * Decrease stock level for an item (Odoo-style)
     * 
     * @param int $itemId
     * @param int $warehouseId
     * @param int|null $locationId (deprecated - kept for backward compatibility)
     * @param float $quantity
     * @param string $transactionType
     * @param string $referenceNumber
     * @param string|null $batchNumber
     * @param bool $autoConfirm
     * @param bool $allowNegative
     * @return StockTransaction
     */
    public function decreaseStock(
        $itemId, 
        $warehouseId, 
        $locationId = null, 
        $quantity, 
        $transactionType, 
        $referenceNumber,
        $batchNumber = null,
        $autoConfirm = true,
        $allowNegative = false
    ) {
        // Check stock availability if not allowing negative
        if (!$allowNegative) {
            $availableStock = $this->getAvailableStock($itemId, $warehouseId);
            if ($availableStock < $quantity) {
                throw new \Exception("Insufficient stock. Available: {$availableStock}, Requested: {$quantity}");
            }
        }
        
        // Find batch ID if batch number is provided
        $batchId = null;
        if ($batchNumber) {
            $batch = ItemBatch::where('item_id', $itemId)
                          ->where('batch_number', $batchNumber)
                          ->first();
            
            if ($batch) {
                $batchId = $batch->batch_id;
            }
        }
        
        // Determine move type based on transaction type
        $moveType = $this->getMoveTypeForDecrease($transactionType);
        
        // Create stock transaction (Odoo-style with positive quantity)
        $transaction = StockTransaction::create([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'dest_warehouse_id' => null, // For simple outgoing moves
            'transaction_type' => $transactionType,
            'move_type' => $moveType,
            'quantity' => $quantity, // Always positive (direction determined by move_type)
            'transaction_date' => now(),
            'reference_document' => $transactionType,
            'reference_number' => $referenceNumber,
            'batch_id' => $batchId,
            'state' => StockTransaction::STATE_DRAFT,
            'origin' => "Stock decrease via {$transactionType}"
        ]);
        
        // Auto-confirm if requested
        if ($autoConfirm) {
            $transaction->markAsDone();
        }
        
        return $transaction;
    }
    
    /**
     * Transfer stock between warehouses (New Odoo-style method)
     * 
     * @param int $itemId
     * @param int $fromWarehouseId
     * @param int $toWarehouseId
     * @param float $quantity
     * @param string $referenceNumber
     * @param string|null $batchNumber
     * @param bool $autoConfirm
     * @return StockTransaction
     */
    public function transferStock(
        $itemId,
        $fromWarehouseId,
        $toWarehouseId,
        $quantity,
        $referenceNumber,
        $batchNumber = null,
        $autoConfirm = true
    ) {
        // Validate warehouses are different
        if ($fromWarehouseId === $toWarehouseId) {
            throw new \Exception("Source and destination warehouses must be different");
        }
        
        // Check stock availability
        $availableStock = $this->getAvailableStock($itemId, $fromWarehouseId);
        if ($availableStock < $quantity) {
            throw new \Exception("Insufficient stock for transfer. Available: {$availableStock}, Requested: {$quantity}");
        }
        
        // Find batch ID if batch number is provided
        $batchId = null;
        if ($batchNumber) {
            $batch = ItemBatch::where('item_id', $itemId)
                          ->where('batch_number', $batchNumber)
                          ->first();
            
            if ($batch) {
                $batchId = $batch->batch_id;
            }
        }
        
        // Create single transfer transaction (Odoo-style)
        $transaction = StockTransaction::create([
            'item_id' => $itemId,
            'warehouse_id' => $fromWarehouseId,
            'dest_warehouse_id' => $toWarehouseId,
            'transaction_type' => StockTransaction::TYPE_TRANSFER,
            'move_type' => StockTransaction::MOVE_TYPE_INTERNAL,
            'quantity' => $quantity, // Always positive
            'transaction_date' => now(),
            'reference_document' => 'stock_transfer',
            'reference_number' => $referenceNumber,
            'batch_id' => $batchId,
            'state' => StockTransaction::STATE_DRAFT,
            'origin' => "Transfer from warehouse {$fromWarehouseId} to {$toWarehouseId}"
        ]);
        
        // Auto-confirm if requested
        if ($autoConfirm) {
            $transaction->markAsDone();
        }
        
        return $transaction;
    }
    
    /**
     * Adjust stock to a specific quantity
     * 
     * @param int $itemId
     * @param int $warehouseId
     * @param float $newQuantity
     * @param string $reason
     * @param string $referenceNumber
     * @param bool $autoConfirm
     * @return StockTransaction|null
     */
    public function adjustStock(
        $itemId,
        $warehouseId,
        $newQuantity,
        $reason,
        $referenceNumber,
        $autoConfirm = true
    ) {
        $currentQuantity = $this->getWarehouseStock($itemId, $warehouseId);
        $adjustmentQuantity = $newQuantity - $currentQuantity;
        
        // No adjustment needed
        if ($adjustmentQuantity == 0) {
            return null;
        }
        
        // Determine move type based on adjustment direction
        $moveType = $adjustmentQuantity > 0 ? 
            StockTransaction::MOVE_TYPE_IN : 
            StockTransaction::MOVE_TYPE_OUT;
        
        $transaction = StockTransaction::create([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'dest_warehouse_id' => null,
            'transaction_type' => StockTransaction::TYPE_ADJUSTMENT,
            'move_type' => $moveType,
            'quantity' => abs($adjustmentQuantity), // Always positive
            'transaction_date' => now(),
            'reference_document' => 'stock_adjustment',
            'reference_number' => $referenceNumber,
            'state' => StockTransaction::STATE_DRAFT,
            'origin' => $reason,
            'notes' => "Adjustment from {$currentQuantity} to {$newQuantity}: {$reason}"
        ]);
        
        // Auto-confirm if requested
        if ($autoConfirm) {
            $transaction->markAsDone();
        }
        
        return $transaction;
    }
    
    /**
     * Get current total stock for an item across all warehouses
     * 
     * @param int $itemId
     * @return float
     */
    public function getCurrentStock($itemId)
    {
        $item = Item::find($itemId);
        return $item ? $item->current_stock : 0;
    }
    
    /**
     * Get stock quantity for an item in a specific warehouse
     * 
     * @param int $itemId
     * @param int $warehouseId
     * @return float
     */
    public function getWarehouseStock($itemId, $warehouseId)
    {
        $itemStock = ItemStock::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->first();
        
        return $itemStock ? $itemStock->quantity : 0;
    }
    
    /**
     * Get available stock (total - reserved) for an item in a warehouse
     * 
     * @param int $itemId
     * @param int $warehouseId
     * @return float
     */
    public function getAvailableStock($itemId, $warehouseId)
    {
        $itemStock = ItemStock::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->first();
        
        if (!$itemStock) {
            return 0;
        }
        
        return $itemStock->quantity - $itemStock->reserved_quantity;
    }
    
    /**
     * Get stock by warehouse for an item (Updated for new system)
     * 
     * @param int $itemId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStockByWarehouse($itemId)
    {
        return ItemStock::where('item_id', $itemId)
            ->where('quantity', '>', 0)
            ->with(['warehouse'])
            ->get()
            ->map(function ($stock) {
                return [
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse->name,
                    'warehouse_code' => $stock->warehouse->code,
                    'quantity' => $stock->quantity,
                    'reserved_quantity' => $stock->reserved_quantity,
                    'available_quantity' => $stock->quantity - $stock->reserved_quantity
                ];
            });
    }
    
    /**
     * Get stock by batch for an item
     * 
     * @param int $itemId
     * @param int|null $warehouseId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStockByBatch($itemId, $warehouseId = null)
    {
        $query = StockTransaction::where('item_id', $itemId)
            ->whereNotNull('batch_id')
            ->where('state', StockTransaction::STATE_DONE);
            
        if ($warehouseId) {
            $query->where(function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                  ->orWhere('dest_warehouse_id', $warehouseId);
            });
        }
        
        $batchTransactions = $query->with(['batch', 'warehouse', 'destWarehouse'])
            ->get()
            ->groupBy('batch_id');
        
        $batchStocks = [];
        
        foreach ($batchTransactions as $batchId => $transactions) {
            $batch = $transactions->first()->batch;
            $totalQuantity = 0;
            
            foreach ($transactions as $transaction) {
                if ($transaction->isTransfer()) {
                    // For transfers, check if this warehouse is source or destination
                    if ($warehouseId) {
                        if ($transaction->warehouse_id == $warehouseId) {
                            $totalQuantity -= $transaction->quantity; // Outgoing
                        } elseif ($transaction->dest_warehouse_id == $warehouseId) {
                            $totalQuantity += $transaction->quantity; // Incoming
                        }
                    }
                } else {
                    // For other transactions, use stock direction
                    $totalQuantity += $transaction->quantity * $transaction->stock_direction;
                }
            }
            
            if ($totalQuantity > 0) {
                $batchStocks[] = [
                    'batch_id' => $batchId,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiry_date,
                    'manufacturing_date' => $batch->manufacturing_date,
                    'lot_number' => $batch->lot_number,
                    'total_quantity' => $totalQuantity,
                    'is_expired' => $batch->isExpired(),
                    'days_until_expiry' => $batch->daysUntilExpiry()
                ];
            }
        }
        
        return collect($batchStocks);
    }
    
    /**
     * Reserve stock for future consumption
     * 
     * @param int $itemId
     * @param int $warehouseId
     * @param float $quantity
     * @param string $reference
     * @return bool
     */
    public function reserveStock($itemId, $warehouseId, $quantity, $reference)
    {
        $itemStock = ItemStock::firstOrNew([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId
        ]);
        
        if (!$itemStock->exists) {
            $itemStock->quantity = 0;
            $itemStock->reserved_quantity = 0;
            $itemStock->save();
        }
        
        $availableQuantity = $itemStock->quantity - $itemStock->reserved_quantity;
        
        if ($availableQuantity < $quantity) {
            throw new \Exception("Insufficient available stock for reservation. Available: {$availableQuantity}, Requested: {$quantity}");
        }
        
        $itemStock->increment('reserved_quantity', $quantity);
        
        return true;
    }
    
    /**
     * Release reserved stock
     * 
     * @param int $itemId
     * @param int $warehouseId
     * @param float $quantity
     * @return bool
     */
    public function releaseReservation($itemId, $warehouseId, $quantity)
    {
        $itemStock = ItemStock::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->first();
            
        if (!$itemStock || $itemStock->reserved_quantity < $quantity) {
            throw new \Exception("Invalid reservation release request. Reserved: " . 
                ($itemStock ? $itemStock->reserved_quantity : 0) . ", Requested: {$quantity}");
        }
        
        $itemStock->decrement('reserved_quantity', $quantity);
        
        return true;
    }
    
    /**
     * Get stock movements for an item within date range
     * 
     * @param int $itemId
     * @param string $startDate
     * @param string $endDate
     * @param int|null $warehouseId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStockMovements($itemId, $startDate, $endDate, $warehouseId = null)
    {
        $query = StockTransaction::where('item_id', $itemId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('state', StockTransaction::STATE_DONE)
            ->with(['warehouse', 'destWarehouse', 'batch']);
            
        if ($warehouseId) {
            $query->where(function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                  ->orWhere('dest_warehouse_id', $warehouseId);
            });
        }
        
        return $query->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get()
            ->map(function($transaction) use ($warehouseId) {
                $data = [
                    'transaction_id' => $transaction->transaction_id,
                    'transaction_date' => $transaction->transaction_date,
                    'transaction_type' => $transaction->transaction_type,
                    'move_type' => $transaction->move_type,
                    'quantity' => $transaction->quantity,
                    'reference_number' => $transaction->reference_number,
                    'origin' => $transaction->origin,
                    'warehouse_name' => $transaction->warehouse->name,
                    'dest_warehouse_name' => $transaction->destWarehouse ? $transaction->destWarehouse->name : null,
                    'batch_number' => $transaction->batch ? $transaction->batch->batch_number : null,
                    'description' => $transaction->description
                ];
                
                // Calculate effect on specific warehouse if filtering by warehouse
                if ($warehouseId && $transaction->isTransfer()) {
                    if ($transaction->warehouse_id == $warehouseId) {
                        $data['warehouse_effect'] = -$transaction->quantity; // Outgoing
                    } elseif ($transaction->dest_warehouse_id == $warehouseId) {
                        $data['warehouse_effect'] = $transaction->quantity; // Incoming
                    } else {
                        $data['warehouse_effect'] = 0;
                    }
                } elseif ($warehouseId && $transaction->warehouse_id == $warehouseId) {
                    $data['warehouse_effect'] = $transaction->quantity * $transaction->stock_direction;
                } else {
                    $data['warehouse_effect'] = $transaction->quantity * $transaction->stock_direction;
                }
                
                return $data;
            });
    }
    
    /**
     * Calculate projected stock considering pending moves
     * 
     * @param int $itemId
     * @param int $warehouseId
     * @return float
     */
    public function getProjectedStock($itemId, $warehouseId)
    {
        $currentStock = $this->getWarehouseStock($itemId, $warehouseId);
        
        // Add pending incoming moves (transfers to this warehouse)
        $pendingIn = StockTransaction::where('item_id', $itemId)
            ->where('dest_warehouse_id', $warehouseId)
            ->where('state', StockTransaction::STATE_DRAFT)
            ->sum('quantity');
            
        // Subtract pending outgoing moves
        $pendingOut = StockTransaction::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('state', StockTransaction::STATE_DRAFT)
            ->where(function($q) {
                $q->where('move_type', StockTransaction::MOVE_TYPE_OUT)
                  ->orWhere('move_type', StockTransaction::MOVE_TYPE_INTERNAL);
            })
            ->sum('quantity');
        
        return $currentStock + $pendingIn - $pendingOut;
    }
    
    /**
     * Get comprehensive stock summary for an item
     * 
     * @param int $itemId
     * @return array
     */
    public function getItemStockSummary($itemId)
    {
        $item = Item::find($itemId);
        if (!$item) {
            throw new \Exception("Item not found");
        }
        
        $warehouseStocks = $this->getStockByWarehouse($itemId);
        $batchStocks = $this->getStockByBatch($itemId);
        
        // Calculate totals
        $totalQuantity = $warehouseStocks->sum('quantity');
        $totalReserved = $warehouseStocks->sum('reserved_quantity');
        $totalAvailable = $warehouseStocks->sum('available_quantity');
        
        // Get pending moves
        $pendingMoves = StockTransaction::where('item_id', $itemId)
            ->where('state', StockTransaction::STATE_DRAFT)
            ->with(['warehouse', 'destWarehouse'])
            ->get()
            ->map(function($move) {
                return [
                    'transaction_id' => $move->transaction_id,
                    'move_type' => $move->move_type,
                    'quantity' => $move->quantity,
                    'warehouse_name' => $move->warehouse->name,
                    'dest_warehouse_name' => $move->destWarehouse ? $move->destWarehouse->name : null,
                    'reference_number' => $move->reference_number,
                    'description' => $move->description
                ];
            });
        
        return [
            'item_id' => $itemId,
            'item_code' => $item->item_code,
            'item_name' => $item->name,
            'total_quantity' => $totalQuantity,
            'total_reserved' => $totalReserved,
            'total_available' => $totalAvailable,
            'stock_status' => $item->stock_status,
            'warehouse_stocks' => $warehouseStocks,
            'batch_stocks' => $batchStocks,
            'pending_moves' => $pendingMoves
        ];
    }
    
    /**
     * Get move type for stock increase operations
     * 
     * @param string $transactionType
     * @return string
     */
    private function getMoveTypeForIncrease($transactionType)
    {
        switch ($transactionType) {
            case 'receive':
            case 'goods_receipt':
            case 'return':
            case 'production_output':
                return StockTransaction::MOVE_TYPE_IN;
            case 'adjustment':
                return StockTransaction::MOVE_TYPE_IN; // Positive adjustment
            default:
                return StockTransaction::MOVE_TYPE_IN;
        }
    }
    
    /**
     * Get move type for stock decrease operations
     * 
     * @param string $transactionType
     * @return string
     */
    private function getMoveTypeForDecrease($transactionType)
    {
        switch ($transactionType) {
            case 'issue':
            case 'delivery':
            case 'consumption':
            case 'scrap':
                return StockTransaction::MOVE_TYPE_OUT;
            case 'adjustment':
                return StockTransaction::MOVE_TYPE_OUT; // Negative adjustment
            default:
                return StockTransaction::MOVE_TYPE_OUT;
        }
    }
}
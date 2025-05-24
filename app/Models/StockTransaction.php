<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class StockTransaction extends Model
{
    use HasFactory;

    protected $table = 'stock_transactions';
    protected $primaryKey = 'transaction_id';
    protected $fillable = [
        'item_id', 
        'warehouse_id',
        'dest_warehouse_id', // NEW: destination warehouse for transfers
        'transaction_type', 
        'move_type', // NEW: in, out, internal
        'quantity', 
        'transaction_date', 
        'reference_document',
        'reference_number',
        'origin', // NEW: source document reference
        'batch_id',
        'state', // NEW: draft, confirmed, done, cancelled
        'notes'
    ];

    protected $dates = [
        'transaction_date',
    ];

    // Transaction types
    const TYPE_RECEIVE = 'receive';
    const TYPE_ISSUE = 'issue';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_RETURN = 'return';
    const TYPE_MANUFACTURING = 'manufacturing';
    
    // Move types (like Odoo)
    const MOVE_TYPE_IN = 'in';        // Incoming to warehouse
    const MOVE_TYPE_OUT = 'out';      // Outgoing from warehouse  
    const MOVE_TYPE_INTERNAL = 'internal'; // Internal transfer
    
    // States (like Odoo)
    const STATE_DRAFT = 'draft';
    const STATE_CONFIRMED = 'confirmed';
    const STATE_DONE = 'done';
    const STATE_CANCELLED = 'cancelled';
    
    /**
     * Get the item for this transaction
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    /**
     * Get the source warehouse for this transaction
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }
    
    /**
     * Get the destination warehouse for this transaction (for transfers)
     */
    public function destWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'dest_warehouse_id', 'warehouse_id');
    }

    /**
     * Get the batch for this transaction
     */
    public function batch()
    {
        return $this->belongsTo(ItemBatch::class, 'batch_id', 'batch_id');
    }

    /**
     * Scope for incoming moves (receiving stock)
     */
    public function scopeIncoming($query)
    {
        return $query->where('move_type', self::MOVE_TYPE_IN);
    }

    /**
     * Scope for outgoing moves (issuing stock)
     */
    public function scopeOutgoing($query)
    {
        return $query->where('move_type', self::MOVE_TYPE_OUT);
    }
    
    /**
     * Scope for internal moves (transfers)
     */
    public function scopeInternal($query)
    {
        return $query->where('move_type', self::MOVE_TYPE_INTERNAL);
    }
    
    /**
     * Scope for moves in specific state
     */
    public function scopeInState($query, $state)
    {
        return $query->where('state', $state);
    }
    
    /**
     * Scope for done/completed moves
     */
    public function scopeDone($query)
    {
        return $query->where('state', self::STATE_DONE);
    }
    
    /**
     * Check if this is a transfer move
     */
    public function isTransfer()
    {
        return $this->move_type === self::MOVE_TYPE_INTERNAL && !is_null($this->dest_warehouse_id);
    }
    
    /**
     * Check if this is an incoming move
     */
    public function isIncoming()
    {
        return $this->move_type === self::MOVE_TYPE_IN;
    }
    
    /**
     * Check if this is an outgoing move
     */
    public function isOutgoing()
    {
        return $this->move_type === self::MOVE_TYPE_OUT;
    }
    
    /**
     * Mark the move as done and update stock
     */
    public function markAsDone()
    {
        if ($this->state === self::STATE_DONE) {
            return false;
        }
        
        \DB::transaction(function () {
            // Update state
            $this->update(['state' => self::STATE_DONE]);
            
            // Update stock based on move type
            if ($this->isTransfer()) {
                // For transfers: decrease source, increase destination
                $this->updateStockForTransfer();
            } elseif ($this->isIncoming()) {
                // For incoming: increase warehouse stock
                $this->updateStockForIncoming();
            } elseif ($this->isOutgoing()) {
                // For outgoing: decrease warehouse stock
                $this->updateStockForOutgoing();
            }
        });
        
        return true;
    }
    
    /**
     * Update stock for transfer moves
     */
    protected function updateStockForTransfer()
    {
        // Decrease stock in source warehouse
        $sourceStock = ItemStock::firstOrNew([
            'item_id' => $this->item_id,
            'warehouse_id' => $this->warehouse_id
        ]);
        
        if (!$sourceStock->exists) {
            $sourceStock->quantity = 0;
            $sourceStock->reserved_quantity = 0;
        }
        
        $sourceStock->quantity -= $this->quantity;
        $sourceStock->save();
        
        // Increase stock in destination warehouse
        $destStock = ItemStock::firstOrNew([
            'item_id' => $this->item_id,
            'warehouse_id' => $this->dest_warehouse_id
        ]);
        
        if (!$destStock->exists) {
            $destStock->quantity = 0;
            $destStock->reserved_quantity = 0;
        }
        
        $destStock->quantity += $this->quantity;
        $destStock->save();
    }
    
    /**
     * Update stock for incoming moves
     */
    protected function updateStockForIncoming()
    {
        $stock = ItemStock::firstOrNew([
            'item_id' => $this->item_id,
            'warehouse_id' => $this->warehouse_id
        ]);
        
        if (!$stock->exists) {
            $stock->quantity = 0;
            $stock->reserved_quantity = 0;
        }
        
        $stock->quantity += $this->quantity;
        $stock->save();
        
        // Update item's total stock
        $this->item->increment('current_stock', $this->quantity);
    }
    
    /**
     * Update stock for outgoing moves
     */
    protected function updateStockForOutgoing()
    {
        $stock = ItemStock::firstOrNew([
            'item_id' => $this->item_id,
            'warehouse_id' => $this->warehouse_id
        ]);
        
        if (!$stock->exists) {
            $stock->quantity = 0;
            $stock->reserved_quantity = 0;
        }
        
        $stock->quantity -= $this->quantity;
        $stock->save();
        
        // Update item's total stock
        $this->item->decrement('current_stock', $this->quantity);
    }
    
    /**
     * Cancel the move
     */
    public function cancel()
    {
        if ($this->state === self::STATE_DONE) {
            throw new \Exception('Cannot cancel a completed move');
        }
        
        $this->update(['state' => self::STATE_CANCELLED]);
        
        return true;
    }
    
    /**
     * Get the effective direction for stock calculation
     * Returns 1 for increases, -1 for decreases
     */
    public function getStockDirectionAttribute()
    {
        if ($this->isIncoming()) {
            return 1; // Increases stock
        } elseif ($this->isOutgoing()) {
            return -1; // Decreases stock
        } else {
            // For transfers, direction depends on which warehouse we're looking at
            return 0; // Neutral (handled separately)
        }
    }
    
    /**
     * Get human readable move description
     */
    public function getDescriptionAttribute()
    {
        if ($this->isTransfer()) {
            return "Transfer from {$this->warehouse->name} to {$this->destWarehouse->name}";
        } elseif ($this->isIncoming()) {
            return "Receive into {$this->warehouse->name}";
        } elseif ($this->isOutgoing()) {
            return "Issue from {$this->warehouse->name}";
        }
        
        return ucfirst($this->transaction_type);
    }
}
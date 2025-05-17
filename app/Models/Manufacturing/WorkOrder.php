<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Item;

class WorkOrder extends Model
{
    use HasFactory;

    protected $table = 'work_orders';
    protected $primaryKey = 'wo_id';
    public $timestamps = false;

    protected $fillable = [
        'wo_number',
        'wo_date',
        'item_id',
        'bom_id',
        'routing_id',
        'planned_quantity',
        'planned_start_date',
        'planned_end_date',
        'status',
    ];

    protected $casts = [
        'wo_date' => 'date',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
    ];

    /**
     * Get the item that owns the work order.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    /**
     * Get the BOM that owns the work order.
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(BOM::class, 'bom_id', 'bom_id');
    }

    /**
     * Get the routing that owns the work order.
     */
    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id', 'routing_id');
    }

    /**
     * Get the work order operations for the work order.
     */
    public function workOrderOperations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class, 'wo_id', 'wo_id');
    }

    /**
     * Get the production orders for the work order.
     */
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'wo_id', 'wo_id');
    }
}

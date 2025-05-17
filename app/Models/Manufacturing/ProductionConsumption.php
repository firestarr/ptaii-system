<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Item;
use App\Models\Warehouse;

class ProductionConsumption extends Model
{
    use HasFactory;

    protected $table = 'production_consumptions';
    protected $primaryKey = 'consumption_id';
    public $timestamps = false;

    protected $fillable = [
        'production_id',
        'item_id',
        'planned_quantity',
        'actual_quantity',
        'variance',
        'warehouse_id',
        //'location_id',
    ];

    /**
     * Get the production order that owns the production consumption.
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_id', 'production_id');
    }

    /**
     * Get the item that owns the production consumption.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    /**
     * Get the warehouse that owns the production consumption.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    /**
     * Get the warehouse location that owns the production consumption.
     */
    // public function warehouseLocation(): BelongsTo
    // {
    //     return $this->belongsTo(WarehouseLocation::class, 'location_id', 'location_id');
    // }
}
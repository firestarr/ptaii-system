<?php

namespace App\Models\Sales;

use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryLine extends Model
{
    use HasFactory;

    protected $table = 'DeliveryLine';
    protected $primaryKey = 'line_id';
    public $timestamps = false;
    
    protected $fillable = [
        'delivery_id',
        'so_line_id',
        'item_id',
        'delivered_quantity',
        'warehouse_id',
        'batch_number'
    ];

    /**
     * Get the delivery that owns the delivery line.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    /**
     * Get the sales order line that owns the delivery line.
     */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SOLine::class, 'so_line_id');
    }

    /**
     * Get the item that the delivery line belongs to.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Get the warehouse that the delivery line belongs to.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
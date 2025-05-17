<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\UnitOfMeasure;
use App\Models\Manufacturing\WorkOrderOperation;
use App\Models\Manufacturing\WorkCenter;

class RoutingOperation extends Model
{
    use HasFactory;

    protected $table = 'routing_operations';
    protected $primaryKey = 'operation_id';
    public $timestamps = false;

    protected $fillable = [
        'routing_id',
        'workcenter_id',
        'operation_name',
        'sequence',
        'setup_time',
        'run_time',
        'uom_id',
        'labor_cost',
        'overhead_cost',
    ];

    /**
     * Get the routing that owns the routing operation.
     */
    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id', 'routing_id');
    }

    /**
     * Get the work center that owns the routing operation.
     */
    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'workcenter_id', 'workcenter_id');
    }

    /**
     * Get the unit of measure that owns the routing operation.
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id', 'uom_id');
    }

    /**
     * Get the work order operations for the routing operation.
     */
    public function workOrderOperations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class, 'routing_operation_id', 'operation_id');
    }
}
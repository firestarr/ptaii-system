<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderOperation extends Model
{
    use HasFactory;

    protected $table = 'work_order_operations';
    protected $primaryKey = 'operation_id';
    public $timestamps = false;

    protected $fillable = [
        'wo_id',
        'routing_operation_id',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'actual_labor_time',
        'actual_machine_time',
        'status',
    ];

    protected $casts = [
        'scheduled_start' => 'date',
        'scheduled_end' => 'date',
        'actual_start' => 'date',
        'actual_end' => 'date',
    ];

    /**
     * Get the work order that owns the work order operation.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'wo_id', 'wo_id');
    }

    /**
     * Get the routing operation that owns the work order operation.
     */
    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id', 'operation_id');
    }
}
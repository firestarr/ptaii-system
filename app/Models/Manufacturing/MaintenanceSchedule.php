<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceSchedule extends Model
{
    use HasFactory;

    protected $table = 'maintenance_schedules';
    protected $primaryKey = 'schedule_id';
    public $timestamps = false;

    protected $fillable = [
        'workcenter_id',
        'maintenance_type',
        'planned_date',
        'actual_date',
        'status',
        'notes',
    ];


    protected $casts = [
        'planned_date' => 'date',
        'actual_date' => 'date',
    ];

    /**
     * Get the work center that owns the maintenance schedule.
     */
    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'workcenter_id', 'workcenter_id');
    }
}
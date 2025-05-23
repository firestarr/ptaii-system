<?php
// app/Models/MaterialPlan.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Manufacturing\BOM;

class MaterialPlan extends Model
{
    use HasFactory;

    protected $table = 'material_plans';
    protected $primaryKey = 'plan_id';
    
    protected $fillable = [
        'item_id',
        'planning_period',
        'material_type',
        'bom_id',
        'forecast_quantity',
        'available_stock',
        'wip_stock',
        'buffer_percentage',
        'buffer_quantity',
        'net_requirement',
        'planned_order_quantity',
        'status'
    ];

    protected $casts = [
        'planning_period' => 'date',
        'forecast_quantity' => 'float',
        'available_stock' => 'float',
        'wip_stock' => 'float',
        'buffer_percentage' => 'float',
        'buffer_quantity' => 'float',
        'net_requirement' => 'float',
        'planned_order_quantity' => 'float',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
    
    public function bom()
    {
        return $this->belongsTo(BOM::class, 'bom_id');
    }
}
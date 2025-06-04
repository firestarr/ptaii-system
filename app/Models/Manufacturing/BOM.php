<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Item;
use App\Models\UnitOfMeasure;

class BOM extends Model
{
    use HasFactory;

    protected $table = 'boms';
    protected $primaryKey = 'bom_id';
    
    protected $fillable = [
        'item_id',
        'bom_code',
        'revision',
        'effective_date',
        'status',
        'standard_quantity',
        'uom_id'
    ];

    protected $casts = [
        'effective_date' => 'date',
        'standard_quantity' => 'float',
    ];

    // Status constants
    const STATUS_DRAFT = 'Draft';
    const STATUS_ACTIVE = 'Active';
    const STATUS_INACTIVE = 'Inactive';

    /**
     * Get the item (finished product) that this BOM belongs to
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    /**
     * Get the unit of measure for this BOM
     */
    public function unitOfMeasure()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id', 'uom_id');
    }

    /**
     * Get the BOM lines (components) for this BOM
     */
    public function bomLines()
    {
        return $this->hasMany(BOMLine::class, 'bom_id', 'bom_id');
    }

    /**
     * Get work orders that use this BOM
     */
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'bom_id', 'bom_id');
    }

    /**
     * Get the total cost of this BOM
     *
     * @param string $currency
     * @return float
     */
    public function getTotalCost($currency = 'USD')
    {
        return $this->bomLines->sum(function ($line) use ($currency) {
            return $line->getExtendedCost($currency);
        });
    }

    /**
     * Check if all components are available for production
     *
     * @param float $productionQuantity
     * @return bool
     */
    public function isAvailableForProduction($productionQuantity = 1)
    {
        foreach ($this->bomLines as $line) {
            if (!$line->isAvailableForProduction($productionQuantity)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get components that are short for production
     *
     * @param float $productionQuantity
     * @return \Illuminate\Support\Collection
     */
    public function getShortageComponents($productionQuantity = 1)
    {
        return $this->bomLines->filter(function ($line) use ($productionQuantity) {
            return $line->getShortageQuantity($productionQuantity) > 0;
        })->map(function ($line) use ($productionQuantity) {
            return [
                'item_id' => $line->item_id,
                'item_code' => $line->item->item_code,
                'item_name' => $line->item->name,
                'required_quantity' => $line->quantity * $productionQuantity,
                'available_quantity' => $line->item->current_stock,
                'shortage_quantity' => $line->getShortageQuantity($productionQuantity),
                'is_critical' => $line->is_critical,
            ];
        });
    }

    /**
     * Calculate maximum possible production based on available components
     *
     * @return float
     */
    public function getMaximumProduction()
    {
        $maxProduction = null;

        foreach ($this->bomLines as $line) {
            if ($line->quantity <= 0) continue;

            $availableQty = $line->item->current_stock ?? 0;
            $possibleProduction = floor($availableQty / $line->quantity);

            if ($maxProduction === null || $possibleProduction < $maxProduction) {
                $maxProduction = $possibleProduction;
            }
        }

        return $maxProduction ?? 0;
    }

    /**
     * Get critical components
     */
    public function getCriticalComponents()
    {
        return $this->bomLines->where('is_critical', true);
    }

    /**
     * Get yield-based components
     */
    public function getYieldBasedComponents()
    {
        return $this->bomLines->where('is_yield_based', true);
    }

    /**
     * Scope for active BOMs
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for specific item
     */
    public function scopeForItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Get the latest revision for an item
     */
    public static function getLatestForItem($itemId)
    {
        return self::where('item_id', $itemId)
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('effective_date', 'desc')
            ->orderBy('revision', 'desc')
            ->first();
    }
}
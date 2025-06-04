<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Item;
use App\Models\UnitOfMeasure;

class BOMLine extends Model
{
    use HasFactory;

    protected $table = 'bom_lines';
    protected $primaryKey = 'line_id';
    
    protected $fillable = [
        'bom_id',
        'item_id',
        'quantity',
        'uom_id',
        'is_critical',
        'notes',
        'is_yield_based',
        'yield_ratio',
        'shrinkage_factor'
    ];

    protected $casts = [
        'quantity' => 'float',
        'is_critical' => 'boolean',
        'is_yield_based' => 'boolean',
        'yield_ratio' => 'float',
        'shrinkage_factor' => 'float',
    ];

    /**
     * Get the BOM that owns this line
     */
    public function bom()
    {
        return $this->belongsTo(BOM::class, 'bom_id', 'bom_id');
    }

    /**
     * Get the item (component) for this BOM line
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    /**
     * Get the unit of measure for this BOM line
     */
    public function unitOfMeasure()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id', 'uom_id');
    }

    /**
     * Calculate the potential yield from a given material quantity.
     * Only applicable for yield-based BOM lines.
     *
     * @param float|null $materialQuantity
     * @return float
     */
    public function calculateYield($materialQuantity = null)
    {
        if (!$this->is_yield_based || !$this->yield_ratio) {
            return 0;
        }

        // If no material quantity provided, use the item's current stock
        if ($materialQuantity === null) {
            $materialQuantity = $this->item->current_stock ?? 0;
        }

        // Calculate base yield
        $baseYield = $materialQuantity * $this->yield_ratio;

        // Apply shrinkage factor if set
        if ($this->shrinkage_factor && $this->shrinkage_factor > 0) {
            $shrinkageAmount = $baseYield * ($this->shrinkage_factor / 100);
            $baseYield -= $shrinkageAmount;
        }

        return $baseYield;
    }

    /**
     * Get the extended cost for this BOM line (quantity * unit cost)
     *
     * @param string $currency
     * @return float
     */
    public function getExtendedCost($currency = 'USD')
    {
        if (!$this->item) {
            return 0;
        }

        $unitCost = $this->item->getDefaultPurchasePriceInCurrency($currency);
        return $this->quantity * $unitCost;
    }

    /**
     * Check if this component is available in sufficient quantity
     *
     * @param float $productionQuantity
     * @return bool
     */
    public function isAvailableForProduction($productionQuantity = 1)
    {
        if (!$this->item) {
            return false;
        }

        $requiredQuantity = $this->quantity * $productionQuantity;
        return $this->item->current_stock >= $requiredQuantity;
    }

    /**
     * Get the shortage quantity for a given production quantity
     *
     * @param float $productionQuantity
     * @return float
     */
    public function getShortageQuantity($productionQuantity = 1)
    {
        if (!$this->item) {
            return 0;
        }

        $requiredQuantity = $this->quantity * $productionQuantity;
        $availableQuantity = $this->item->current_stock;
        
        return max(0, $requiredQuantity - $availableQuantity);
    }

    /**
     * Scope for critical components
     */
    public function scopeCritical($query)
    {
        return $query->where('is_critical', true);
    }

    /**
     * Scope for yield-based components
     */
    public function scopeYieldBased($query)
    {
        return $query->where('is_yield_based', true);
    }
}
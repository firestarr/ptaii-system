<?php
// app/Models/Sales/Delivery.php - UPDATED VERSION

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory;

    protected $table = 'Delivery';
    protected $primaryKey = 'delivery_id';
    public $timestamps = false;

    protected $fillable = [
        'delivery_number',
        'delivery_date',
        'so_id',                    // NULL for consolidated deliveries
        'customer_id',
        'status',
        'shipping_method',
        'tracking_number',
        'is_consolidated',          // NEW: Boolean flag for consolidated deliveries
        'consolidated_so_ids',      // NEW: JSON array of SO IDs for consolidated deliveries
        'notes'                     // NEW: Additional notes field
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'is_consolidated' => 'boolean',
        'consolidated_so_ids' => 'array'  // Automatically cast JSON to array
    ];

    /**
     * Get the sales order that owns the delivery (for single SO deliveries).
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'so_id');
    }

    /**
     * Get the customer that owns the delivery.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the delivery lines for the delivery.
     */
    public function deliveryLines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class, 'delivery_id');
    }

    /**
     * Get consolidated sales orders (for consolidated deliveries).
     * This is a dynamic relationship based on consolidated_so_ids.
     */
    public function consolidatedSalesOrders()
    {
        if (!$this->is_consolidated || !$this->consolidated_so_ids) {
            return collect();
        }

        return SalesOrder::whereIn('so_id', $this->consolidated_so_ids)->get();
    }

    /**
     * Get unique sales orders from delivery lines.
     * Works for both single and consolidated deliveries.
     */
    public function getUniqueSalesOrdersAttribute()
    {
        if ($this->is_consolidated) {
            return $this->consolidatedSalesOrders();
        }

        // For single SO delivery
        if ($this->salesOrder) {
            return collect([$this->salesOrder]);
        }

        // Fallback: get unique SOs from delivery lines
        $soIds = $this->deliveryLines
            ->map(function($line) {
                return $line->salesOrderLine ? $line->salesOrderLine->so_id : null;
            })
            ->filter()
            ->unique();

        return SalesOrder::whereIn('so_id', $soIds)->get();
    }

    /**
     * Check if this is a consolidated delivery.
     */
    public function isConsolidated(): bool
    {
        return $this->is_consolidated === true;
    }

    /**
     * Get the count of sales orders in this delivery.
     */
    public function getSalesOrderCountAttribute(): int
    {
        if ($this->is_consolidated && $this->consolidated_so_ids) {
            return count($this->consolidated_so_ids);
        }

        return $this->so_id ? 1 : 0;
    }

    /**
     * Get consolidation benefits for this delivery.
     */
    public function getConsolidationBenefitsAttribute(): array
    {
        if (!$this->is_consolidated || !$this->consolidated_so_ids) {
            return [
                'time_saved' => 0,
                'cost_saved' => 0,
                'efficiency_gain' => 0
            ];
        }

        $soCount = count($this->consolidated_so_ids);
        
        return [
            'time_saved' => max(0, ($soCount - 1) * 15), // 15 minutes saved per additional SO
            'cost_saved' => max(0, ($soCount - 1) * 10), // $10 saved per additional SO
            'efficiency_gain' => min(50, ($soCount - 1) * 12.5) // Max 50% efficiency gain
        ];
    }

    /**
     * Get delivery summary for display.
     */
    public function getSummaryAttribute(): array
    {
        $summary = [
            'delivery_number' => $this->delivery_number,
            'customer_name' => $this->customer->name ?? 'Unknown Customer',
            'delivery_date' => $this->delivery_date,
            'status' => $this->status,
            'is_consolidated' => $this->is_consolidated,
            'sales_order_count' => $this->sales_order_count,
            'total_items' => $this->deliveryLines->count(),
            'total_quantity' => $this->deliveryLines->sum('delivered_quantity')
        ];

        if ($this->is_consolidated) {
            $summary['consolidated_so_numbers'] = $this->consolidatedSalesOrders()->pluck('so_number')->toArray();
            $summary['consolidation_benefits'] = $this->consolidation_benefits;
        } else {
            $summary['so_number'] = $this->salesOrder ? $this->salesOrder->so_number : null;
        }

        return $summary;
    }

    /**
     * Scope for consolidated deliveries only.
     */
    public function scopeConsolidated($query)
    {
        return $query->where('is_consolidated', true);
    }

    /**
     * Scope for single SO deliveries only.
     */
    public function scopeSingleSO($query)
    {
        return $query->where('is_consolidated', false)->whereNotNull('so_id');
    }

    /**
     * Get delivery type as string.
     */
    public function getDeliveryTypeAttribute(): string
    {
        if ($this->is_consolidated) {
            $soCount = $this->sales_order_count;
            return "Consolidated ({$soCount} SOs)";
        }

        return 'Single SO';
    }

    /**
     * Get status badge class for UI.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        $statusClasses = [
            'Pending' => 'badge-warning',
            'In Transit' => 'badge-info',
            'Completed' => 'badge-success',
            'Delivered' => 'badge-success',
            'Cancelled' => 'badge-danger'
        ];

        return $statusClasses[$this->status] ?? 'badge-secondary';
    }

    /**
     * Get warehouses involved in this delivery.
     */
    public function getInvolvedWarehousesAttribute(): array
    {
        return $this->deliveryLines
            ->pluck('warehouse.name')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Static method to create consolidated delivery.
     */
    public static function createConsolidated(array $data): self
    {
        // Ensure required fields for consolidated delivery
        $data['is_consolidated'] = true;
        $data['so_id'] = null; // Consolidated deliveries don't have single SO ID

        // Validate consolidated_so_ids
        if (!isset($data['consolidated_so_ids']) || !is_array($data['consolidated_so_ids']) || count($data['consolidated_so_ids']) < 2) {
            throw new \InvalidArgumentException('Consolidated delivery must have at least 2 sales orders');
        }

        // Validate all SOs belong to same customer
        $customerIds = SalesOrder::whereIn('so_id', $data['consolidated_so_ids'])
            ->distinct()
            ->pluck('customer_id');

        if ($customerIds->count() > 1) {
            throw new \InvalidArgumentException('All sales orders must belong to the same customer');
        }

        if (!$customerIds->contains($data['customer_id'])) {
            throw new \InvalidArgumentException('Customer ID does not match sales orders');
        }

        return self::create($data);
    }

    /**
     * Get delivery performance metrics.
     */
    public static function getPerformanceMetrics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $totalDeliveries = self::where('created_at', '>=', $startDate)->count();
        $consolidatedDeliveries = self::consolidated()->where('created_at', '>=', $startDate)->count();
        $completedDeliveries = self::where('status', 'Completed')->where('created_at', '>=', $startDate)->count();

        $consolidationRate = $totalDeliveries > 0 ? ($consolidatedDeliveries / $totalDeliveries) * 100 : 0;
        $completionRate = $totalDeliveries > 0 ? ($completedDeliveries / $totalDeliveries) * 100 : 0;

        // Calculate total benefits from consolidated deliveries
        $consolidatedData = self::consolidated()
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalTimeSaved = 0;
        $totalCostSaved = 0;
        $totalSOsProcessed = 0;

        foreach ($consolidatedData as $delivery) {
            $benefits = $delivery->consolidation_benefits;
            $totalTimeSaved += $benefits['time_saved'];
            $totalCostSaved += $benefits['cost_saved'];
            $totalSOsProcessed += $delivery->sales_order_count;
        }

        return [
            'total_deliveries' => $totalDeliveries,
            'consolidated_deliveries' => $consolidatedDeliveries,
            'consolidation_rate' => round($consolidationRate, 2),
            'completion_rate' => round($completionRate, 2),
            'total_time_saved_minutes' => $totalTimeSaved,
            'total_cost_saved' => $totalCostSaved,
            'total_sos_processed' => $totalSOsProcessed,
            'avg_sos_per_consolidated' => $consolidatedDeliveries > 0 ? 
                round($totalSOsProcessed / $consolidatedDeliveries, 1) : 0,
            'efficiency_score' => round(($consolidationRate + $completionRate) / 2, 2)
        ];
    }
}
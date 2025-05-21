<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Item;

class SalesForecast extends Model
{
    use HasFactory;

    protected $table = 'SalesForecast';
    protected $primaryKey = 'forecast_id';
    public $timestamps = false;
    
    protected $fillable = [
        'item_id',
        'customer_id',
        'forecast_period',
        'forecast_quantity',
        'actual_quantity',
        'variance',
        'forecast_source',
        'confidence_level',
        'forecast_issue_date',
        'submission_date',
        'is_current_version',
        'previous_version_id'
    ];

    protected $casts = [
        'forecast_period' => 'date',
        'forecast_quantity' => 'float',
        'actual_quantity' => 'float',
        'variance' => 'float',
        'confidence_level' => 'float',
        'forecast_issue_date' => 'date',
        'submission_date' => 'date',
        'is_current_version' => 'boolean'
    ];

    /**
     * Get the customer that owns the forecast.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the item that the forecast belongs to.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Get the previous version of this forecast.
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(SalesForecast::class, 'previous_version_id', 'forecast_id');
    }

    /**
     * Get all versions of the forecast for a specific item, customer, and period.
     *
     * @param int $itemId
     * @param int $customerId
     * @param string $forecastPeriod
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllVersions($itemId, $customerId, $forecastPeriod)
    {
        return self::where('item_id', $itemId)
            ->where('customer_id', $customerId)
            ->where('forecast_period', $forecastPeriod)
            ->orderBy('forecast_issue_date', 'desc')
            ->orderBy('submission_date', 'desc')
            ->get();
    }

    /**
     * Get the latest version of the forecast for a specific item, customer, and period.
     *
     * @param int $itemId
     * @param int $customerId
     * @param string $forecastPeriod
     * @return SalesForecast|null
     */
    public static function getLatestVersion($itemId, $customerId, $forecastPeriod)
    {
        return self::where('item_id', $itemId)
            ->where('customer_id', $customerId)
            ->where('forecast_period', $forecastPeriod)
            ->where('is_current_version', true)
            ->first();
    }
}
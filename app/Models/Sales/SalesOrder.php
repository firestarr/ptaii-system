<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CurrencyRate;

class SalesOrder extends Model
{
    use HasFactory;

    protected $table = 'SalesOrder';
    protected $primaryKey = 'so_id';
    public $timestamps = false;

    protected $fillable = [
        'so_number',
        'so_date',
        'customer_id',
        'quotation_id',
        'payment_terms',
        'delivery_terms',
        'expected_delivery',
        'status',
        'total_amount',
        'tax_amount',
        'currency_code', // Baru
        'exchange_rate', // Baru
        'base_currency', // Baru
        'base_currency_total', // Baru
        'base_currency_tax' // Baru
    ];

    protected $casts = [
        'so_date' => 'date',
        'expected_delivery' => 'date',
        'total_amount' => 'float',
        'tax_amount' => 'float',
        'exchange_rate' => 'float', // Baru
        'base_currency_total' => 'float', // Baru
        'base_currency_tax' => 'float' // Baru
    ];

    /**
     * Get the customer that owns the sales order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the sales quotation that owns the sales order.
     */
    public function salesQuotation(): BelongsTo
    {
        return $this->belongsTo(SalesQuotation::class, 'quotation_id');
    }

    /**
     * Get the sales order lines for the sales order.
     */
    public function salesOrderLines(): HasMany
    {
        return $this->hasMany(SOLine::class, 'so_id');
    }

    /**
     * Get the deliveries for the sales order.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'so_id');
    }

    /**
     * Get the sales invoices for the sales order.
     */
    public function salesInvoices(): HasMany
    {
        // Removed invalid relationship due to missing 'so_id' in SalesInvoice
        // If needed, this can be re-implemented using a different approach
        return $this->hasMany(SalesInvoice::class, 'do_id', 'so_id');
    }
    
    /**
     * Convert amounts to specified currency.
     *
     * @param string $toCurrency
     * @param string|null $date
     * @return array
     */
    public function getAmountsInCurrency($toCurrency, $date = null)
    {
        $date = $date ?? $this->so_date;
        
        // If already in requested currency, return original amounts
        if ($this->currency_code === $toCurrency) {
            return [
                'total_amount' => $this->total_amount,
                'tax_amount' => $this->tax_amount
            ];
        }
        
        // Try to convert via base currency first
        if ($toCurrency === $this->base_currency) {
            return [
                'total_amount' => $this->base_currency_total,
                'tax_amount' => $this->base_currency_tax
            ];
        }
        
        // Get rate from base currency to requested currency
        $rate = CurrencyRate::getCurrentRate($this->base_currency, $toCurrency, $date);
        
        if (!$rate) {
            // Try direct conversion
            $rate = CurrencyRate::getCurrentRate($this->currency_code, $toCurrency, $date);
            if (!$rate) {
                // If no conversion possible, return original values
                return [
                    'total_amount' => $this->total_amount,
                    'tax_amount' => $this->tax_amount
                ];
            }
            
            return [
                'total_amount' => $this->total_amount * $rate,
                'tax_amount' => $this->tax_amount * $rate
            ];
        }
        
        return [
            'total_amount' => $this->base_currency_total * $rate,
            'tax_amount' => $this->base_currency_tax * $rate
        ];
    }
}
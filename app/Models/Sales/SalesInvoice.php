<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CurrencyRate;
use App\Models\Accounting\CustomerReceivable;

class SalesInvoice extends Model
{
    use HasFactory;

    protected $table = 'SalesInvoice';
    protected $primaryKey = 'invoice_id';
    public $timestamps = false;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'customer_id',
        'do_id',
        'total_amount',
        'tax_amount',
        'due_date',
        'status',
        'currency_code', // Baru
        'exchange_rate', // Baru
        'base_currency', // Baru
        'base_currency_total', // Baru
        'base_currency_tax' // Baru
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'float',
        'tax_amount' => 'float',
        'exchange_rate' => 'float', // Baru
        'base_currency_total' => 'float', // Baru
        'base_currency_tax' => 'float' // Baru
    ];

    /**
     * Get the customer that owns the sales invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the sales order that owns the sales invoice.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    /**
     * Get the sales invoice lines for the sales invoice.
     */
    public function salesInvoiceLines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class, 'invoice_id');
    }

    /**
     * Get the sales returns for the sales invoice.
     */
    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class, 'invoice_id');
    }

    /**
     * Get the customer receivables for the sales invoice.
     */
    public function customerReceivables(): HasMany
    {
        return $this->hasMany(CustomerReceivable::class, 'invoice_id');
    }

    /**
     * Get the sales commissions for the sales invoice.
     */
    public function salesCommissions(): HasMany
    {
        return $this->hasMany(SalesCommission::class, 'invoice_id');
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
        $date = $date ?? $this->invoice_date;
        
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
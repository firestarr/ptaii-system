<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CurrencyRate;
use App\Models\Item;

class SalesInvoiceLine extends Model
{
    use HasFactory;

    protected $table = 'SalesInvoiceLine';
    protected $primaryKey = 'line_id';
    public $timestamps = false;
    
    protected $fillable = [
        'invoice_id',
        'so_line_id',
        'item_id',
        'quantity',
        'unit_price',
        'discount',
        'subtotal',
        'tax',
        'total',
        'base_currency_unit_price', // Baru
        'base_currency_subtotal', // Baru
        'base_currency_discount', // Baru
        'base_currency_tax', // Baru
        'base_currency_total', // Baru
        'do_line_id'
    ];
    
    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'subtotal' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'base_currency_unit_price' => 'float', // Baru
        'base_currency_subtotal' => 'float', // Baru
        'base_currency_discount' => 'float', // Baru
        'base_currency_tax' => 'float', // Baru
        'base_currency_total' => 'float' // Baru
    ];

    /**
     * Get the sales invoice that owns the sales invoice line.
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    /**
     * Get the sales order line that owns the sales invoice line.
     */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SOLine::class, 'so_line_id');
    }

    /**
     * Get the item that the sales invoice line belongs to.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Get the sales return lines for the sales invoice line.
     */
    public function salesReturnLines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class, 'invoice_line_id');
    }

    /**
     * Get the delivery line associated with the invoice line.
     */
    public function deliveryLine()
    {
        return $this->belongsTo(DeliveryLine::class, 'do_line_id', 'line_id');
    }
    
    /**
     * Get line amounts in specified currency.
     *
     * @param string $toCurrency
     * @param string|null $date
     * @return array
     */
    public function getAmountsInCurrency($toCurrency, $date = null)
    {
        $invoice = $this->salesInvoice;
        $date = $date ?? $invoice->invoice_date;
        
        // If already in requested currency, return original amounts
        if ($invoice->currency_code === $toCurrency) {
            return [
                'unit_price' => $this->unit_price,
                'subtotal' => $this->subtotal,
                'discount' => $this->discount,
                'tax' => $this->tax,
                'total' => $this->total
            ];
        }
        
        // Try to convert via base currency first
        if ($toCurrency === $invoice->base_currency) {
            return [
                'unit_price' => $this->base_currency_unit_price,
                'subtotal' => $this->base_currency_subtotal,
                'discount' => $this->base_currency_discount,
                'tax' => $this->base_currency_tax,
                'total' => $this->base_currency_total
            ];
        }
        
        // Get rate from base currency to requested currency
        $rate = CurrencyRate::getCurrentRate($invoice->base_currency, $toCurrency, $date);
        
        if (!$rate) {
            // Try direct conversion
            $rate = CurrencyRate::getCurrentRate($invoice->currency_code, $toCurrency, $date);
            if (!$rate) {
                // If no conversion possible, return original values
                return [
                    'unit_price' => $this->unit_price,
                    'subtotal' => $this->subtotal,
                    'discount' => $this->discount,
                    'tax' => $this->tax,
                    'total' => $this->total
                ];
            }
            
            return [
                'unit_price' => $this->unit_price * $rate,
                'subtotal' => $this->subtotal * $rate,
                'discount' => $this->discount * $rate,
                'tax' => $this->tax * $rate,
                'total' => $this->total * $rate
            ];
        }
        
        return [
            'unit_price' => $this->base_currency_unit_price * $rate,
            'subtotal' => $this->base_currency_subtotal * $rate,
            'discount' => $this->base_currency_discount * $rate,
            'tax' => $this->base_currency_tax * $rate,
            'total' => $this->base_currency_total * $rate
        ];
    }
}
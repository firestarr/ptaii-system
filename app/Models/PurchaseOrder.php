<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';
    protected $primaryKey = 'po_id';
    protected $fillable = [
        'po_number',
        'po_date',
        'vendor_id',
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
        'base_currency_tax', // Baru
    ];

    protected $casts = [
        'po_date' => 'date',
        'expected_delivery' => 'date',
        'exchange_rate' => 'float', // Baru
        'base_currency_total' => 'float', // Baru
        'base_currency_tax' => 'float', // Baru
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function lines()
    {
        return $this->hasMany(POLine::class, 'po_id');
    }

    public function goodsReceipts()
    {
        return $this->hasManyThrough(
            GoodsReceipt::class,
            GoodsReceiptLine::class,
            'po_id', // Foreign key on GoodsReceiptLine table...
            'receipt_id', // Foreign key on GoodsReceipt table...
            'po_id', // Local key on PurchaseOrder table...
            'receipt_id' // Local key on GoodsReceiptLine table...
        );
    }

    public function invoices()
    {
        return $this->hasMany(VendorInvoice::class, 'po_id');
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
        $date = $date ?? $this->po_date;
        
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
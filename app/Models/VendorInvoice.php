<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CurrencyRate;

class VendorInvoice extends Model
{
    use HasFactory;

    protected $table = 'vendor_invoices';
    protected $primaryKey = 'invoice_id';
    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'vendor_id',
        'po_id',
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

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function lines()
    {
        return $this->hasMany(VendorInvoiceLine::class, 'invoice_id');
    }

    public function payables()
    {
        return $this->hasMany(VendorPayable::class, 'invoice_id');
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
    // Add this relationship to your VendorInvoice model
    public function goodsReceipts()
    {
        return $this->belongsToMany(
            GoodsReceipt::class, 
            'invoice_receipt_relations', 
            'invoice_id', 
            'receipt_id'
        );
    }

    // Update existing relationship to handle multiple POs
    public function purchaseOrders()
    {
        return $this->hasManyThrough(
            PurchaseOrder::class,
            GoodsReceiptLine::class,
            'invoice_line_id',
            'po_id',
            'invoice_id',
            'po_id'
        )->distinct();
    }
}
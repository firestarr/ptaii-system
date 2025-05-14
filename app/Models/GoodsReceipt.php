<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    use HasFactory;

    protected $table = 'goods_receipts';
    protected $primaryKey = 'receipt_id';  
    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'vendor_id',
        'status'
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    /**
     * Get the vendor associated with the goods receipt.
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Get the lines for the goods receipt.
     */
    public function lines()
    {
        return $this->hasMany(GoodsReceiptLine::class, 'receipt_id');
    }

    /**
     * Get all purchase orders associated with this receipt.
     */
    public function purchaseOrders()
    {
        $poIds = $this->lines()->pluck('po_id')->unique();
        return PurchaseOrder::whereIn('po_id', $poIds);
    }

    /**
     * Get purchase order numbers related to this receipt.
     */
    public function getPoNumbersAttribute()
    {
        return PurchaseOrder::whereIn('po_id', $this->lines()->pluck('po_id')->unique())
            ->pluck('po_number')
            ->implode(', ');
    }

    /**
     * Get total quantity received in this receipt.
     */
    public function getTotalQuantityAttribute()
    {
        return $this->lines()->sum('received_quantity');
    }

    /**
     * Get total number of items in this receipt.
     */
    public function getTotalItemsAttribute()
    {
        return $this->lines()->count();
    }
    
    // Add to your GoodsReceipt model
    public function vendorInvoices()
    {
        return $this->belongsToMany(
            VendorInvoice::class, 
            'invoice_receipt_relations', 
            'receipt_id', 
            'invoice_id'
        );
    }

    // Add a method to check if the receipt is fully invoiced
    public function isFullyInvoiced()
    {
        return $this->lines()->where('is_invoiced', false)->count() === 0;
    }

    // Add a method to get uninvoiced lines
    public function getUninvoicedLines()
    {
        return $this->lines()->where('is_invoiced', false)->get();
    }
}
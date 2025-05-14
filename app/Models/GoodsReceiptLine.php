<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    use HasFactory;

    protected $table = 'goods_receipt_lines';
    protected $primaryKey = 'line_id';
    protected $fillable = [
        'receipt_id',
        'po_line_id',
        'po_id', // Added to support multiple PO
        'item_id',
        'received_quantity',
        'warehouse_id',
        'batch_number'
    ];

    protected $casts = [
        'received_quantity' => 'float',
    ];

    /**
     * Get the goods receipt associated with the line.
     */
    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class, 'receipt_id');
    }

    /**
     * Get the purchase order line associated with the receipt line.
     */
    public function purchaseOrderLine()
    {
        return $this->belongsTo(POLine::class, 'po_line_id');
    }

    /**
     * Get the purchase order associated with the line.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    // Add to GoodsReceiptLine model
    public function invoiceLine()
    {
        return $this->belongsTo(VendorInvoiceLine::class, 'invoice_line_id');
    }

    /**
     * Get the item associated with the line.
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Get the warehouse associated with the line.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Get the outstanding quantity for a specific PO line.
     *
     * @param int $poLineId
     * @param int|null $excludeReceiptId
     * @return float
     */
    public static function getOutstandingQuantity($poLineId, $excludeReceiptId = null)
    {
        $poLine = POLine::find($poLineId);
        if (!$poLine) {
            return 0;
        }
        
        $query = self::where('po_line_id', $poLineId)
            ->whereHas('goodsReceipt', function($query) {
                $query->where('status', 'confirmed');
            });
            
        if ($excludeReceiptId) {
            $query->whereHas('goodsReceipt', function($query) use ($excludeReceiptId) {
                $query->where('receipt_id', '<>', $excludeReceiptId);
            });
        }
        
        $receivedQuantity = $query->sum('received_quantity');
            
        return $poLine->quantity - $receivedQuantity;
    }
}
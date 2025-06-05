<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use app\Models\User;

class PurchaseRequisition extends Model
{
    use HasFactory;

    protected $table = 'purchase_requisitions';
    protected $primaryKey = 'pr_id';
    protected $fillable = [
        'pr_number',
        'pr_date',
        'requester_id',
        'status',
        'notes'
    ];

    protected $casts = [
        'pr_date' => 'date',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }

    public function lines()
    {
        return $this->hasMany(PRLine::class, 'pr_id');
    }
    /**
     * Check if PR can be converted to PO
     */
    public function canCreatePO()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if PR has vendor pricing for all items
     */
    public function hasCompletePricing($vendorId = null)
    {
        foreach ($this->lines as $line) {
            $item = $line->item;
            $hasPrice = false;
            
            if ($vendorId) {
                $hasPrice = $item->prices()
                    ->where('vendor_id', $vendorId)
                    ->where('price_type', 'purchase')
                    ->active()
                    ->exists();
            } else {
                $hasPrice = $item->prices()
                    ->where('price_type', 'purchase')
                    ->active()
                    ->exists();
            }
            
            if (!$hasPrice) {
                return false;
            }
        }
        
        return true;
    }
}
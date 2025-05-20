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
}
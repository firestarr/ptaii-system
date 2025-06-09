<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sales\SalesOrder;
use App\Models\User;

class PdfOrderCapture extends Model
{
    use HasFactory;

    protected $table = 'pdf_order_captures';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'filename',
        'file_path',
        'file_size',
        'ai_raw_response',
        'extracted_data',
        'status',
        'processing_error',
        'created_so_id',
        'confidence_score',
        'processed_by',
        'processed_at',
        'user_id',
        'processing_options'
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'ai_raw_response' => 'array',
        'processing_options' => 'array',
        'confidence_score' => 'float',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants - sesuaikan dengan yang digunakan di controller
    const STATUS_UPLOADED = 'uploaded';
    const STATUS_PROCESSING = 'processing';
    const STATUS_DATA_EXTRACTED = 'data_extracted';
    const STATUS_EXTRACTED = 'extracted';
    const STATUS_SO_CREATED = 'so_created';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Get the sales order that was created from this capture
     */
    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'created_so_id', 'so_id');
    }

    /**
     * Get the user who processed this capture
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the user who uploaded this capture
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mark as completed with SO created
     */
    public function markAsCompleted($soId)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'created_so_id' => $soId,
            'processed_at' => now()
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed($error)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'processing_error' => $error
        ]);
    }

    /**
     * Check if processing was successful
     */
    public function isSuccessful()
    {
        return in_array($this->status, [self::STATUS_SO_CREATED, self::STATUS_COMPLETED]) && !is_null($this->created_so_id);
    }

    /**
     * Check if currently processing
     */
    public function isProcessing()
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Get statistics for PDF order captures
     *
     * @param int|null $userId
     * @param int $days
     * @return array
     */
    public static function getStatistics($userId = null, $days = 30)
    {
        $query = self::query();

        if ($userId) {
            $query->where('processed_by', $userId);
        }

        if ($days) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        $total = $query->count();

        $completed = (clone $query)->whereIn('status', [self::STATUS_SO_CREATED, self::STATUS_COMPLETED])->count();
        $processing = (clone $query)->where('status', self::STATUS_PROCESSING)->count();
        $failed = (clone $query)->where('status', self::STATUS_FAILED)->count();

        $averageConfidence = (clone $query)->whereNotNull('confidence_score')->avg('confidence_score');

        $successRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        return [
            'total' => $total,
            'completed' => $completed,
            'processing' => $processing,
            'failed' => $failed,
            'success_rate' => $successRate,
            'average_confidence' => $averageConfidence ? round($averageConfidence, 2) : 0,
        ];
    }
}
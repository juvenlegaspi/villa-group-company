<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JmvDailyProductionLog extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';
    public const STATUS_FOR_CORRECTION = 'FOR_CORRECTION';
    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING_REVIEW => 'Pending Review',
        self::STATUS_FOR_CORRECTION => 'For Correction',
        self::STATUS_COMPLETED => 'Completed / Logged',
    ];

    protected $fillable = [
        'division_id', 'log_no', 'log_date', 'location', 'shift', 'leadman', 'foreman', 'total_workers',
        'timber_used', 'timber_unit', 'nails_used', 'nails_unit', 'fuel_consumed', 'fuel_unit',
        'soil_output', 'coal_output', 'excess_deficit', 'remarks', 'status', 'created_by', 'updated_by',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_remarks', 'completed_at',
        'revision_count',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date', 'total_workers' => 'integer', 'timber_used' => 'decimal:2',
            'nails_used' => 'decimal:2', 'fuel_consumed' => 'decimal:2', 'soil_output' => 'integer',
            'coal_output' => 'integer', 'excess_deficit' => 'integer', 'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime', 'completed_at' => 'datetime', 'revision_count' => 'integer',
        ];
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function attachments() { return $this->hasMany(JmvDailyProductionAttachment::class); }
    public function audits() { return $this->hasMany(JmvDailyProductionAudit::class)->latest('created_at'); }
}

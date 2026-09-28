<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechDefect extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vessel_id',
        'status',
        'date_completed',
        'completion_cost',
        'date_identified',
        'port_location',
        'reported_by',
        'reported_by_user_id',
        'review_submitted_by',
        'review_submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
        'assigned_to_user_id',
        'target_completion_date',
        'action_assigned_by',
        'action_assigned_at',
        'progress_percent',
        'progress_notes',
        'progress_updated_by',
        'progress_updated_at',
        'downtime_started_at',
        'downtime_ended_at',
        'total_downtime_hours',
        'system_affected',
        'defect_description',
        'initial_cause',
        'technical_assessment',
        'technical_findings',
        'technical_recommendation',
        'information_request',
        'information_response',
        'information_requested_by',
        'information_requested_at',
        'information_responded_by',
        'information_responded_at',
        'assessed_by',
        'assessed_at',
        'root_cause',
        'corrective_action',
        'preventive_action',
        'severity_level',
        'operational_impact',
        'temporary_repair',
        'third_party_required',
        'third_party_reason',
        'spares_required',
        'remarks',
        'repair_completed_by',
        'repair_completed_at',
        'verified_by',
        'verified_at',
        'closed_by',
        'closed_at',
        'verification_remarks',
        'overdue_notified_at',
    ];

    protected $casts = [
        'date_completed' => 'date',
        'completion_cost' => 'decimal:2',
        'date_identified' => 'date',
        'target_completion_date' => 'date',
        'review_submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'information_requested_at' => 'datetime',
        'information_responded_at' => 'datetime',
        'assessed_at' => 'datetime',
        'action_assigned_at' => 'datetime',
        'progress_percent' => 'integer',
        'progress_updated_at' => 'datetime',
        'downtime_started_at' => 'datetime',
        'downtime_ended_at' => 'datetime',
        'total_downtime_hours' => 'decimal:2',
        'repair_completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'closed_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function supports()
    {
        return $this->hasMany(ThirdPartySupport::class, 'tech_defect_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignee() { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
    public function reviewSubmitter() { return $this->belongsTo(User::class, 'review_submitted_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function assessor() { return $this->belongsTo(User::class, 'assessed_by'); }
    public function actionAssigner() { return $this->belongsTo(User::class, 'action_assigned_by'); }
    public function progressUpdater() { return $this->belongsTo(User::class, 'progress_updated_by'); }
    public function repairCompleter() { return $this->belongsTo(User::class, 'repair_completed_by'); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }
    public function attachments() { return $this->hasMany(TechDefectAttachment::class); }
    public function informationRequests() { return $this->hasMany(TechDefectInformationRequest::class)->latest('requested_at')->latest('id'); }

    public function audits()
    {
        return $this->hasMany(TechDefectAudit::class)->latest();
    }

    public function getReportCodeAttribute(): string
    {
        $year = optional($this->created_at)->format('Y') ?? now()->format('Y');

        return 'TDR-'.$year.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}

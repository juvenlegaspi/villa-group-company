<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VesselCertificate extends Model
{
    protected $fillable = [
        'vessel_id',
        'certificate_name',
        'certificate_number',
        'certificate_type',
        'issuing_authority',
        'issue_date',
        'expiry_date',
        'remarks',
        'document',
        'workflow_status',
        'previous_certificate_id',
        'document_original_name',
        'document_mime_type',
        'document_size',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_remarks',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'document_size' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function previousCertificate() { return $this->belongsTo(self::class, 'previous_certificate_id'); }
    public function renewals() { return $this->hasMany(self::class, 'previous_certificate_id'); }
    public function documents() { return $this->hasMany(VesselCertificateDocument::class)->latest(); }
    public function audits() { return $this->hasMany(VesselCertificateAudit::class)->latest('created_at'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('workflow_status', '!=', 'superseded');
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('workflow_status', 'active');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereDate('expiry_date', '<', today());
    }

    public function scopeExpiringWithinDays(Builder $query, int $days = 30): Builder
    {
        return $query->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->copy()->addDays($days));
    }
}

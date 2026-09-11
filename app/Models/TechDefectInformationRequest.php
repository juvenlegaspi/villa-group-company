<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechDefectInformationRequest extends Model
{
    protected $fillable = [
        'tech_defect_id',
        'requested_by',
        'request_text',
        'requested_at',
        'responded_by',
        'response_text',
        'responded_at',
        'status',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function techDefect() { return $this->belongsTo(TechDefect::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function responder() { return $this->belongsTo(User::class, 'responded_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VesselCertificateAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['vessel_certificate_id', 'user_id', 'action', 'changes', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function certificate() { return $this->belongsTo(VesselCertificate::class, 'vessel_certificate_id'); }
    public function user() { return $this->belongsTo(User::class); }
}

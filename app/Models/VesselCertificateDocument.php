<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VesselCertificateDocument extends Model
{
    protected $fillable = ['vessel_certificate_id', 'path', 'original_name', 'mime_type', 'size', 'is_current', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'is_current' => 'boolean'];
    }

    public function certificate() { return $this->belongsTo(VesselCertificate::class, 'vessel_certificate_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}

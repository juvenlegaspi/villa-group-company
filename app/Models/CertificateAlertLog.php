<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateAlertLog extends Model
{
    protected $fillable = [
        'vessel_certificate_id',
        'recipient_email',
        'alert_date',
        'sent_at',
    ];

    protected $casts = [
        'alert_date' => 'date',
        'sent_at' => 'datetime',
    ];
}

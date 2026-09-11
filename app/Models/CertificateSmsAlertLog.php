<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateSmsAlertLog extends Model
{
    protected $fillable = [
        'vessel_certificate_id', 'user_id', 'recipient_number', 'alert_date', 'days_remaining',
        'status', 'provider_message_id', 'provider_status', 'error_message', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['alert_date' => 'date', 'days_remaining' => 'integer', 'sent_at' => 'datetime'];
    }
}

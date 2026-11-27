<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayTrackingSms extends Model
{
    use \App\Models\Concerns\ScopesHaruhayProperties;

    protected $table = 'haruhay_tracking_sms';

    protected $guarded = ['id'];

    protected $hidden = ['payload', 'recipient'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'started_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function reservation()
    {
        return $this->belongsTo(HaruhayReservation::class, 'reservation_id');
    }
}

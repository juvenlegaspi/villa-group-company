<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayReservation extends Model
{
    use \App\Models\Concerns\ScopesHaruhayProperties;

    use \App\Models\Concerns\InvalidatesHaruhayReservationDirectory;
    protected $table = 'haruhay_online_reservations';

    protected $guarded = ['id'];

    protected $hidden = ['tracking_token_hash', 'submission_fingerprint', 'submission_token'];

    public const STATUSES = ['PendingReview', 'AwaitingPayment', 'PaymentReview', 'PartiallyPaid', 'Confirmed', 'MovedIn', 'Declined', 'Cancelled', 'Expired'];

    protected function casts(): array
    {
        return ['occupant_count' => 'integer', 'other_occupants' => 'array', 'duration_months' => 'integer', 'requested_starts_on' => 'date', 'starts_on' => 'date', 'ends_on' => 'date', 'monthly_rent' => 'decimal:2', 'deposit_required' => 'decimal:2', 'advance_required' => 'decimal:2', 'advance_received' => 'decimal:2', 'hold_expires_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'quote_accepted_at' => 'datetime', 'approved_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function unit()
    {
        return $this->belongsTo(HaruhayUnit::class);
    }

    public function tenant()
    {
        return $this->belongsTo(HaruhayTenant::class);
    }

    public function contract()
    {
        return $this->belongsTo(HaruhayLeaseContract::class);
    }

    public function payments()
    {
        return $this->hasMany(HaruhayReservationPayment::class, 'reservation_id');
    }

    public function trackingSms()
    {
        return $this->hasOne(HaruhayTrackingSms::class, 'reservation_id');
    }

    public function events()
    {
        return $this->hasMany(HaruhayReservationEvent::class, 'reservation_id')->orderBy('id');
    }

    public function getRemainingCentsAttribute(): int
    {
        return max(0, (int) round((float) $this->advance_required * 100) - (int) round((float) $this->advance_received * 100));
    }
}

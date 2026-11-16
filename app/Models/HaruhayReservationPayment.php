<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayReservationPayment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['proof_path', 'submission_token'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'verified_amount' => 'decimal:2', 'paid_on' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function reservation()
    {
        return $this->belongsTo(HaruhayReservation::class);
    }

    public function collection()
    {
        return $this->belongsTo(HaruhayCollection::class);
    }
}

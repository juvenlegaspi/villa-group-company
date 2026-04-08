<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use App\Models\Vessel;

class VesselCertificate extends Model
{
    //
    protected $fillable = [
        'vessel_id',
        'certificate_name',
        'issue_date',
        'expiry_date',
        'remarks',
        'document'
    ];

    public function vessel()
{
    return $this->belongsTo(Vessel::class);
}
}

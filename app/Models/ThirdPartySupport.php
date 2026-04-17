<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartySupport extends Model
{
    protected $fillable = [
        'tech_defect_id',
        'reason_for_support',
        'spares_required',
        'tools_required',
        'status',
    ];

    public function techDefect()
    {
        return $this->belongsTo(TechDefect::class);
    }
}

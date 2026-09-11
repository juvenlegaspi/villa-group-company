<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechDefectAudit extends Model
{
    protected $fillable = [
        'tech_defect_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'changes',
        'description',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function techDefect()
    {
        return $this->belongsTo(TechDefect::class)->withTrashed();
    }
}

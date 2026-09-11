<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartySupport extends Model
{
    protected $fillable = [
        'tech_defect_id',
        'vendor_name',
        'contact_person',
        'contact_number',
        'reason_for_support',
        'spares_required',
        'tools_required',
        'expected_completion_date',
        'quoted_cost',
        'actual_cost',
        'status',
        'created_by',
        'completed_by',
        'completed_at',
        'overdue_notified_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'expected_completion_date' => 'date', 'quoted_cost' => 'decimal:2', 'actual_cost' => 'decimal:2', 'overdue_notified_at' => 'datetime'];
    }

    public function techDefect()
    {
        return $this->belongsTo(TechDefect::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

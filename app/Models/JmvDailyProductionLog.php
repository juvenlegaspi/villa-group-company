<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JmvDailyProductionLog extends Model
{
    protected $fillable = [
        'division_id', 'log_no', 'log_date', 'location', 'shift', 'leadman', 'foreman', 'total_workers',
        'timber_used', 'timber_unit', 'nails_used', 'nails_unit', 'fuel_consumed', 'fuel_unit',
        'soil_output', 'coal_output', 'excess_deficit', 'remarks', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date', 'total_workers' => 'integer', 'timber_used' => 'decimal:2',
            'nails_used' => 'decimal:2', 'fuel_consumed' => 'decimal:2', 'soil_output' => 'integer',
            'coal_output' => 'integer', 'excess_deficit' => 'integer',
        ];
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function attachments() { return $this->hasMany(JmvDailyProductionAttachment::class); }
    public function audits() { return $this->hasMany(JmvDailyProductionAudit::class)->latest('created_at'); }
}

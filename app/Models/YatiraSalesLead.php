<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YatiraSalesLead extends Model
{
    use SoftDeletes;

    protected $fillable = ['division_id', 'lead_code', 'date_registered', 'client_name', 'contact_person', 'contact_number', 'email', 'client_address', 'project_name', 'project_type', 'project_description', 'estimated_value', 'project_location', 'lead_source', 'expected_close_date', 'assigned_agent_id', 'sales_stage_id', 'probability_percent', 'status', 'remarks', 'cancellation_reason', 'cancelled_at', 'cancelled_by', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date_registered' => 'date', 'expected_close_date' => 'date', 'estimated_value' => 'decimal:2', 'probability_percent' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    public function stage()
    {
        return $this->belongsTo(YatiraSalesStage::class, 'sales_stage_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories()
    {
        return $this->hasMany(YatiraSalesHistory::class)->latest('created_at');
    }
}

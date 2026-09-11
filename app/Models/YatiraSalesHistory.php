<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YatiraSalesHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['yatira_sales_lead_id', 'user_id', 'action', 'from_stage_id', 'to_stage_id', 'remarks', 'changes', 'ip_address', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromStage()
    {
        return $this->belongsTo(YatiraSalesStage::class, 'from_stage_id');
    }

    public function toStage()
    {
        return $this->belongsTo(YatiraSalesStage::class, 'to_stage_id');
    }
}

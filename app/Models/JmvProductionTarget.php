<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JmvProductionTarget extends Model
{
    protected $fillable = ['division_id', 'coal_target', 'weekly_coal_target', 'created_by', 'updated_by'];

    protected $attributes = ['location' => 'General', 'shift' => 'All shifts', 'is_general' => true];

    protected function casts(): array
    {
        return ['coal_target' => 'integer', 'weekly_coal_target' => 'integer'];
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function weeklyBagons(): int
    {
        return $this->weekly_coal_target ?? $this->coal_target * (int) config('jmv.production_days_per_week', 7);
    }

    public function dailyMt(): float
    {
        return $this->coal_target * (float) config('jmv.production_mt_per_bagon', 0.435);
    }

    public function weeklyMt(): float
    {
        return $this->weeklyBagons() * (float) config('jmv.production_mt_per_bagon', 0.435);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YatiraSalesStage extends Model
{
    protected $fillable = ['sequence', 'name', 'weight_percent', 'probability_percent', 'is_active'];

    protected function casts(): array
    {
        return ['weight_percent' => 'decimal:2', 'probability_percent' => 'decimal:2', 'is_active' => 'boolean'];
    }
}

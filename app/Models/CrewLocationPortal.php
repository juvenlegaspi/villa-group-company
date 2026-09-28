<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CrewLocationPortal extends Model
{
    protected $fillable = ['token', 'generated_by'];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['token' => Str::random(64)]
        );
    }
}

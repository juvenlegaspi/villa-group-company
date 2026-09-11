<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'division_id'];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function positions() { return $this->hasMany(Position::class); }
}

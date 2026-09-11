<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = ['name'];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function positions() { return $this->hasMany(Position::class); }
    public function userAssignments() { return $this->hasMany(CompanyUserAssignment::class); }
}

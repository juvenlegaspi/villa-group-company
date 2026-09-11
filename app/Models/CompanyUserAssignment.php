<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyUserAssignment extends Model
{
    protected $fillable = ['user_id', 'division_id', 'department_id', 'position_id', 'access_role_id', 'is_primary', 'is_active', 'effective_from', 'effective_until'];
    protected function casts(): array { return ['is_primary' => 'boolean', 'is_active' => 'boolean', 'effective_from' => 'date', 'effective_until' => 'date']; }
    public function user() { return $this->belongsTo(User::class); }
    public function division() { return $this->belongsTo(Division::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function position() { return $this->belongsTo(Position::class); }
    public function accessRole() { return $this->belongsTo(AccessRole::class); }
}

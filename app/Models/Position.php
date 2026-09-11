<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = ['division_id', 'department_id', 'name', 'code', 'legacy_role', 'description', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function division() { return $this->belongsTo(Division::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function permissions() { return $this->belongsToMany(Permission::class, 'permission_position'); }
    public function users() { return $this->hasMany(User::class); }
}

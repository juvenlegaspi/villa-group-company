<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessRole extends Model
{
    protected $fillable = ['name', 'slug', 'scope', 'rank', 'description', 'is_active'];
    protected function casts(): array { return ['rank' => 'integer', 'is_active' => 'boolean']; }
    public function permissions() { return $this->belongsToMany(Permission::class, 'access_role_permission'); }
    public function users() { return $this->hasMany(User::class); }
}

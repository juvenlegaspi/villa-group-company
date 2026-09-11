<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'module', 'description'];
    public function accessRoles() { return $this->belongsToMany(AccessRole::class, 'access_role_permission'); }
    public function positions() { return $this->belongsToMany(Position::class, 'permission_position'); }
}

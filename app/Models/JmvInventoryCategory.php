<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JmvInventoryCategory extends Model { protected $fillable = ['division_id','name','is_active']; protected function casts(): array { return ['is_active'=>'boolean']; } }

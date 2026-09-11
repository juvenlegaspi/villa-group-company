<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JmvInventoryLocation extends Model { protected $fillable = ['division_id','code','name','is_active']; protected function casts(): array { return ['is_active'=>'boolean']; } }

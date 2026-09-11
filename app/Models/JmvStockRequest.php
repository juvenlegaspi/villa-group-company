<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JmvStockRequest extends Model { protected $fillable=['division_id','request_no','requested_by','department_id','status','purpose','approval_remarks','approved_by','approved_at','released_by','released_at']; protected function casts():array{return['approved_at'=>'datetime','released_at'=>'datetime'];} public function items(){return $this->hasMany(JmvStockRequestItem::class);}public function requester(){return $this->belongsTo(User::class,'requested_by');}public function department(){return $this->belongsTo(Department::class);}public function approver(){return $this->belongsTo(User::class,'approved_by');} }

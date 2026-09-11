<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YatiraFixedAssetAudit extends Model
{
    public $timestamps = false;
    protected $fillable = ['yatira_fixed_asset_id', 'user_id', 'action', 'changes', 'ip_address', 'user_agent', 'created_at'];
    protected function casts(): array { return ['changes' => 'array', 'created_at' => 'datetime']; }
    public function asset() { return $this->belongsTo(YatiraFixedAsset::class, 'yatira_fixed_asset_id'); }
    public function user() { return $this->belongsTo(User::class); }
}

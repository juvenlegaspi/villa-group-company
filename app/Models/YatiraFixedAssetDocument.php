<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YatiraFixedAssetDocument extends Model
{
    protected $fillable = ['yatira_fixed_asset_id', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'];

    public function asset() { return $this->belongsTo(YatiraFixedAsset::class, 'yatira_fixed_asset_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}

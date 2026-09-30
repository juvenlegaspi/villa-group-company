<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JmvDailyProductionAttachment extends Model
{
    protected $fillable = ['jmv_daily_production_log_id', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'];
    public function log() { return $this->belongsTo(JmvDailyProductionLog::class, 'jmv_daily_production_log_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechDefectAttachment extends Model
{
    protected $fillable = ['tech_defect_id', 'uploaded_by', 'category', 'original_name', 'path', 'mime_type', 'size'];

    public function techDefect() { return $this->belongsTo(TechDefect::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}

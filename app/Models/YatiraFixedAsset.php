<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YatiraFixedAsset extends Model
{
    protected $table = 'yatira_fixed_assets';

    protected $fillable = [
        'asset_code',
        'asset_name',
        'category',
        'assigned_to',
        'location',
        'asset_condition',
        'status',
        'date_acquired',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'date_acquired' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YatiraFixedAsset extends Model
{
    use SoftDeletes;
    protected $table = 'yatira_fixed_assets';

    protected $fillable = [
        'division_id',
        'asset_code',
        'asset_name',
        'serial_number',
        'category',
        'assigned_to',
        'assigned_user_id',
        'assigned_department_id',
        'location',
        'asset_condition',
        'status',
        'date_acquired',
        'acquisition_cost',
        'remarks',
        'document_path',
        'document_original_name',
        'created_by',
        'updated_by',
        'disposed_at',
        'disposal_reason',
    ];

    protected $casts = [
        'date_acquired' => 'date',
        'acquisition_cost' => 'decimal:2',
        'disposed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function assignedUser() { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function assignedDepartment() { return $this->belongsTo(Department::class, 'assigned_department_id'); }
    public function audits() { return $this->hasMany(YatiraFixedAssetAudit::class)->latest('created_at'); }
    public function documents() { return $this->hasMany(YatiraFixedAssetDocument::class)->latest(); }
}

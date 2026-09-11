<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalAuthority extends Model
{
    public const MODULES = [
        'technical_defects' => 'Technical & Defects',
        'procurement' => 'Procurement',
        'inventory' => 'Inventory',
        'certificates' => 'Vessel Certificates',
        'dry_docking' => 'Dry Docking',
        'voyage_operations' => 'Voyage Operations',
    ];

    /**
     * Only expose authorities that are enforced by an active workflow.
     */
    public const MODULES_BY_COMPANY = [
        'villa shipping lines' => ['technical_defects'],
    ];

    public static function modulesForCompany(?string $companyName): array
    {
        $key = strtolower(trim((string) $companyName));
        $moduleKeys = self::MODULES_BY_COMPANY[$key] ?? [];

        return collect($moduleKeys)
            ->mapWithKeys(fn (string $module): array => [$module => self::MODULES[$module]])
            ->all();
    }

    protected $fillable = ['user_id', 'division_id', 'department_id', 'module', 'action', 'approval_level', 'amount_limit', 'is_active', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return ['approval_level' => 'integer', 'amount_limit' => 'decimal:2', 'is_active' => 'boolean', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}

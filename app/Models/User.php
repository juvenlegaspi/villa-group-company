<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ASSIGNABLE_ROLES = [
        'admin',
        'it',
        'manager',
        'captain',
        'staff',
        'r&d',
        'hr',
        'purchaser',
        'owner',
    ];

    protected $fillable = [
        'name',
        'lastname',
        'username',
        'email',
        'cell_number',
        'avatar_path',
        'password',
        'created_by',
        'is_admin',
        'role',
        'department_id',
        'position_id',
        'access_role_id',
        'division_id',
        'must_change_password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position() { return $this->belongsTo(Position::class); }
    public function accessRole() { return $this->belongsTo(AccessRole::class); }
    public function companyAssignments() { return $this->hasMany(CompanyUserAssignment::class); }
    public function approvalAuthorities() { return $this->hasMany(ApprovalAuthority::class); }
    public function vesselAssignments() { return $this->hasMany(UserVesselAssignment::class); }
    public function assignedVessels() { return $this->belongsToMany(Vessel::class, 'user_vessel_assignments')->withPivot(['assigned_by', 'is_primary', 'is_active', 'effective_from', 'effective_until'])->withTimestamps(); }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'owner') return $permission === 'reports.view.executive';
        $this->loadMissing(['accessRole.permissions', 'position.permissions']);
        return $this->accessRole?->permissions->contains('slug', $permission)
            || $this->position?->permissions->contains('slug', $permission)
            || ($this->is_admin && $this->role !== 'owner');
    }

    public function hasApprovalAuthority(string $module, ?int $divisionId = null): bool
    {
        return $this->approvalAuthorities()->where('module', $module)->where('is_active', true)
            ->when($divisionId, fn ($query) => $query->where('division_id', $divisionId))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->exists();
    }

    public function vessel()
    {
        return $this->hasOne(Vessel::class, 'captain_id');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'owner'], true) || (bool) $this->is_admin;
    }

    public function isExecutiveViewer(): bool
    {
        if ($this->role === 'owner') {
            return true;
        }

        $this->loadMissing('accessRole');

        return $this->accessRole?->slug === 'executive-viewer';
    }

    public function canViewExecutiveDashboards(): bool
    {
        return $this->isExecutiveViewer() || $this->isSystemAdministrator();
    }

    /**
     * Owners are dashboard viewers. User administration is intentionally
     * reserved for system administrators and legacy admin-enabled accounts.
     */
    public function canManageUsers(): bool
    {
        return $this->role !== 'owner'
            && ($this->role === 'admin' || (bool) $this->is_admin || $this->hasPermission('users.manage.company') || $this->hasPermission('users.manage.system'));
    }

    public function canManageAllCompanies(): bool
    {
        return $this->role !== 'owner' && ($this->is_admin || $this->role === 'admin' || $this->hasPermission('companies.access.all'));
    }

    public function isSystemAdministrator(): bool
    {
        if ($this->role === 'owner') return false;
        $this->loadMissing('accessRole');

        return $this->role === 'admin'
            || (bool) $this->is_admin
            || $this->accessRole?->slug === 'system-administrator';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}

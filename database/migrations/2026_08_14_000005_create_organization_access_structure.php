<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 80);
            $table->string('legacy_role', 40)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['division_id', 'department_id', 'code']);
            $table->index(['division_id', 'is_active']);
        });

        Schema::create('access_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('scope', 20)->default('company');
            $table->unsignedTinyInteger('rank')->default(10);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module', 80);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('module');
        });

        Schema::create('access_role_permission', function (Blueprint $table): void {
            $table->foreignId('access_role_id')->constrained('access_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['access_role_id', 'permission_id']);
        });

        Schema::create('permission_position', function (Blueprint $table): void {
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['position_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('position_id')->nullable()->after('department_id')->constrained('positions')->nullOnDelete();
            $table->foreignId('access_role_id')->nullable()->after('position_id')->constrained('access_roles')->nullOnDelete();
        });

        Schema::create('company_user_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('access_role_id')->nullable()->constrained('access_roles')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'division_id']);
            $table->index(['division_id', 'is_active']);
        });

        Schema::create('approval_authorities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('module', 80);
            $table->string('action', 40)->default('approve');
            $table->unsignedTinyInteger('approval_level')->default(1);
            $table->decimal('amount_limit', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->index(['division_id', 'module', 'is_active']);
        });

        $now = now();
        $roles = [
            ['name' => 'System Administrator', 'slug' => 'system-administrator', 'scope' => 'system', 'rank' => 100, 'description' => 'Full platform configuration and user administration.'],
            ['name' => 'Company Administrator', 'slug' => 'company-administrator', 'scope' => 'company', 'rank' => 80, 'description' => 'Manages users and operational access within one company.'],
            ['name' => 'Company Approver', 'slug' => 'company-approver', 'scope' => 'company', 'rank' => 60, 'description' => 'Reviews and approves assigned company workflows.'],
            ['name' => 'Standard User', 'slug' => 'standard-user', 'scope' => 'company', 'rank' => 30, 'description' => 'Performs operational work assigned to the employee.'],
            ['name' => 'Viewer', 'slug' => 'viewer', 'scope' => 'company', 'rank' => 10, 'description' => 'Read-only access to authorized records.'],
            ['name' => 'Executive Viewer', 'slug' => 'executive-viewer', 'scope' => 'system', 'rank' => 20, 'description' => 'Owner/executive dashboard and consolidated report access.'],
        ];
        foreach ($roles as $role) DB::table('access_roles')->insert([...$role, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        $permissions = [
            ['users.manage.system', 'Manage users across all companies', 'users'],
            ['users.manage.company', 'Manage users within an assigned company', 'users'],
            ['companies.access.all', 'Access all companies', 'companies'],
            ['reports.view.executive', 'View executive dashboards and reports', 'reports'],
            ['tech_defects.create', 'Create technical defect reports', 'tech_defects'],
            ['tech_defects.view_assigned', 'View assigned technical defects', 'tech_defects'],
            ['tech_defects.view_company', 'View all technical defects in the company', 'tech_defects'],
            ['tech_defects.start_repair', 'Start technical defect repairs', 'tech_defects'],
            ['tech_defects.update_closeout', 'Update repair close-out records', 'tech_defects'],
            ['tech_defects.upload_evidence', 'Upload technical defect evidence', 'tech_defects'],
            ['tech_defects.request_support', 'Request third-party technical support', 'tech_defects'],
            ['tech_defects.submit', 'Submit repairs for verification', 'tech_defects'],
            ['tech_defects.verify', 'Verify or return completed repairs', 'tech_defects'],
            ['tech_defects.archive', 'Archive and restore technical defect reports', 'tech_defects'],
        ];
        foreach ($permissions as [$slug, $name, $module]) DB::table('permissions')->insert(['slug' => $slug, 'name' => $name, 'module' => $module, 'created_at' => $now, 'updated_at' => $now]);

        $roleIds = DB::table('access_roles')->pluck('id', 'slug');
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $grants = [
            'system-administrator' => array_keys($permissionIds->all()),
            'company-administrator' => ['users.manage.company', 'tech_defects.create', 'tech_defects.view_company', 'tech_defects.start_repair', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify', 'tech_defects.archive'],
            'company-approver' => ['tech_defects.view_company', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.submit', 'tech_defects.verify'],
            'standard-user' => ['tech_defects.view_assigned', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.submit'],
            'viewer' => ['tech_defects.view_assigned'],
            'executive-viewer' => ['reports.view.executive'],
        ];
        foreach ($grants as $roleSlug => $slugs) foreach ($slugs as $slug) DB::table('access_role_permission')->insert(['access_role_id' => $roleIds[$roleSlug], 'permission_id' => $permissionIds[$slug]]);

        DB::table('users')->orderBy('id')->each(function (object $user) use ($roleIds, $permissionIds, $now): void {
            if (! $user->division_id || ! $user->department_id) return;
            $positionName = match (strtolower((string) $user->role)) {
                'captain' => 'Vessel Captain', 'purchaser' => 'Purchaser', 'it' => 'IT Specialist', 'hr' => 'HR Officer',
                'r&d' => 'R&D Specialist', 'manager' => 'Department Manager', 'owner' => 'Executive', 'admin' => 'System Administrator', default => 'Staff',
            };
            $code = Str::slug($positionName);
            $positionId = DB::table('positions')->where(['division_id' => $user->division_id, 'department_id' => $user->department_id, 'code' => $code])->value('id');
            if (! $positionId) $positionId = DB::table('positions')->insertGetId(['division_id' => $user->division_id, 'department_id' => $user->department_id, 'name' => $positionName, 'code' => $code, 'legacy_role' => strtolower((string) $user->role), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $roleSlug = match (true) {
                strtolower((string) $user->role) === 'owner' => 'executive-viewer',
                strtolower((string) $user->role) === 'admin' || (bool) $user->is_admin => 'system-administrator',
                strtolower((string) $user->role) === 'manager' => 'company-approver',
                default => 'standard-user',
            };
            $accessRoleId = $roleIds[$roleSlug];
            DB::table('users')->where('id', $user->id)->update(['position_id' => $positionId, 'access_role_id' => $accessRoleId]);
            DB::table('company_user_assignments')->insert(['user_id' => $user->id, 'division_id' => $user->division_id, 'department_id' => $user->department_id, 'position_id' => $positionId, 'access_role_id' => $accessRoleId, 'is_primary' => true, 'is_active' => (bool) $user->status, 'created_at' => $now, 'updated_at' => $now]);
            if (strtolower((string) $user->role) === 'manager') DB::table('approval_authorities')->insert(['user_id' => $user->id, 'division_id' => $user->division_id, 'department_id' => $user->department_id, 'module' => 'technical_defects', 'action' => 'approve', 'approval_level' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            if (strtolower((string) $user->role) === 'captain') foreach (['tech_defects.create', 'tech_defects.start_repair', 'tech_defects.request_support'] as $permission) DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionIds[$permission]]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_authorities');
        Schema::dropIfExists('company_user_assignments');
        Schema::table('users', function (Blueprint $table): void { $table->dropConstrainedForeignId('access_role_id'); $table->dropConstrainedForeignId('position_id'); });
        Schema::dropIfExists('permission_position');
        Schema::dropIfExists('access_role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('access_roles');
        Schema::dropIfExists('positions');
    }
};

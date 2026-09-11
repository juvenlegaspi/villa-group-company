<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'yatira.sales.view' => 'View Yatira sales monitoring',
        'yatira.sales.create' => 'Register Yatira sales leads',
        'yatira.sales.update' => 'Update Yatira leads and sales stages',
        'yatira.sales.manage' => 'Manage all Yatira sales leads',
    ];

    public function up(): void
    {
        Schema::create('yatira_sales_stages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('sequence')->unique();
            $table->string('name')->unique();
            $table->decimal('weight_percent', 5, 2);
            $table->decimal('probability_percent', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('yatira_sales_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->string('lead_code', 40)->unique();
            $table->date('date_registered');
            $table->string('client_name');
            $table->string('contact_person')->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('client_address')->nullable();
            $table->string('project_name');
            $table->string('project_type')->nullable();
            $table->text('project_description')->nullable();
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->string('project_location');
            $table->string('lead_source')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_stage_id')->constrained('yatira_sales_stages')->restrictOnDelete();
            $table->decimal('probability_percent', 5, 2);
            $table->string('status', 20)->default('ACTIVE');
            $table->text('remarks')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['division_id', 'status']);
            $table->index(['sales_stage_id', 'assigned_agent_id']);
            $table->index('expected_close_date');
        });

        Schema::create('yatira_sales_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('yatira_sales_lead_id')->constrained('yatira_sales_leads')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->foreignId('from_stage_id')->nullable()->constrained('yatira_sales_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('yatira_sales_stages')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->json('changes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['yatira_sales_lead_id', 'created_at'], 'yatira_sales_history_timeline');
        });

        foreach ([
            [1, 'New Lead Registration', 8, 8], [2, 'Management Review', 8, 16], [3, 'Site Inspection', 8, 24],
            [4, 'Cost Estimate', 8, 32], [5, 'Proposal Approval', 8, 40], [6, 'Proposal Sent', 10, 50],
            [7, 'Negotiation', 15, 65], [8, 'Contract Signing', 15, 80], [9, 'Awarded', 20, 100],
        ] as [$sequence,$name,$weight,$probability]) {
            DB::table('yatira_sales_stages')->insert(['sequence' => $sequence, 'name' => $name, 'weight_percent' => $weight, 'probability_percent' => $probability, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], ['name' => $name, 'module' => 'yatira', 'description' => $name.'.', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->seedOrganizationAccess();
    }

    private function seedOrganizationAccess(): void
    {
        $divisionId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['yatira'])->value('id');
        if (! $divisionId) {
            return;
        }
        $departmentId = DB::table('departments')->where('division_id', $divisionId)->whereRaw('LOWER(name) = ?', ['sales and business development'])->value('id');
        if (! $departmentId) {
            $departmentId = DB::table('departments')->insertGetId(['division_id' => $divisionId, 'name' => 'Sales and Business Development', 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['Sales Manager', 'sales-manager', 'manager'], ['Sales Agent', 'sales-agent', 'staff']] as [$name,$code,$role]) {
            $positionId = DB::table('positions')->where(['division_id' => $divisionId, 'department_id' => $departmentId, 'code' => $code])->value('id');
            if (! $positionId) {
                $positionId = DB::table('positions')->insertGetId(['division_id' => $divisionId, 'department_id' => $departmentId, 'name' => $name, 'code' => $code, 'legacy_role' => $role, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $slugs = $code === 'sales-manager' ? array_keys(self::PERMISSIONS) : ['yatira.sales.view', 'yatira.sales.create', 'yatira.sales.update'];
            foreach (DB::table('permissions')->whereIn('slug', $slugs)->pluck('id') as $permissionId) {
                DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionId]);
            }
        }
        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        foreach (['system-administrator', 'company-administrator'] as $roleSlug) {
            $roleId = DB::table('access_roles')->where('slug', $roleSlug)->value('id');
            if ($roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('yatira_sales_histories');
        Schema::dropIfExists('yatira_sales_leads');
        Schema::dropIfExists('yatira_sales_stages');
        $ids = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('permission_position')->whereIn('permission_id', $ids)->delete();
        DB::table('access_role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};

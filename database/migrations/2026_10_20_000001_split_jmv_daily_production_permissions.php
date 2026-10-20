<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'jmv.operations.daily_production.create' => 'Create JMV daily production logs',
        'jmv.operations.daily_production.update' => 'Update JMV daily production logs',
        'jmv.operations.daily_production.submit' => 'Submit JMV daily production logs for review',
        'jmv.operations.daily_production.approve' => 'Approve JMV daily production logs',
    ];

    public function up(): void
    {
        $statusWasAdded = $this->addWorkflowColumns();

        if ($statusWasAdded) {
            DB::table('jmv_daily_production_logs')->update([
                'status' => 'COMPLETED',
                'completed_at' => DB::raw('updated_at'),
            ]);
        }

        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'module' => 'jmv', 'description' => $name.'.', 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $divisionId = DB::table('divisions')->whereRaw('LOWER(TRIM(name)) = ?', ['jmv'])->value('id');
        if (! $divisionId) return;

        $departmentId = DB::table('departments')
            ->where('division_id', $divisionId)
            ->whereRaw('LOWER(TRIM(name)) = ?', ['mining operations'])
            ->value('id');

        if ($departmentId) {
            DB::table('positions')->updateOrInsert(
                ['division_id' => $divisionId, 'department_id' => $departmentId, 'code' => 'admin-staff'],
                [
                    'name' => 'Admin Staff',
                    'legacy_role' => 'staff',
                    'description' => 'Encodes, verifies, and submits daily production accomplishment reports.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $this->sync([
            'admin-staff' => ['view', 'create', 'update', 'submit'],
            'mine-operations-manager' => ['view', 'create', 'update', 'submit', 'approve', 'reports.view'],
            'mine-supervisor' => ['view', 'create', 'update', 'submit', 'approve', 'reports.view'],
            'chief-geologist' => ['view', 'reports.view'],
            'geologist' => ['view'],
        ]);
    }

    public function down(): void
    {
        $this->sync([
            'mine-operations-manager' => ['view', 'manage', 'reports.view'],
            'mine-supervisor' => ['view', 'manage', 'reports.view'],
            'department-manager' => ['view', 'manage', 'reports.view'],
            'chief-geologist' => ['view', 'reports.view'],
            'geologist' => ['view', 'manage'],
        ]);

        DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->delete();

        Schema::table('jmv_daily_production_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('jmv_daily_production_logs', 'submitted_by')) {
                $table->dropForeign(['submitted_by']);
            }
            if (Schema::hasColumn('jmv_daily_production_logs', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
            }
        });

        foreach ([
            'revision_count', 'completed_at', 'review_remarks', 'reviewed_at',
            'reviewed_by', 'submitted_at', 'submitted_by', 'status',
        ] as $column) {
            if (Schema::hasColumn('jmv_daily_production_logs', $column)) {
                Schema::table('jmv_daily_production_logs', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function addWorkflowColumns(): bool
    {
        $tableName = 'jmv_daily_production_logs';
        $statusWasAdded = ! Schema::hasColumn($tableName, 'status');

        if ($statusWasAdded) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('status', 30)->default('DRAFT')->after('remarks')->index();
            });
        }
        if (! Schema::hasColumn($tableName, 'submitted_by')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('submitted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn($tableName, 'submitted_at')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            });
        }
        if (! Schema::hasColumn($tableName, 'reviewed_by')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn($tableName, 'reviewed_at')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            });
        }
        if (! Schema::hasColumn($tableName, 'review_remarks')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->text('review_remarks')->nullable()->after('reviewed_at');
            });
        }
        if (! Schema::hasColumn($tableName, 'completed_at')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('completed_at')->nullable()->after('review_remarks');
            });
        }
        if (! Schema::hasColumn($tableName, 'revision_count')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedInteger('revision_count')->default(0)->after('completed_at');
            });
        }

        return $statusWasAdded;
    }

    /** @param array<string, array<int, string>> $rules */
    private function sync(array $rules): void
    {
        $divisionId = DB::table('divisions')->whereRaw('LOWER(TRIM(name)) = ?', ['jmv'])->value('id');
        if (! $divisionId) return;

        $positions = DB::table('positions')->where('division_id', $divisionId)->get(['id', 'code']);
        $permissionIds = DB::table('permissions')
            ->where('slug', 'like', 'jmv.operations.daily_production.%')
            ->pluck('id', 'slug');

        DB::table('permission_position')
            ->whereIn('position_id', $positions->pluck('id'))
            ->whereIn('permission_id', $permissionIds->values())
            ->delete();

        foreach ($positions as $position) {
            foreach ($rules[$position->code] ?? [] as $suffix) {
                $permissionId = $permissionIds['jmv.operations.daily_production.'.$suffix] ?? null;
                if ($permissionId) {
                    DB::table('permission_position')->insertOrIgnore([
                        'position_id' => $position->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }
};

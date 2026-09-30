<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'jmv.operations.daily_production.view' => 'View JMV daily production monitoring',
        'jmv.operations.daily_production.manage' => 'Create and update JMV daily production logs',
        'jmv.operations.daily_production.reports.view' => 'Export JMV daily production reports',
    ];

    public function up(): void
    {
        Schema::create('jmv_daily_production_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->string('log_no', 40)->unique();
            $table->date('log_date');
            $table->string('location', 255);
            $table->string('shift', 100);
            $table->string('leadman', 255);
            $table->string('foreman', 255);
            $table->unsignedInteger('total_workers');
            $table->decimal('timber_used', 12, 2)->default(0);
            $table->string('timber_unit', 30)->default('PCS');
            $table->decimal('nails_used', 12, 2)->default(0);
            $table->string('nails_unit', 30)->default('PCS');
            $table->decimal('fuel_consumed', 12, 2)->default(0);
            $table->string('fuel_unit', 20)->default('LTRS');
            $table->unsignedInteger('soil_output')->default(0);
            $table->unsignedInteger('coal_output')->default(0);
            $table->integer('excess_deficit')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['division_id', 'log_date', 'location', 'shift'], 'jmv_daily_log_unique');
            $table->index(['division_id', 'log_date'], 'jmv_daily_log_date_index');
        });

        Schema::create('jmv_daily_production_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jmv_daily_production_log_id');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('jmv_daily_production_log_id', 'jmv_prod_attachment_log_fk')->references('id')->on('jmv_daily_production_logs')->cascadeOnDelete();
        });

        Schema::create('jmv_daily_production_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jmv_daily_production_log_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['jmv_daily_production_log_id', 'created_at'], 'jmv_daily_audit_timeline');
            $table->foreign('jmv_daily_production_log_id', 'jmv_prod_audit_log_fk')->references('id')->on('jmv_daily_production_logs')->cascadeOnDelete();
        });

        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'module' => 'jmv', 'description' => $name.'.', 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $jmvId = DB::table('divisions')->whereRaw('LOWER(TRIM(name)) = ?', ['jmv'])->value('id');
        if (! $jmvId) return;

        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id', 'slug');
        $rules = [
            'mine-operations-manager' => array_keys(self::PERMISSIONS),
            'mine-supervisor' => array_keys(self::PERMISSIONS),
            'department-manager' => array_keys(self::PERMISSIONS),
            'chief-geologist' => ['jmv.operations.daily_production.view', 'jmv.operations.daily_production.reports.view'],
            'geologist' => ['jmv.operations.daily_production.view', 'jmv.operations.daily_production.manage'],
        ];
        foreach ($rules as $positionCode => $slugs) {
            foreach (DB::table('positions')->where('division_id', $jmvId)->where('code', $positionCode)->pluck('id') as $positionId) {
                foreach ($slugs as $slug) {
                    if ($permissionIds->has($slug)) {
                        DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionIds[$slug]]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jmv_daily_production_audits');
        Schema::dropIfExists('jmv_daily_production_attachments');
        Schema::dropIfExists('jmv_daily_production_logs');
        DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->delete();
    }
};

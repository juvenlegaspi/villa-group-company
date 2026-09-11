<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_vessel_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vessel_id')->constrained('vessels')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'vessel_id']);
            $table->index(['vessel_id', 'is_active']);
            $table->index(['user_id', 'is_active', 'effective_from', 'effective_until'], 'user_vessel_active_dates');
        });

        // Preserve every legacy captain assignment. The old captain_id column
        // remains available during the transition for reports and integrations.
        $now = now();
        DB::table('vessels')->whereNotNull('captain_id')->orderBy('id')->each(function (object $vessel) use ($now): void {
            DB::table('user_vessel_assignments')->insertOrIgnore([
                'user_id' => $vessel->captain_id,
                'vessel_id' => $vessel->id,
                'assigned_by' => null,
                'is_primary' => true,
                'is_active' => true,
                'effective_from' => $now->toDateString(),
                'effective_until' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        // Existing technical assignees must retain access to the vessels for
        // which they already have active or historical defect responsibility.
        if (Schema::hasTable('tech_defects')) {
            DB::table('tech_defects')->whereNotNull('assigned_to_user_id')
                ->select(['assigned_to_user_id', 'vessel_id'])->distinct()->orderBy('vessel_id')
                ->each(function (object $defect) use ($now): void {
                    DB::table('user_vessel_assignments')->insertOrIgnore([
                        'user_id' => $defect->assigned_to_user_id,
                        'vessel_id' => $defect->vessel_id,
                        'assigned_by' => null,
                        'is_primary' => false,
                        'is_active' => true,
                        'effective_from' => $now->toDateString(),
                        'effective_until' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_vessel_assignments');
    }
};

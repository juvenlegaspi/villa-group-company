<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jmv_production_targets', function (Blueprint $table): void {
            $table->boolean('is_general')->nullable();
            $table->unique(['division_id', 'is_general'], 'jmv_one_general_production_target');
        });
        // Preserve earlier mine/shift records and their audits; use the latest setting as the general target.
        foreach (DB::table('jmv_production_targets')->distinct()->pluck('division_id') as $divisionId) {
            $id = DB::table('jmv_production_targets')->where('division_id', $divisionId)->orderByDesc('updated_at')->orderByDesc('id')->value('id');
            DB::table('jmv_production_targets')->where('id', $id)->update(['is_general' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('jmv_production_targets', function (Blueprint $table): void {
            $table->dropUnique('jmv_one_general_production_target');
            $table->dropColumn('is_general');
        });
    }
};

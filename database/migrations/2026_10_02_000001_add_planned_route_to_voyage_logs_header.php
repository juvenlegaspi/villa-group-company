<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyage_logs_header', function (Blueprint $table): void {
            $table->json('planned_route_geojson')->nullable();
            $table->string('planned_route_provider', 50)->nullable();
            $table->decimal('planned_route_distance_nm', 12, 2)->nullable();
            $table->decimal('planned_route_duration_hours', 12, 2)->nullable();
            $table->unsignedBigInteger('planned_route_approved_by')->nullable();
            $table->timestamp('planned_route_approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('voyage_logs_header', function (Blueprint $table): void {
            $table->dropColumn([
                'planned_route_geojson',
                'planned_route_provider',
                'planned_route_distance_nm',
                'planned_route_duration_hours',
                'planned_route_approved_by',
                'planned_route_approved_at',
            ]);
        });
    }
};

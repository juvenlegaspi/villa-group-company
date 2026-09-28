<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ports')) {
            Schema::table('ports', function (Blueprint $table): void {
                if (! Schema::hasColumn('ports', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('ports', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
            });
        }

        if (Schema::hasTable('voyage_logs_header')) {
            Schema::table('voyage_logs_header', function (Blueprint $table): void {
                foreach ([
                    'origin_latitude', 'origin_longitude',
                    'destination_latitude', 'destination_longitude',
                    'current_latitude', 'current_longitude',
                ] as $column) {
                    if (! Schema::hasColumn('voyage_logs_header', $column)) {
                        $table->decimal($column, 10, 7)->nullable();
                    }
                }
            });
        }

        if (! Schema::hasTable('vessel_position_logs')) {
            Schema::create('vessel_position_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vessel_id');
                $table->unsignedBigInteger('voyage_id');
                $table->unsignedBigInteger('voyage_activity_id')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy_meters', 10, 2)->nullable();
                $table->string('location_name')->nullable();
                $table->string('source', 30)->default('map_pin');
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->dateTime('recorded_at');
                $table->timestamps();

                $table->index(['vessel_id', 'recorded_at']);
                $table->index(['voyage_id', 'recorded_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_position_logs');

        // Coordinate columns are intentionally retained so production rollback
        // cannot discard recorded vessel positions.
    }
};

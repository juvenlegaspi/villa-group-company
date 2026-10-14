<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vessels')) {
            Schema::table('vessels', function (Blueprint $table): void {
                if (! Schema::hasColumn('vessels', 'mmsi')) {
                    $table->string('mmsi', 9)->nullable()->unique();
                }
                if (! Schema::hasColumn('vessels', 'ais_enabled')) {
                    $table->boolean('ais_enabled')->default(false);
                }
                if (! Schema::hasColumn('vessels', 'ais_last_seen_at')) {
                    $table->timestamp('ais_last_seen_at')->nullable();
                }
            });

            // Known AIS identities supplied by the vessel records. Existing values are never overwritten.
            DB::table('vessels')
                ->where('imo_number', '9084023')
                ->whereNull('mmsi')
                ->update(['mmsi' => '548235468', 'ais_enabled' => true]);

            DB::table('vessels')
                ->where('imo_number', '9035656')
                ->whereNull('mmsi')
                ->update(['mmsi' => '548365489', 'ais_enabled' => true]);
        }

        if (Schema::hasTable('vessel_position_logs')) {
            Schema::table('vessel_position_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('vessel_position_logs', 'speed_over_ground')) {
                    $table->decimal('speed_over_ground', 7, 2)->nullable();
                }
                if (! Schema::hasColumn('vessel_position_logs', 'course_over_ground')) {
                    $table->decimal('course_over_ground', 6, 2)->nullable();
                }
                if (! Schema::hasColumn('vessel_position_logs', 'true_heading')) {
                    $table->unsignedSmallInteger('true_heading')->nullable();
                }
                if (! Schema::hasColumn('vessel_position_logs', 'ais_message_type')) {
                    $table->string('ais_message_type', 50)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vessel_position_logs')) {
            $columns = collect([
                'speed_over_ground',
                'course_over_ground',
                'true_heading',
                'ais_message_type',
            ])->filter(fn (string $column): bool => Schema::hasColumn('vessel_position_logs', $column))->all();

            if ($columns !== []) {
                Schema::table('vessel_position_logs', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (Schema::hasTable('vessels')) {
            if (Schema::hasColumn('vessels', 'mmsi')) {
                Schema::table('vessels', function (Blueprint $table): void {
                    $table->dropUnique(['mmsi']);
                });
            }

            $columns = collect(['mmsi', 'ais_enabled', 'ais_last_seen_at'])
                ->filter(fn (string $column): bool => Schema::hasColumn('vessels', $column))
                ->all();

            if ($columns !== []) {
                Schema::table('vessels', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }
};

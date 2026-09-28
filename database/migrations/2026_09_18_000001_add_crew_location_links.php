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
        if (Schema::hasTable('vessels') && ! Schema::hasColumn('vessels', 'location_update_token')) {
            Schema::table('vessels', function (Blueprint $table): void {
                $table->string('location_update_token', 64)->nullable()->unique();
                $table->timestamp('location_update_token_created_at')->nullable();
            });

            DB::table('vessels')->select('id')->orderBy('id')->eachById(function ($vessel): void {
                DB::table('vessels')->where('id', $vessel->id)->update([
                    'location_update_token' => Str::random(64),
                    'location_update_token_created_at' => now(),
                ]);
            });
        }

        if (Schema::hasTable('vessel_position_logs')) {
            $hasReporterName = Schema::hasColumn('vessel_position_logs', 'reporter_name');
            $hasIpAddress = Schema::hasColumn('vessel_position_logs', 'ip_address');
            $hasUserAgent = Schema::hasColumn('vessel_position_logs', 'user_agent');

            if (! $hasReporterName || ! $hasIpAddress || ! $hasUserAgent) {
                Schema::table('vessel_position_logs', function (Blueprint $table) use ($hasReporterName, $hasIpAddress, $hasUserAgent): void {
                    if (! $hasReporterName) {
                        $table->string('reporter_name')->nullable();
                    }
                    if (! $hasIpAddress) {
                        $table->string('ip_address', 45)->nullable();
                    }
                    if (! $hasUserAgent) {
                        $table->text('user_agent')->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vessel_position_logs')) {
            $columns = collect(['reporter_name', 'ip_address', 'user_agent'])
                ->filter(fn (string $column) => Schema::hasColumn('vessel_position_logs', $column))
                ->all();
            if ($columns !== []) {
                Schema::table('vessel_position_logs', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (Schema::hasTable('vessels') && Schema::hasColumn('vessels', 'location_update_token')) {
            Schema::table('vessels', function (Blueprint $table): void {
                $table->dropUnique(['location_update_token']);
                $table->dropColumn(['location_update_token', 'location_update_token_created_at']);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voyage_logs_header') && Schema::hasColumn('voyage_logs_header', 'arrival_date')) {
            Schema::table('voyage_logs_header', function (Blueprint $table): void {
                $table->dateTime('arrival_date')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Keep DATETIME on rollback so saved ETA times are never truncated.
    }
};

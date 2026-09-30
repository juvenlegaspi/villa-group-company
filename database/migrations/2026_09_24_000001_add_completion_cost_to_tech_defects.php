<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tech_defects', 'completion_cost')) {
            Schema::table('tech_defects', function (Blueprint $table): void {
                $table->decimal('completion_cost', 12, 2)->nullable()->after('date_completed');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tech_defects', 'completion_cost')) {
            Schema::table('tech_defects', function (Blueprint $table): void {
                $table->dropColumn('completion_cost');
            });
        }
    }
};

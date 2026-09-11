<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('departments', 'division_id')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->foreignId('division_id')->nullable()->after('name')
                    ->constrained('divisions')->nullOnDelete();
            });
        }

        // A department is assigned automatically only when all of its current
        // users belong to one division. Shared/unused departments remain global.
        DB::table('departments')->orderBy('id')->each(function (object $department): void {
            $divisionIds = DB::table('users')
                ->where('department_id', $department->id)
                ->whereNotNull('division_id')
                ->distinct()
                ->pluck('division_id');

            if ($divisionIds->count() === 1) {
                DB::table('departments')
                    ->where('id', $department->id)
                    ->update(['division_id' => $divisionIds->first()]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('departments', 'division_id')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('division_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $villaGroupExists = DB::table('divisions')
            ->whereRaw('LOWER(name) = ?', ['villa group'])
            ->exists();

        if (! $villaGroupExists) {
            DB::table('divisions')
                ->whereRaw('LOWER(name) = ?', ['corporate'])
                ->update(['name' => 'Villa Group', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $corporateExists = DB::table('divisions')
            ->whereRaw('LOWER(name) = ?', ['corporate'])
            ->exists();

        if (! $corporateExists) {
            DB::table('divisions')
                ->whereRaw('LOWER(name) = ?', ['villa group'])
                ->update(['name' => 'Corporate', 'updated_at' => now()]);
        }
    }
};

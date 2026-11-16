<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('divisions')->whereRaw('LOWER(name) = ?', ['haruhay'])->exists()) {
            DB::table('divisions')->insert([
                'name' => 'Haruhay',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Retain the company and any user assignments or operational records.
    }
};

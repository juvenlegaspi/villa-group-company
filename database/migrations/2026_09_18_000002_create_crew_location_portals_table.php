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
        if (! Schema::hasTable('crew_location_portals')) {
            Schema::create('crew_location_portals', function (Blueprint $table): void {
                $table->id();
                $table->string('token', 64)->unique();
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->timestamps();
            });

            DB::table('crew_location_portals')->insert([
                'id' => 1,
                'token' => Str::random(64),
                'generated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_location_portals');
    }
};

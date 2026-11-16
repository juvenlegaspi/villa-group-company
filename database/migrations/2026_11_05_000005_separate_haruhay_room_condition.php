<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('haruhay_units', function (Blueprint $table) {
            $table->string('status', 20)->default('Vacant')->change();
            $table->string('room_condition', 30)->default('Ready');
            $table->index(['property_id', 'room_condition']);
        });
        DB::table('haruhay_units')->where('status', 'Maintenance')->update([
            'status' => 'Vacant', 'room_condition' => 'Under Maintenance',
        ]);
    }

    public function down(): void
    {
        DB::table('haruhay_units')->where('status', 'Vacant')->where('room_condition', 'Under Maintenance')->update(['status' => 'Maintenance']);
        Schema::table('haruhay_units', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'room_condition']);
            $table->dropColumn('room_condition');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('haruhay_units')->orderBy('id')->each(function ($room) {
                DB::table('haruhay_units')->where('id', $room->id)->update(['unit_no' => 'pending-'.\Illuminate\Support\Str::uuid()]);
            });
            DB::table('haruhay_units')->orderBy('id')->each(function ($room) {
                DB::table('haruhay_units')->where('id', $room->id)->update(['unit_no' => 'DR-'.str_pad((string) $room->id, 2, '0', STR_PAD_LEFT)]);
            });
        });
        Schema::table('haruhay_units', function (Blueprint $table) { $table->unique('unit_no'); });
    }

    public function down(): void
    {
        Schema::table('haruhay_units', function (Blueprint $table) { $table->dropUnique(['unit_no']); });
    }
};

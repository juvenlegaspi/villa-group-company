<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haruhay_collections', function (Blueprint $table) {
            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('haruhay_collections', fn (Blueprint $table) => $table->dropColumn(['proof_path', 'proof_name']));
    }
};

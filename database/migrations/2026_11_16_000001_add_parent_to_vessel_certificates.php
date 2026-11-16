<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $table): void {
            $table->foreignId('parent_certificate_id')->nullable()->constrained('vessel_certificates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vessel_certificates', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_certificate_id'));
    }
};

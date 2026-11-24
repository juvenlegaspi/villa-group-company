<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yatira_fixed_assets', function (Blueprint $table): void {
            $table->foreignId('vessel_id')->nullable()->constrained('vessels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('yatira_fixed_assets', fn (Blueprint $table) => $table->dropConstrainedForeignId('vessel_id'));
    }
};

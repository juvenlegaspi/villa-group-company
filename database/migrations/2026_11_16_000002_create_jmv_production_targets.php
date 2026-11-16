<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jmv_production_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->string('location');
            $table->string('shift', 100);
            $table->unsignedInteger('coal_target')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['division_id', 'location', 'shift'], 'jmv_production_target_mine_shift_unique');
        });
        Schema::create('jmv_production_target_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('target_id')->constrained('jmv_production_targets')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jmv_production_target_audits');
        Schema::dropIfExists('jmv_production_targets');
    }
};

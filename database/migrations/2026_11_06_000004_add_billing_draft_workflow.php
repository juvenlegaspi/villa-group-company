<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haruhay_billings', function (Blueprint $table) {
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('utilities_ready')->default(false);
            $table->date('issued_on')->nullable()->change();
            $table->decimal('water', 12, 2)->nullable()->default(null)->change();
            $table->decimal('electric', 12, 2)->nullable()->default(null)->change();
        });
        // Preserve previously issued invoices and their payment state.
        DB::table('haruhay_billings')->update(['finalized_at' => DB::raw('created_at'), 'utilities_ready' => true]);
    }
    public function down(): void
    {
        Schema::table('haruhay_billings', function (Blueprint $table) {
            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['finalized_at', 'finalized_by', 'utilities_ready']);
        });
    }
};

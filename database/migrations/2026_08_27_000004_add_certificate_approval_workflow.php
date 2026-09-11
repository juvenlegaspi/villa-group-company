<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $table): void {
            $table->string('certificate_number')->nullable()->after('certificate_name');
            $table->string('certificate_type')->nullable()->after('certificate_number');
            $table->string('issuing_authority')->nullable()->after('certificate_type');
            $table->foreignId('submitted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_remarks')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['certificate_number', 'certificate_type', 'issuing_authority', 'submitted_at', 'approved_at', 'approval_remarks']);
        });
    }
};

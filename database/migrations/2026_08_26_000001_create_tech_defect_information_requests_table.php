<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tech_defect_information_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tech_defect_id')->constrained('tech_defects')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('request_text');
            $table->dateTime('requested_at');
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_text')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->timestamps();

            $table->index(['tech_defect_id', 'status'], 'tech_defect_info_status_index');
        });

        DB::table('tech_defects')->whereNotNull('information_request')->orderBy('id')->each(function ($report): void {
            DB::table('tech_defect_information_requests')->insert([
                'tech_defect_id' => $report->id,
                'requested_by' => $report->information_requested_by,
                'request_text' => $report->information_request,
                'requested_at' => $report->information_requested_at ?? $report->updated_at ?? now(),
                'responded_by' => $report->information_responded_by,
                'response_text' => $report->information_response,
                'responded_at' => $report->information_responded_at,
                'status' => filled($report->information_response) ? 'Responded' : 'Pending',
                'created_at' => $report->information_requested_at ?? $report->updated_at ?? now(),
                'updated_at' => $report->information_responded_at ?? $report->updated_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_defect_information_requests');
    }
};

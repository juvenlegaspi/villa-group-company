<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tech_defects', function (Blueprint $table): void {
            $table->foreignId('assigned_to_user_id')->nullable()->after('reported_by_user_id')->constrained('users')->nullOnDelete();
            $table->date('target_completion_date')->nullable()->after('assigned_to_user_id');
            $table->dateTime('downtime_started_at')->nullable()->after('target_completion_date');
            $table->dateTime('downtime_ended_at')->nullable()->after('downtime_started_at');
            $table->decimal('total_downtime_hours', 10, 2)->nullable()->after('downtime_ended_at');
            $table->text('root_cause')->nullable()->after('initial_cause');
            $table->text('corrective_action')->nullable()->after('root_cause');
            $table->text('preventive_action')->nullable()->after('corrective_action');
            $table->foreignId('repair_completed_by')->nullable()->after('remarks')->constrained('users')->nullOnDelete();
            $table->dateTime('repair_completed_at')->nullable()->after('repair_completed_by');
            $table->foreignId('verified_by')->nullable()->after('repair_completed_at')->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable()->after('verified_by');
            $table->text('verification_remarks')->nullable()->after('verified_at');
            $table->dateTime('overdue_notified_at')->nullable()->after('verification_remarks');
            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['target_completion_date', 'status']);
        });

        Schema::table('third_party_supports', function (Blueprint $table): void {
            $table->string('vendor_name')->nullable()->after('tech_defect_id');
            $table->string('contact_person')->nullable()->after('vendor_name');
            $table->string('contact_number', 50)->nullable()->after('contact_person');
            $table->date('expected_completion_date')->nullable()->after('tools_required');
            $table->decimal('quoted_cost', 12, 2)->nullable()->after('expected_completion_date');
            $table->decimal('actual_cost', 12, 2)->nullable()->after('quoted_cost');
            $table->dateTime('overdue_notified_at')->nullable()->after('completed_at');
            $table->index(['expected_completion_date', 'status']);
        });

        Schema::create('tech_defect_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tech_defect_id')->constrained('tech_defects')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40);
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->index(['tech_defect_id', 'category']);
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('tech_defect_attachments');
        Schema::table('third_party_supports', function (Blueprint $table): void {
            $table->dropIndex(['expected_completion_date', 'status']);
            $table->dropColumn(['vendor_name', 'contact_person', 'contact_number', 'expected_completion_date', 'quoted_cost', 'actual_cost', 'overdue_notified_at']);
        });
        Schema::table('tech_defects', function (Blueprint $table): void {
            $table->dropIndex(['assigned_to_user_id', 'status']);
            $table->dropIndex(['target_completion_date', 'status']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('repair_completed_by');
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropColumn(['target_completion_date', 'downtime_started_at', 'downtime_ended_at', 'total_downtime_hours', 'root_cause', 'corrective_action', 'preventive_action', 'repair_completed_at', 'verified_at', 'verification_remarks', 'overdue_notified_at']);
        });
    }
};

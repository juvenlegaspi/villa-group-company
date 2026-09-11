<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tech_defects', function (Blueprint $table): void {
            $table->foreignId('review_submitted_by')->nullable()->after('reported_by_user_id')->constrained('users')->nullOnDelete();
            $table->dateTime('review_submitted_at')->nullable()->after('review_submitted_by');
            $table->foreignId('reviewed_by')->nullable()->after('review_submitted_at')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_remarks')->nullable()->after('reviewed_at');
            $table->text('technical_assessment')->nullable()->after('initial_cause');
            $table->text('technical_findings')->nullable()->after('technical_assessment');
            $table->text('technical_recommendation')->nullable()->after('technical_findings');
            $table->text('information_request')->nullable()->after('technical_recommendation');
            $table->text('information_response')->nullable()->after('information_request');
            $table->foreignId('information_requested_by')->nullable()->after('information_response')->constrained('users')->nullOnDelete();
            $table->dateTime('information_requested_at')->nullable()->after('information_requested_by');
            $table->foreignId('information_responded_by')->nullable()->after('information_requested_at')->constrained('users')->nullOnDelete();
            $table->dateTime('information_responded_at')->nullable()->after('information_responded_by');
            $table->foreignId('assessed_by')->nullable()->after('information_responded_at')->constrained('users')->nullOnDelete();
            $table->dateTime('assessed_at')->nullable()->after('assessed_by');
            $table->foreignId('action_assigned_by')->nullable()->after('target_completion_date')->constrained('users')->nullOnDelete();
            $table->dateTime('action_assigned_at')->nullable()->after('action_assigned_by');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('action_assigned_at');
            $table->text('progress_notes')->nullable()->after('progress_percent');
            $table->foreignId('progress_updated_by')->nullable()->after('progress_notes')->constrained('users')->nullOnDelete();
            $table->dateTime('progress_updated_at')->nullable()->after('progress_updated_by');
            $table->foreignId('closed_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable()->after('closed_by');
        });

        DB::table('tech_defects')->where('status', 'Open')->update(['status' => 'New Report']);
        DB::table('tech_defects')->where('status', 'Waiting 3rd Party')->update(['status' => 'Ongoing']);
        DB::table('tech_defects')->where('status', 'Completed')->update(['status' => 'Closed']);

        $permissions = [
            ['tech_defects.submit_review', 'Submit technical defect reports for management review'],
            ['tech_defects.review', 'Review vessel technical defect reports'],
            ['tech_defects.assess', 'Assess technical defects and record findings'],
            ['tech_defects.assign_action', 'Assign corrective work to technical personnel'],
            ['tech_defects.perform_action', 'Perform and update assigned corrective work'],
        ];
        foreach ($permissions as [$slug, $name]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'module' => 'tech_defects', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $roleIds = DB::table('access_roles')->pluck('id', 'slug');
        $grants = [
            'system-administrator' => $permissionIds->keys()->filter(fn ($slug) => str_starts_with($slug, 'tech_defects.'))->all(),
            'company-administrator' => $permissionIds->keys()->filter(fn ($slug) => str_starts_with($slug, 'tech_defects.'))->all(),
            'company-approver' => ['tech_defects.view_company', 'tech_defects.review', 'tech_defects.assign_action', 'tech_defects.verify'],
            'standard-user' => ['tech_defects.view_assigned', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.submit'],
        ];
        foreach ($grants as $roleSlug => $slugs) {
            if (! isset($roleIds[$roleSlug])) continue;
            foreach ($slugs as $slug) {
                if (! isset($permissionIds[$slug])) continue;
                DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleIds[$roleSlug], 'permission_id' => $permissionIds[$slug]]);
            }
        }

        $positionGrants = [
            'vessel-captain' => ['tech_defects.create', 'tech_defects.submit_review'],
            'technical-manager' => ['tech_defects.assess', 'tech_defects.assign_action', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify'],
            'chief-engineer' => ['tech_defects.assess', 'tech_defects.assign_action', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify'],
            'second-engineer' => ['tech_defects.assess', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit'],
            'maintenance-technician' => ['tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit'],
        ];
        foreach ($positionGrants as $code => $slugs) {
            foreach (DB::table('positions')->where('code', $code)->pluck('id') as $positionId) {
                foreach ($slugs as $slug) {
                    if (! isset($permissionIds[$slug])) continue;
                    DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionIds[$slug]]);
                }
            }
        }

        $captainPositionIds = DB::table('positions')->where('legacy_role', 'captain')->pluck('id');
        foreach ($captainPositionIds as $positionId) {
            foreach (['tech_defects.create', 'tech_defects.submit_review'] as $slug) {
                DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionIds[$slug]]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tech_defects')->where('status', 'New Report')->update(['status' => 'Open']);
        DB::table('tech_defects')->whereIn('status', ['For Review', 'For Assessment', 'For Action'])->update(['status' => 'Open']);
        DB::table('tech_defects')->where('status', 'Closed')->update(['status' => 'Completed']);

        $slugs = ['tech_defects.submit_review', 'tech_defects.review', 'tech_defects.assess', 'tech_defects.assign_action', 'tech_defects.perform_action'];
        DB::table('permissions')->whereIn('slug', $slugs)->delete();

        Schema::table('tech_defects', function (Blueprint $table): void {
            foreach (['closed_by', 'progress_updated_by', 'action_assigned_by', 'assessed_by', 'information_responded_by', 'information_requested_by', 'reviewed_by', 'review_submitted_by'] as $foreign) {
                $table->dropConstrainedForeignId($foreign);
            }
            $table->dropColumn([
                'review_submitted_at', 'reviewed_at', 'review_remarks', 'technical_assessment', 'technical_findings',
                'technical_recommendation', 'information_request', 'information_response', 'information_requested_at',
                'information_responded_at', 'assessed_at', 'action_assigned_at', 'progress_percent', 'progress_notes',
                'progress_updated_at', 'closed_at',
            ]);
        });
    }
};

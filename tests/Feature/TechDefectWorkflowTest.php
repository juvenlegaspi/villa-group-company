<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\ApprovalAuthority;
use App\Models\Permission;
use App\Models\Position;
use App\Models\TechDefect;
use App\Models\ThirdPartySupport;
use App\Models\User;
use App\Models\Vessel;
use App\Models\UserVesselAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechDefectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $division;
    private int $department;
    private User $manager;
    private User $captain;
    private User $technician;
    private User $reporter;
    private Vessel $vessel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->division = DB::table('divisions')->insertGetId(['name' => 'Villa shipping Lines', 'created_at' => now(), 'updated_at' => now()]);
        $this->department = DB::table('departments')->insertGetId(['name' => 'Technical Department', 'division_id' => $this->division, 'created_at' => now(), 'updated_at' => now()]);

        $captainPosition = $this->position('Vessel Captain', 'captain', ['tech_defects.create', 'tech_defects.submit_review']);
        $technicalPosition = $this->position('Chief Engineer', 'staff', ['tech_defects.assess', 'tech_defects.assign_action', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit']);
        $staffPosition = $this->position('Vessel Crew', 'staff', []);
        $managerPosition = $this->position('Vessel Manager', 'manager', []);

        $this->manager = $this->user('manager', $managerPosition, 'company-approver');
        ApprovalAuthority::create(['user_id' => $this->manager->id, 'division_id' => $this->division, 'department_id' => $this->department, 'module' => 'technical_defects', 'action' => 'approve', 'is_active' => true]);
        $this->captain = $this->user('captain', $captainPosition, 'standard-user');
        $this->technician = $this->user('staff', $technicalPosition, 'standard-user');
        $this->reporter = $this->user('staff', $staffPosition, 'standard-user', 'Workflow', 'Tester');
        $this->vessel = Vessel::create(['vessel_name' => 'MV Workflow', 'captain_id' => $this->captain->id]);
        UserVesselAssignment::create(['user_id' => $this->technician->id, 'vessel_id' => $this->vessel->id, 'assigned_by' => $this->manager->id, 'is_active' => true, 'effective_from' => today()]);
    }

    public function test_new_report_is_validated_created_and_audited(): void
    {
        Storage::fake('local');
        $response = $this->actingAs($this->captain)->post(route('tech-defects.store'), $this->payload([
            'checklist_document' => UploadedFile::fake()->create('engine-checklist.pdf', 250, 'application/pdf'),
            'defect_photo' => UploadedFile::fake()->image('affected-engine.jpg'),
        ]));
        $report = TechDefect::firstOrFail();

        $response->assertRedirect(route('tech-defects.show', $report));
        $this->assertSame('New Report', $report->status);
        $this->assertNull($report->assigned_to_user_id);
        $this->assertDatabaseHas('tech_defect_audits', ['tech_defect_id' => $report->id, 'action' => 'created', 'to_status' => 'New Report']);
        $attachment = $report->attachments()->where('category', 'Checklist')->firstOrFail();
        $this->assertSame('engine-checklist.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
        $defectPhoto = $report->attachments()->where('category', 'Before Repair')->firstOrFail();
        $this->assertSame('affected-engine.jpg', $defectPhoto->original_name);
        Storage::disk('local')->assertExists($defectPhoto->path);
        $this->assertDatabaseCount('tech_defect_attachments', 2);
        $this->assertDatabaseCount('tech_defect_audits', 3);
        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), ['action' => 'submit_review'])->assertRedirect();
        $this->assertSame('For Review', $report->fresh()->status);
        $pdfResponse = $this->actingAs($this->captain)->get(route('tech-defects.pdf', $report));
        $pdfResponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
        $this->actingAs($this->captain)->post(route('tech-defects.store'), $this->payload(['severity_level' => 'Extreme', 'date_identified' => today()->addDay()->toDateString(), 'checklist_document' => UploadedFile::fake()->create('invalid-checklist.pdf', 100, 'application/pdf'), 'defect_photo' => UploadedFile::fake()->image('invalid-report-photo.jpg')]))
            ->assertSessionHasErrors(['severity_level', 'date_identified']);
    }

    public function test_new_report_requires_valid_checklist_and_defect_photo(): void
    {
        Storage::fake('local');
        $this->actingAs($this->captain)->post(route('tech-defects.store'), $this->payload())
            ->assertSessionHasErrors(['checklist_document', 'defect_photo']);
        $this->assertDatabaseCount('tech_defects', 0);

        $this->actingAs($this->captain)->post(route('tech-defects.store'), $this->payload([
            'checklist_document' => UploadedFile::fake()->create('unsafe.exe', 20, 'application/octet-stream'),
            'defect_photo' => UploadedFile::fake()->image('valid-defect.jpg'),
        ]))->assertSessionHasErrors('checklist_document');
        $this->assertDatabaseCount('tech_defects', 0);

        $this->actingAs($this->captain)->post(route('tech-defects.store'), $this->payload([
            'checklist_document' => UploadedFile::fake()->create('valid-checklist.pdf', 100, 'application/pdf'),
            'defect_photo' => UploadedFile::fake()->create('not-an-image.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('defect_photo');
        $this->assertDatabaseCount('tech_defects', 0);
    }

    public function test_reporter_dropdown_is_limited_to_active_marine_and_technical_personnel(): void
    {
        Storage::fake('local');
        $otherDepartment = DB::table('departments')->insertGetId([
            'name' => 'Finance and Accounting', 'division_id' => $this->division,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $financeUser = $this->user('staff', $this->reporter->position, 'standard-user', 'Finance', 'Reporter');
        $financeUser->update(['department_id' => $otherDepartment]);
        $inactiveTechnicalUser = $this->user('staff', $this->reporter->position, 'standard-user', 'Inactive', 'Engineer');
        $inactiveTechnicalUser->update(['status' => false]);

        $this->actingAs($this->captain)->get(route('tech-defects.create'))
            ->assertOk()
            ->assertSee('Workflow Tester')
            ->assertDontSee('Finance Reporter')
            ->assertDontSee('Inactive Engineer');

        $this->actingAs($this->captain)->post(route('tech-defects.store'), $this->payload([
            'reported_by_user_id' => $financeUser->id,
            'checklist_document' => UploadedFile::fake()->create('checklist.pdf', 100, 'application/pdf'),
            'defect_photo' => UploadedFile::fake()->image('defect.jpg'),
        ]))->assertSessionHasErrors('reported_by_user_id');
        $this->assertDatabaseCount('tech_defects', 0);
    }

    public function test_complete_seven_stage_workflow_requires_assessment_progress_evidence_and_independent_verification(): void
    {
        Storage::fake('local');
        $report = $this->report();

        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), ['action' => 'submit_review'])
            ->assertRedirect(route('tech-defects.show', $report))->assertSessionHasErrors('before_repair_attachment');
        $this->actingAs($this->captain)->post(route('tech-defects.attachments.store', $report), [
            'category' => 'Before Repair',
            'attachment' => UploadedFile::fake()->image('affected-defect.jpg'),
        ])->assertRedirect();
        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), ['action' => 'submit_review'])->assertRedirect();
        $this->assertSame('For Review', $report->fresh()->status);

        $this->actingAs($this->manager)->put(route('tech-defects.update', $report), ['action' => 'review_confirm', 'review_remarks' => 'Validated'])->assertRedirect();
        $this->assertSame('For Assessment', $report->fresh()->status);

        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), $this->assessment())->assertRedirect();
        $this->assertSame('For Action', $report->fresh()->status);
        $this->assertSame($this->technician->id, $report->fresh()->assigned_to_user_id);

        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'start_action'])->assertRedirect();
        $this->assertSame('Ongoing', $report->fresh()->status);
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'submit_completion'])
            ->assertRedirect(route('tech-defects.show', $report))->assertSessionHasErrors('verification');
        $this->actingAs($this->technician)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertSee('Complete corrective work first')
            ->assertDontSee('Complete repair details')
            ->assertSee('Assign third party');
        $this->actingAs($this->technician)->get(route('tech-defects.repair-closeout', $report))
            ->assertRedirect(route('tech-defects.show', $report))
            ->assertSessionHasErrors('closeout');
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'update_progress', 'progress_percent' => 100, 'progress_notes' => 'Repair completed'])->assertRedirect();
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'update_progress', 'progress_percent' => 100, 'progress_notes' => 'Attempted duplicate completion'])->assertStatus(422);
        $this->assertSame('Repair completed', $report->fresh()->progress_notes);
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'update_closeout', 'root_cause' => 'Worn bearing', 'corrective_action' => 'Bearing replaced', 'preventive_action' => 'Monthly vibration checks'])->assertRedirect();

        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'submit_completion'])
            ->assertRedirect(route('tech-defects.show', $report))->assertSessionHasErrors('attachment');
        $this->actingAs($this->technician)->post(route('tech-defects.attachments.store', $report), ['category' => 'After Repair', 'attachment' => UploadedFile::fake()->image('after.jpg')])->assertRedirect();
        $this->actingAs($this->technician)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertSee('value="submit_completion"', false)
            ->assertDontSee('value="add_support"', false);
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), [
            'action' => 'add_support', 'reason_for_support' => 'Late duplicate support', 'vendor_name' => 'Late Vendor',
            'expected_completion_date' => today()->addDay()->toDateString(), 'spares_required' => 'No', 'tools_required' => 'Test kit',
        ])->assertRedirect(route('tech-defects.show', $report))->assertSessionHasErrors('support');
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'submit_completion'])->assertRedirect();
        $this->assertSame('For Verification', $report->fresh()->status);

        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'verify_resolution', 'verification_remarks' => 'Checked'])->assertForbidden();
        $this->actingAs($this->manager)->put(route('tech-defects.update', $report), ['action' => 'verify_resolution', 'verification_remarks' => 'Evidence and operation checked'])->assertRedirect();
        $this->assertSame('For Verification', $report->fresh()->status);
        $this->assertNotNull($report->fresh()->verified_at);
        $this->actingAs($this->manager)->put(route('tech-defects.update', $report), ['action' => 'confirm_completion'])->assertRedirect();
        $this->assertSame('Closed', $report->fresh()->status);
        $this->assertDatabaseHas('tech_defect_audits', ['tech_defect_id' => $report->id, 'action' => 'report_closed']);
    }

    public function test_stage_uploads_are_restricted_to_the_correct_status_and_file_type(): void
    {
        Storage::fake('local');
        $report = $this->report();

        $this->actingAs($this->captain)->post(route('tech-defects.attachments.store', $report), [
            'category' => 'Before Repair',
            'attachment' => UploadedFile::fake()->create('defect.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('attachment');

        $this->actingAs($this->captain)->post(route('tech-defects.attachments.store', $report), [
            'category' => 'Before Repair',
            'attachment' => UploadedFile::fake()->image('defect.png'),
        ])->assertRedirect();
        $this->assertDatabaseHas('tech_defect_attachments', [
            'tech_defect_id' => $report->id,
            'category' => 'Before Repair',
            'original_name' => 'defect.png',
        ]);

        $this->actingAs($this->captain)->post(route('tech-defects.attachments.store', $report), [
            'category' => 'After Repair',
            'attachment' => UploadedFile::fake()->image('too-early.jpg'),
        ])->assertStatus(422);

        $this->actingAs($this->captain)->post(route('tech-defects.attachments.store', $report), [
            'category' => 'Checklist',
            'attachment' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_manager_can_return_report_and_technical_team_can_request_information(): void
    {
        $report = $this->report(['status' => 'For Review']);
        $this->actingAs($this->manager)->put(route('tech-defects.update', $report), ['action' => 'return_report', 'review_remarks' => 'Clarify alarm reading'])->assertRedirect();
        $this->assertSame('New Report', $report->fresh()->status);

        $report->update(['status' => 'For Assessment']);
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'request_information', 'information_request' => 'Provide latest engine readings'])->assertRedirect();
        $firstRequest = $report->informationRequests()->firstOrFail();
        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), ['action' => 'respond_information', 'information_request_id' => $firstRequest->id, 'information_response' => 'Readings attached and stable'])->assertRedirect();
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'request_information', 'information_request' => 'Confirm the alarm timestamp'])->assertRedirect();
        $this->assertDatabaseCount('tech_defect_information_requests', 2);
        $this->assertDatabaseHas('tech_defect_information_requests', ['id' => $firstRequest->id, 'request_text' => 'Provide latest engine readings', 'response_text' => 'Readings attached and stable', 'status' => 'Responded']);
        $this->assertDatabaseHas('tech_defect_information_requests', ['tech_defect_id' => $report->id, 'request_text' => 'Confirm the alarm timestamp', 'status' => 'Pending']);
        $this->assertDatabaseHas('tech_defect_audits', ['tech_defect_id' => $report->id, 'action' => 'information_responded']);
    }

    public function test_captain_cannot_review_even_with_approver_access_and_authority(): void
    {
        $report = $this->report(['status' => 'For Review']);
        $this->captain->update(['access_role_id' => AccessRole::where('slug', 'company-approver')->value('id')]);
        $this->captain->unsetRelation('accessRole');
        ApprovalAuthority::create([
            'user_id' => $this->captain->id,
            'division_id' => $this->division,
            'department_id' => $this->department,
            'module' => 'technical_defects',
            'action' => 'approve',
            'is_active' => true,
        ]);

        $this->actingAs($this->captain)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertDontSee('Review &amp; confirm', false)
            ->assertDontSee('Return for correction');

        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), [
            'action' => 'review_confirm',
            'review_remarks' => 'Captain must not approve this report.',
        ])->assertForbidden();
        $this->assertSame('For Review', $report->fresh()->status);

        $report->update([
            'status' => 'For Verification',
            'assigned_to_user_id' => $this->technician->id,
            'repair_completed_by' => $this->technician->id,
            'repair_completed_at' => now(),
            'progress_percent' => 100,
        ]);
        $this->actingAs($this->captain)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertDontSee('Verify resolution')
            ->assertDontSee('Reopen issue');
        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), [
            'action' => 'reopen_issue',
            'verification_remarks' => 'Captain must not reopen verified work.',
        ])->assertForbidden();
    }

    public function test_third_party_support_remains_ongoing_and_records_both_actors(): void
    {
        $report = $this->report(['status' => 'Ongoing', 'assigned_to_user_id' => $this->technician->id]);
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), [
            'action' => 'add_support', 'reason_for_support' => 'Specialized alignment', 'vendor_name' => 'Marine Works',
            'expected_completion_date' => today()->addDay()->toDateString(), 'quoted_cost' => 5000, 'spares_required' => 'No', 'tools_required' => 'Laser kit',
        ])->assertRedirect();
        $support = ThirdPartySupport::firstOrFail();
        $this->assertSame('Ongoing', $report->fresh()->status);
        $this->assertSame($this->technician->id, $support->created_by);

        $this->actingAs($this->technician)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertSee('Third-party work in progress')
            ->assertDontSee('Complete repair details')
            ->assertDontSee('Assign third party');
        $this->actingAs($this->technician)->get(route('tech-defects.repair-closeout', $report))
            ->assertRedirect(route('tech-defects.show', $report))
            ->assertSessionHasErrors('closeout');
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), [
            'action' => 'add_support', 'reason_for_support' => 'Duplicate support', 'vendor_name' => 'Second Vendor',
            'expected_completion_date' => today()->addDay()->toDateString(), 'spares_required' => 'No', 'tools_required' => 'Test kit',
        ])->assertRedirect(route('tech-defects.show', $report))->assertSessionHasErrors('support');

        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), ['action' => 'done_'.$support->id, 'actual_cost' => 4800])->assertRedirect();
        $this->assertSame('Done', $support->fresh()->status);
        $this->assertSame($this->technician->id, $support->fresh()->completed_by);
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), [
            'action' => 'update_progress',
            'progress_percent' => 100,
            'progress_notes' => 'Third-party work completed',
        ])->assertRedirect();
        $this->actingAs($this->technician)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertSee('Complete repair details')
            ->assertDontSee('Assign third party');
    }

    public function test_repair_closeout_prefills_report_start_and_current_end_time_but_remains_editable(): void
    {
        $now = now()->startOfMinute();
        $this->travelTo($now);
        $report = $this->report([
            'status' => 'Ongoing',
            'assigned_to_user_id' => $this->technician->id,
            'progress_percent' => 100,
            'downtime_started_at' => null,
            'downtime_ended_at' => null,
            'created_at' => $now->copy()->subHours(6),
            'updated_at' => $now->copy()->subHours(6),
        ]);

        $this->actingAs($this->technician)->get(route('tech-defects.repair-closeout', $report))
            ->assertOk()
            ->assertSee('value="'.$report->created_at->format('Y-m-d\\TH:i').'"', false)
            ->assertSee('value="'.$now->format('Y-m-d\\TH:i').'"', false)
            ->assertSee('Adjust it if the actual downtime started at a different time.')
            ->assertSee('Adjust it to the actual restoration time when necessary.');

        $manualStart = $now->copy()->subHours(5);
        $manualEnd = $now->copy()->subHour();
        $this->actingAs($this->technician)->put(route('tech-defects.update', $report), [
            'action' => 'update_closeout',
            'downtime_started_at' => $manualStart->format('Y-m-d H:i:s'),
            'downtime_ended_at' => $manualEnd->format('Y-m-d H:i:s'),
            'root_cause' => 'Bearing wear',
            'corrective_action' => 'Bearing replaced',
            'preventive_action' => 'Scheduled inspection',
        ])->assertRedirect(route('tech-defects.show', $report));

        $report->refresh();
        $this->assertTrue($report->downtime_started_at->equalTo($manualStart));
        $this->assertTrue($report->downtime_ended_at->equalTo($manualEnd));
        $this->assertSame('4.00', $report->total_downtime_hours);
        $this->travelBack();
    }

    public function test_third_party_support_is_limited_to_technical_department_and_managers(): void
    {
        $report = $this->report(['status' => 'Ongoing', 'assigned_to_user_id' => $this->technician->id]);
        $permission = Permission::where('slug', 'tech_defects.request_support')->firstOrFail();
        $this->captain->position->permissions()->syncWithoutDetaching([$permission->id]);
        $this->captain->unsetRelation('position');

        $payload = [
            'action' => 'add_support',
            'reason_for_support' => 'Specialized shaft inspection',
            'vendor_name' => 'External Marine Services',
            'expected_completion_date' => today()->addDay()->toDateString(),
            'spares_required' => 'No',
            'tools_required' => 'Alignment scanner',
        ];

        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), $payload)->assertForbidden();
        $this->actingAs($this->captain)->get(route('tech-defects.show', $report))->assertOk()->assertDontSee('Assign third party');
        $this->actingAs($this->manager)->put(route('tech-defects.update', $report), $payload)->assertRedirect();
        $this->assertDatabaseHas('third_party_supports', ['tech_defect_id' => $report->id, 'vendor_name' => 'External Marine Services']);
    }

    public function test_captain_cannot_access_ongoing_repair_closeout_or_evidence_actions(): void
    {
        Storage::fake('local');
        $report = $this->report([
            'status' => 'Ongoing',
            'assigned_to_user_id' => $this->technician->id,
            'progress_percent' => 100,
        ]);

        $this->actingAs($this->captain)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertDontSee('Complete repair details')
            ->assertDontSee('Review repair details')
            ->assertDontSee('Upload repair photo')
            ->assertDontSee('Add evidence');

        $this->actingAs($this->captain)->get(route('tech-defects.repair-closeout', $report))->assertForbidden();
        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), [
            'action' => 'update_closeout',
            'root_cause' => 'Unauthorized change',
            'corrective_action' => 'Unauthorized change',
        ])->assertForbidden();
        $this->actingAs($this->captain)->post(route('tech-defects.attachments.store', $report), [
            'category' => 'After Repair',
            'attachment' => UploadedFile::fake()->image('after.jpg'),
        ])->assertForbidden();
        $this->actingAs($this->captain)->put(route('tech-defects.update', $report), [
            'action' => 'submit_completion',
        ])->assertForbidden();
    }

    public function test_reopened_issue_resets_verification_and_reopens_downtime_tracking(): void
    {
        $report = $this->report(['status' => 'For Verification', 'assigned_to_user_id' => $this->technician->id, 'repair_completed_by' => $this->technician->id, 'repair_completed_at' => now(), 'progress_percent' => 100, 'downtime_started_at' => now()->subHours(3), 'downtime_ended_at' => now(), 'total_downtime_hours' => 3]);
        $this->actingAs($this->manager)->put(route('tech-defects.update', $report), ['action' => 'reopen_issue', 'verification_remarks' => 'Vibration remains above limit'])->assertRedirect();
        $report->refresh();
        $this->assertSame('Ongoing', $report->status);
        $this->assertSame(90, $report->progress_percent);
        $this->assertNull($report->downtime_ended_at);
        $this->assertNull($report->verified_at);
        $this->actingAs($this->manager)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertSee('Awaiting progress update from the assigned Technical PIC')
            ->assertDontSee('value="update_progress"', false);
        $this->actingAs($this->technician)->get(route('tech-defects.show', $report))
            ->assertOk()
            ->assertSee('value="update_progress"', false);
    }

    public function test_only_archive_permission_can_archive_and_restore(): void
    {
        $report = $this->report();
        $this->actingAs($this->captain)->delete(route('tech-defects.destroy', $report))->assertForbidden();
        $archive = Permission::where('slug', 'tech_defects.archive')->firstOrFail();
        $this->manager->accessRole->permissions()->syncWithoutDetaching([$archive->id]);
        $this->manager->unsetRelation('accessRole');
        $this->actingAs($this->manager)->delete(route('tech-defects.destroy', $report))->assertRedirect();
        $this->assertSoftDeleted('tech_defects', ['id' => $report->id]);
        $this->actingAs($this->manager)->post(route('tech-defects.restore', $report->id))->assertRedirect();
        $this->assertNotSoftDeleted('tech_defects', ['id' => $report->id]);
    }

    public function test_captain_is_limited_to_assigned_vessel(): void
    {
        $otherCaptain = $this->user('captain', $this->captain->position, 'standard-user');
        $otherVessel = Vessel::create(['vessel_name' => 'MV Other', 'captain_id' => $otherCaptain->id]);
        $otherReport = $this->report(['vessel_id' => $otherVessel->id]);
        $this->actingAs($this->captain)->get(route('tech-defects.show', $otherReport))->assertForbidden();
        $this->actingAs($this->captain)->post(route('tech-defects.store'), $this->payload(['vessel_id' => $otherVessel->id]))->assertForbidden();
    }

    public function test_dashboard_counts_new_and_closed_reports(): void
    {
        $this->report(['status' => 'New Report', 'severity_level' => 'Critical']);
        $this->report(['status' => 'Closed', 'severity_level' => 'Major', 'date_completed' => today()]);
        $this->actingAs($this->manager)->get(route('tech-defects.dashboard'))->assertOk()
            ->assertViewHas('totalReports', 2)->assertViewHas('open', 1)->assertViewHas('completed', 1)->assertViewHas('criticalDefects', 1);
    }

    private function assessment(): array
    {
        return ['action' => 'complete_assessment', 'technical_assessment' => 'Bearing inspection required', 'technical_findings' => 'Bearing wear confirmed', 'technical_recommendation' => 'Replace bearing and monitor vibration', 'assigned_to_user_id' => $this->technician->id, 'target_completion_date' => today()->addDay()->toDateString()];
    }

    private function report(array $overrides = []): TechDefect
    {
        return TechDefect::create(array_merge($this->payload(), ['reported_by' => 'WORKFLOW TESTER', 'status' => 'New Report'], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['vessel_id' => $this->vessel->id, 'date_identified' => today()->toDateString(), 'port_location' => 'Cebu', 'reported_by_user_id' => $this->reporter->id, 'system_affected' => 'Main Engine', 'defect_description' => 'Abnormal vibration detected', 'initial_cause' => 'For investigation', 'severity_level' => 'Major', 'operational_impact' => 'Limited', 'temporary_repair' => 'No', 'remarks' => 'Monitor readings'], $overrides);
    }

    private function position(string $name, string $legacyRole, array $permissions): Position
    {
        $position = Position::create(['division_id' => $this->division, 'department_id' => $this->department, 'name' => $name, 'code' => str($name)->slug(), 'legacy_role' => $legacyRole, 'is_active' => true]);
        $position->permissions()->sync(Permission::whereIn('slug', [...$permissions, 'shipping.technical_defects.access'])->pluck('id'));
        return $position;
    }

    private function user(string $legacyRole, Position $position, string $accessRole, ?string $name = null, ?string $lastname = null): User
    {
        static $number = 0; $number++;
        return User::create(['name' => $name ?? ucfirst($legacyRole), 'lastname' => $lastname ?? 'User '.$number, 'username' => 'tech-user-'.$number, 'email' => 'tech-user-'.$number.'@example.test', 'cell_number' => '09170000000', 'password' => Hash::make('password-for-tests'), 'role' => $legacyRole, 'is_admin' => false, 'department_id' => $this->department, 'division_id' => $this->division, 'position_id' => $position->id, 'access_role_id' => AccessRole::where('slug', $accessRole)->value('id'), 'must_change_password' => false, 'status' => true]);
    }
}

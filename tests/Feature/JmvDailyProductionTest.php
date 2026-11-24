<?php

namespace Tests\Feature;

use App\Models\JmvDailyProductionLog;
use App\Models\User;
use App\Notifications\JmvDailyProductionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JmvDailyProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Notification::fake();
    }

    public function test_operations_manager_can_create_filter_update_download_and_export_daily_logs(): void
    {
        Storage::fake('local');
        $manager = $this->positionUser('mine-operations-manager');
        $payload = $this->payload([
            'location' => 'Mine 1',
            'attachments' => [UploadedFile::fake()->create('daily-report.pdf', 100, 'application/pdf')],
        ]);

        $this->actingAs($manager)->post(route('jmv.operations.production.store'), $payload)->assertRedirect();
        $log = JmvDailyProductionLog::firstOrFail();
        $this->assertSame('Mine 1', $log->location);
        $this->assertDatabaseHas('jmv_daily_production_audits', ['jmv_daily_production_log_id' => $log->id, 'action' => 'log_created']);
        $attachment = $log->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($manager)->get(route('jmv.operations.production.index', ['location' => 'Mine 1']))
            ->assertOk()->assertSee($log->log_no)->assertSee('Mine 1');
        $this->actingAs($manager)->get(route('jmv.operations.production.index', ['location' => 'Mine 2']))
            ->assertOk()->assertDontSee($log->log_no);
        $this->actingAs($manager)->get(route('jmv.operations.production.show', $log))->assertOk()->assertSee('daily-report.pdf');
        $this->actingAs($manager)->get(route('jmv.operations.production.attachments.show', [$log, $attachment]))->assertOk();

        $this->actingAs($manager)->put(route('jmv.operations.production.update', $log), $this->payload(['coal_output' => 20]))
            ->assertRedirect(route('jmv.operations.production.show', $log));
        $this->assertSame(20, $log->fresh()->coal_output);
        $this->assertDatabaseHas('jmv_daily_production_audits', ['jmv_daily_production_log_id' => $log->id, 'action' => 'log_updated']);

        $export = $this->actingAs($manager)->get(route('jmv.operations.production.export', ['location' => 'Mine 1']));
        $export->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx"', $export->headers->get('content-disposition'));
        $this->assertStringStartsWith("PK\x03\x04", $export->getContent());
    }

    public function test_log_accepts_zero_output_and_is_unique_by_date_location_and_shift(): void
    {
        $manager = $this->positionUser('mine-supervisor');
        $this->actingAs($manager)->from(route('jmv.operations.production.index'))
            ->post(route('jmv.operations.production.store'), $this->payload(['soil_output' => 0, 'coal_output' => 0]))
            ->assertSessionHasNoErrors();

        $log = JmvDailyProductionLog::firstOrFail();
        $this->assertSame(0, $log->soil_output);
        $this->assertSame(0, $log->coal_output);
        $this->put(route('jmv.operations.production.update', $log), $this->payload(['soil_output' => 0, 'coal_output' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(0, $log->fresh()->soil_output);
        $this->assertSame(0, $log->fresh()->coal_output);
        $this->post(route('jmv.operations.production.store'), $this->payload(['soil_output' => -1, 'coal_output' => 0]))->assertSessionHasErrors('soil_output');
        $this->actingAs($manager)->from(route('jmv.operations.production.index'))
            ->post(route('jmv.operations.production.store'), $this->payload())
            ->assertSessionHasErrors('log_date');
        $this->assertDatabaseCount('jmv_daily_production_logs', 1);
    }

    public function test_daily_production_access_follows_jmv_operational_positions(): void
    {
        Storage::fake('local');
        $adminStaff = $this->positionUser('admin-staff');
        $miningStaff = $this->positionUser('mining-staff');
        $supervisor = $this->positionUser('mine-supervisor');
        $geologist = $this->positionUser('geologist');
        $chief = $this->positionUser('chief-geologist');

        $this->actingAs($adminStaff)->post(route('jmv.operations.production.store'), $this->payload([
            'attachments' => [UploadedFile::fake()->create('daily-report.pdf', 100, 'application/pdf')],
        ]))->assertSessionHasNoErrors();
        $log = JmvDailyProductionLog::firstOrFail();

        $this->assertTrue($supervisor->hasPermission('jmv.operations.daily_production.approve'));
        $this->assertFalse($adminStaff->hasPermission('jmv.operations.daily_production.approve'));
        $this->assertSame(JmvDailyProductionLog::STATUS_DRAFT, $log->status);
        $this->actingAs($adminStaff)->get(route('jmv.operations.production.edit', $log))->assertOk();
        $this->actingAs($adminStaff)->get(route('jmv.operations.production.export'))->assertForbidden();
        $this->actingAs($miningStaff)->post(route('jmv.operations.production.store'), $this->payload(['location' => 'Mine 2']))->assertForbidden();
        $this->actingAs($supervisor)->get(route('jmv.operations.production.show', $log))->assertOk();
        $this->actingAs($supervisor)->get(route('jmv.operations.production.edit', $log))->assertStatus(422);

        $this->actingAs($chief)->get(route('jmv.operations.production.index'))->assertOk()->assertDontSee($log->log_no);
        $this->actingAs($chief)->get(route('jmv.operations.production.show', $log))->assertNotFound();
        $this->actingAs($chief)->get(route('jmv.operations.production.export'))->assertOk();
        $this->actingAs($chief)->post(route('jmv.operations.production.store'), $this->payload(['location' => 'Mine 2']))->assertForbidden();
        $this->actingAs($geologist)->get(route('jmv.operations.production.index'))->assertOk();
        $this->actingAs($geologist)->post(route('jmv.operations.production.store'), $this->payload(['location' => 'Mine 3']))->assertForbidden();
    }

    public function test_admin_staff_can_submit_and_supervisor_can_approve_an_official_log(): void
    {
        Storage::fake('local');
        $adminStaff = $this->positionUser('admin-staff');
        $supervisor = $this->positionUser('mine-supervisor');
        $manager = $this->positionUser('mine-operations-manager');
        $chief = $this->positionUser('chief-geologist');

        $this->actingAs($adminStaff)->post(route('jmv.operations.production.store'), $this->payload([
            'attachments' => [UploadedFile::fake()->create('accomplishment.pdf', 100, 'application/pdf')],
        ]))->assertSessionHasNoErrors();
        $log = JmvDailyProductionLog::firstOrFail();

        $this->actingAs($adminStaff)->post(route('jmv.operations.production.submit', $log))->assertSessionHasNoErrors();
        $this->assertSame(JmvDailyProductionLog::STATUS_PENDING_REVIEW, $log->fresh()->status);
        $this->assertNotNull($log->fresh()->submitted_at);
        Notification::assertSentTo([$supervisor, $manager], JmvDailyProductionNotification::class);
        $this->actingAs($adminStaff)->get(route('jmv.operations.production.edit', $log))->assertStatus(422);

        $this->actingAs($supervisor)->post(route('jmv.operations.production.approve', $log), ['review_remarks' => 'Verified against the report.'])->assertSessionHasNoErrors();
        $log->refresh();
        $this->assertSame(JmvDailyProductionLog::STATUS_COMPLETED, $log->status);
        $this->assertSame($supervisor->id, $log->reviewed_by);
        $this->assertNotNull($log->completed_at);
        $this->assertDatabaseHas('jmv_daily_production_audits', ['jmv_daily_production_log_id' => $log->id, 'action' => 'log_approved']);
        Notification::assertSentTo($adminStaff, JmvDailyProductionNotification::class);
        $this->actingAs($chief)->get(route('jmv.operations.production.index'))->assertOk()->assertSee($log->log_no);
    }

    public function test_return_correction_resubmit_and_self_approval_controls_work(): void
    {
        Storage::fake('local');
        $adminStaff = $this->positionUser('admin-staff');
        $supervisor = $this->positionUser('mine-supervisor');
        $manager = $this->positionUser('mine-operations-manager');

        $this->actingAs($adminStaff)->post(route('jmv.operations.production.store'), $this->payload([
            'location' => 'Mine 2',
            'attachments' => [UploadedFile::fake()->create('mine-2-report.pdf', 100, 'application/pdf')],
        ]));
        $log = JmvDailyProductionLog::where('location', 'Mine 2')->firstOrFail();
        $this->actingAs($adminStaff)->post(route('jmv.operations.production.submit', $log))->assertSessionHasNoErrors();
        $this->actingAs($supervisor)->post(route('jmv.operations.production.return', $log), ['review_remarks' => 'Correct the coal output.'])->assertSessionHasNoErrors();

        $log->refresh();
        $this->assertSame(JmvDailyProductionLog::STATUS_FOR_CORRECTION, $log->status);
        $this->assertSame('Correct the coal output.', $log->review_remarks);
        $this->assertSame(1, $log->revision_count);
        $this->actingAs($adminStaff)->put(route('jmv.operations.production.update', $log), $this->payload(['location' => 'Mine 2', 'coal_output' => 25]))->assertSessionHasNoErrors();
        $this->actingAs($adminStaff)->post(route('jmv.operations.production.submit', $log))->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('jmv.operations.production.approve', $log))->assertSessionHasNoErrors();
        $this->assertSame(JmvDailyProductionLog::STATUS_COMPLETED, $log->fresh()->status);

        $this->actingAs($supervisor)->post(route('jmv.operations.production.store'), $this->payload([
            'location' => 'Mine 3',
            'attachments' => [UploadedFile::fake()->create('mine-3-report.pdf', 100, 'application/pdf')],
        ]));
        $ownLog = JmvDailyProductionLog::where('location', 'Mine 3')->firstOrFail();
        $this->actingAs($supervisor)->post(route('jmv.operations.production.submit', $ownLog))->assertSessionHasNoErrors();
        $this->actingAs($supervisor)->post(route('jmv.operations.production.approve', $ownLog))->assertStatus(422);
        $this->actingAs($manager)->post(route('jmv.operations.production.approve', $ownLog))->assertSessionHasNoErrors();
        $this->assertSame(JmvDailyProductionLog::STATUS_COMPLETED, $ownLog->fresh()->status);
    }

    public function test_submission_requires_a_supporting_document_and_export_excludes_drafts(): void
    {
        $adminStaff = $this->positionUser('admin-staff');
        $manager = $this->positionUser('mine-operations-manager');
        $this->actingAs($adminStaff)->post(route('jmv.operations.production.store'), $this->payload());
        $draft = JmvDailyProductionLog::firstOrFail();
        $this->actingAs($adminStaff)->post(route('jmv.operations.production.submit', $draft))->assertStatus(422);

        $export = $this->actingAs($manager)->get(route('jmv.operations.production.export'));
        $export->assertOk();
        $this->assertStringNotContainsString($draft->log_no, $export->getContent());
    }

    public function test_mine_shift_targets_are_managed_only_by_jmv_manager_and_supervisor(): void
    {
        $manager = $this->positionUser('mine-operations-manager');
        $supervisor = $this->positionUser('mine-supervisor');
        $staff = $this->positionUser('admin-staff');
        $geologist = $this->positionUser('geologist');
        $payload = ['location' => ' Mine 1 ', 'shift' => ' Day ', 'coal_target' => 50, 'weekly_coal_target' => 300];
        $this->actingAs($manager)->post(route('jmv.operations.production.targets.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $target = \App\Models\JmvProductionTarget::firstOrFail();
        $this->assertSame('General', $target->location);
        $this->assertSame('All shifts', $target->shift);
        $this->assertSame(50, $target->coal_target);
        $this->actingAs($supervisor)->put(route('jmv.operations.production.targets.update', $target), ['coal_target' => 65, 'weekly_coal_target' => 400, 'division_id' => 999, 'location' => 'Changed'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(65, $target->fresh()->coal_target);
        $this->assertSame(400, $target->fresh()->weeklyBagons());
        $this->assertEqualsWithDelta(174.0, $target->fresh()->weeklyMt(), 0.00001);
        $this->assertSame('General', $target->fresh()->location);
        $this->assertSame($supervisor->id, $target->fresh()->updated_by);
        $this->assertDatabaseCount('jmv_production_target_audits', 2);
        foreach ([$staff, $geologist] as $user) {
            $this->actingAs($user)->get(route('jmv.operations.production.index'))->assertOk()->assertSee('General production target')->assertSee('65')->assertDontSee('Save target');
            $this->post(route('jmv.operations.production.targets.store'), [...$payload, 'location' => 'Mine 2'])->assertForbidden();
            $this->put(route('jmv.operations.production.targets.update', $target), ['coal_target' => 999, 'weekly_coal_target' => 999])->assertForbidden();
        }
        $this->assertSame(65, $target->fresh()->coal_target);
        $this->assertDatabaseCount('jmv_production_target_audits', 2);
    }

    public function test_general_target_is_created_once_and_validates_daily_and_weekly_values(): void
    {
        $manager = $this->positionUser('mine-operations-manager');
        $this->actingAs($manager);
        $payload = ['coal_target' => 9, 'weekly_coal_target' => 63];
        $this->post(route('jmv.operations.production.targets.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('jmv.operations.production.targets.store'), [...$payload, 'location' => ' mine 1 ', 'shift' => 'day'])->assertSessionHasErrorsIn('productionTarget', 'coal_target');
        $this->post(route('jmv.operations.production.targets.store'), [...$payload, 'shift' => 'Night'])->assertSessionHasErrorsIn('productionTarget', 'coal_target');
        $this->assertDatabaseCount('jmv_production_targets', 1);
        foreach ([-1, 2.5, 'abc', null] as $invalid) {
            $this->post(route('jmv.operations.production.targets.store'), [...$payload, 'location' => 'Mine 2', 'coal_target' => $invalid])->assertSessionHasErrorsIn('productionTarget', 'coal_target');
        }
        $otherDivision = \App\Models\Division::create(['name' => 'Other Target Company']);
        $foreign = \App\Models\JmvProductionTarget::create([...$payload, 'division_id' => $otherDivision->id, 'created_by' => $manager->id, 'updated_by' => $manager->id]);
        $this->put(route('jmv.operations.production.targets.update', $foreign), ['coal_target' => 55])->assertNotFound();
        $this->get(route('jmv.operations.production.index'))->assertOk()->assertDontSee('Add mine / shift target')->assertSee('DAILY TARGET')->assertSee('WEEKLY TARGET')->assertSee('3.915 MT')->assertSee('63 Bagons')->assertSee('27.405 MT')->assertSee('Edit target');
    }

    public function test_administrator_can_see_and_edit_daily_and_weekly_targets(): void
    {
        $admin = $this->positionUser('admin-staff');
        $admin->update(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->post(route('jmv.operations.production.targets.store'), ['location' => 'Mine 1', 'shift' => 'Day', 'coal_target' => 9, 'weekly_coal_target' => 63])->assertRedirect()->assertSessionHasNoErrors();
        $target = \App\Models\JmvProductionTarget::firstOrFail();
        $this->get(route('jmv.operations.production.index'))->assertOk()->assertSee('Edit target')->assertSee('Save target');
        $this->put(route('jmv.operations.production.targets.update', $target), ['coal_target' => 10, 'weekly_coal_target' => 60])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10, $target->fresh()->coal_target);
        $this->assertSame(60, $target->fresh()->weekly_coal_target);
    }

    public function test_variance_is_coal_minus_target_and_cannot_be_overridden_by_input(): void
    {
        $manager = $this->positionUser('mine-operations-manager');
        $this->actingAs($manager)->post(route('jmv.operations.production.targets.store'), ['coal_target' => 9, 'weekly_coal_target' => 63])->assertSessionHasNoErrors();
        $this->post(route('jmv.operations.production.store'), $this->payload(['coal_output' => 7, 'excess_deficit' => 999, 'daily_target_bagons' => 999]))->assertRedirect()->assertSessionHasNoErrors();
        $log = JmvDailyProductionLog::firstOrFail();
        $this->assertSame(9, $log->daily_target_bagons);
        $this->assertSame(-2, $log->excess_deficit);
        $this->get(route('jmv.operations.production.edit', $log))->assertOk()->assertSee('Excess / Deficit (automatic)')->assertDontSee('name="excess_deficit"', false);
        $target = \App\Models\JmvProductionTarget::firstOrFail();
        $this->put(route('jmv.operations.production.targets.update', $target), ['coal_target' => 20, 'weekly_coal_target' => 100])->assertSessionHasNoErrors();
        $this->assertSame(-2, $log->fresh()->excess_deficit);
        $this->put(route('jmv.operations.production.update', $log), $this->payload(['coal_output' => 12, 'excess_deficit' => 'not a manual field']))->assertSessionHasNoErrors();
        $this->assertSame(9, $log->fresh()->daily_target_bagons);
        $this->assertSame(3, $log->fresh()->excess_deficit);
        $log->fresh()->update(['excess_deficit' => -3]);
        $migration = require database_path('migrations/2026_11_18_000001_reverse_jmv_production_variance.php');
        $migration->up();
        $this->assertSame(3, $log->fresh()->excess_deficit);
        $migration->up();
        $this->assertSame(3, $log->fresh()->excess_deficit);
        $this->put(route('jmv.operations.production.update', $log), $this->payload(['coal_output' => 9]))->assertSessionHasNoErrors();
        $this->assertSame(0, $log->fresh()->excess_deficit);
    }

    public function test_missing_target_does_not_invent_a_zero_target_or_accept_manual_variance(): void
    {
        $manager = $this->positionUser('mine-operations-manager');
        $this->actingAs($manager)->post(route('jmv.operations.production.store'), $this->payload(['excess_deficit' => 999]))->assertRedirect()->assertSessionHasNoErrors();
        $log = JmvDailyProductionLog::firstOrFail();
        $this->assertNull($log->daily_target_bagons);
        $this->assertNull($log->excess_deficit);
    }

    public function test_production_log_numbers_increment_and_reset_for_the_current_year(): void
    {
        $manager = $this->positionUser('mine-operations-manager');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-31 23:00:00', 'Asia/Manila'));
        $this->actingAs($manager)->post(route('jmv.operations.production.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jmv_daily_production_logs', ['log_no' => 'DPM-2026-0000']);
        $this->post(route('jmv.operations.production.store'), $this->payload(['location' => 'Mine 2']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jmv_daily_production_logs', ['log_no' => 'DPM-2026-0001']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-01 01:00:00', 'Asia/Manila'));
        $this->post(route('jmv.operations.production.store'), $this->payload(['log_date' => '2026-12-30']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jmv_daily_production_logs', ['log_no' => 'DPM-2027-0000']);
        $this->travelBack();
    }

    public function test_supporting_documents_can_be_previewed_without_forcing_a_download(): void
    {
        Storage::fake('local');
        $manager = $this->positionUser('mine-operations-manager');
        $this->actingAs($manager)->post(route('jmv.operations.production.store'), $this->payload(['attachments' => [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')]]))->assertSessionHasNoErrors();
        $log = JmvDailyProductionLog::firstOrFail();
        $attachment = $log->attachments()->firstOrFail();
        Storage::disk('local')->put($attachment->path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->get(route('jmv.operations.production.show', $log))->assertOk()->assertSee('View')->assertSee(route('jmv.operations.production.attachments.preview', [$log, $attachment]), false);
        $this->get(route('jmv.operations.production.attachments.preview', [$log, $attachment]))->assertOk()->assertViewHas('previewType', 'pdf')->assertSee('<iframe', false)->assertSee('Download');
        $inline = $this->get(route('jmv.operations.production.attachments.show', [$log, $attachment, 'inline' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', $inline->headers->get('Content-Disposition'));
        $download = $this->get(route('jmv.operations.production.attachments.show', [$log, $attachment]))->assertOk();
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        // Detect the actual file type instead of trusting its filename or stored MIME.
        Storage::disk('local')->put($attachment->path, '<html><script>alert(1)</script></html>');
        $this->get(route('jmv.operations.production.attachments.preview', [$log, $attachment]))->assertOk()->assertViewHas('previewType', null)->assertDontSee('<iframe', false);
        $this->get(route('jmv.operations.production.attachments.show', [$log, $attachment, 'inline' => 1]))->assertStatus(415);
        $geologist = $this->positionUser('geologist');
        $this->actingAs($geologist)->get(route('jmv.operations.production.attachments.preview', [$log, $attachment]))->assertNotFound();
        $this->get(route('jmv.operations.production.attachments.show', [$log, $attachment, 'inline' => 1]))->assertNotFound();
    }

    public function test_mobile_json_save_confirms_record_and_reports_validation_errors_without_duplicates(): void
    {
        Storage::fake('local');
        $this->actingAs($this->positionUser('mine-operations-manager'));
        $response = $this->post(route('jmv.operations.production.store'), $this->payload([
            'attachments' => [UploadedFile::fake()->create('mobile-report.pdf', 50, 'application/pdf')],
        ]), ['Accept' => 'application/json'])->assertCreated();
        $log = JmvDailyProductionLog::firstOrFail();
        $response->assertJsonPath('redirect_url', route('jmv.operations.production.show', $log));
        $this->assertSame(1, $log->attachments()->count());
        $this->post(route('jmv.operations.production.store'), $this->payload(), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(1, JmvDailyProductionLog::count());
        $this->post(route('jmv.operations.production.update', $log), $this->payload(['_method' => 'PUT', 'coal_output' => 20]), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('redirect_url', route('jmv.operations.production.show', $log));
        $this->assertSame(20, $log->fresh()->coal_output);
    }

    public function test_add_modal_does_not_inherit_the_last_listed_log_or_its_legacy_shift(): void
    {
        $this->actingAs($this->positionUser('mine-operations-manager'));
        $this->post(route('jmv.operations.production.targets.store'), ['coal_target' => 9, 'weekly_coal_target' => 63])->assertSessionHasNoErrors();
        $this->post(route('jmv.operations.production.store'), $this->payload(['shift' => '5:00 PM - 2:00 AM', 'leadman' => 'Old record leadman']))->assertSessionHasNoErrors();
        JmvDailyProductionLog::firstOrFail()->update(['daily_target_bagons' => 99]);
        $response = $this->get(route('jmv.operations.production.index'))->assertOk();
        preg_match('/<form id="addProductionLogForm".*?<\/form>/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('5:00 PM - 2:00 AM', $matches[0]);
        $this->assertStringNotContainsString('value="Old record leadman"', $matches[0]);
        $this->assertStringContainsString('data-daily-target="9"', $matches[0]);
        $this->assertStringContainsString('8AM - 5PM', $matches[0]);
        $this->assertStringContainsString('4PM - 1AM', $matches[0]);
        $this->assertStringContainsString('12AM - 8AM', $matches[0]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'log_date' => now()->format('Y-m-d'), 'location' => 'Mine 1', 'shift' => '8:00 AM - 5:00 PM',
            'leadman' => 'Don Mac', 'foreman' => 'Bari Kwatru', 'total_workers' => 18,
            'timber_used' => 11, 'timber_unit' => 'PCS', 'nails_used' => 1, 'nails_unit' => 'KG',
            'fuel_consumed' => 3, 'fuel_unit' => 'LTRS', 'soil_output' => 3, 'coal_output' => 15,
            'excess_deficit' => 6, 'remarks' => 'Daily production test',
        ], $overrides);
    }

    private function positionUser(string $code): User
    {
        $jmvId = DB::table('divisions')->where('name', 'JMV')->value('id');
        $position = DB::table('positions')->where('division_id', $jmvId)->where('code', $code)->first();
        static $number = 0;
        $number++;
        return User::create([
            'name' => 'JMV', 'lastname' => 'Operations', 'username' => 'jmvops'.$number,
            'email' => 'jmvops'.$number.'@example.test', 'cell_number' => '09000000000',
            'password' => Hash::make('password'), 'role' => $position->legacy_role,
            'division_id' => $position->division_id, 'department_id' => $position->department_id,
            'position_id' => $position->id, 'is_admin' => false, 'must_change_password' => false, 'status' => true,
        ]);
    }
}

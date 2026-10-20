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

    public function test_log_requires_output_and_is_unique_by_date_location_and_shift(): void
    {
        $manager = $this->positionUser('mine-supervisor');
        $this->actingAs($manager)->from(route('jmv.operations.production.index'))
            ->post(route('jmv.operations.production.store'), $this->payload(['soil_output' => 0, 'coal_output' => 0]))
            ->assertSessionHasErrors('soil_output');

        $this->actingAs($manager)->post(route('jmv.operations.production.store'), $this->payload())->assertSessionHasNoErrors();
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

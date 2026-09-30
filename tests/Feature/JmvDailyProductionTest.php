<?php

namespace Tests\Feature;

use App\Models\JmvDailyProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JmvDailyProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
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

        $this->actingAs($manager)->get(route('jmv.operations.production.export', ['location' => 'Mine 1']))
            ->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
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

    public function test_geologist_can_manage_but_chief_geologist_is_read_only(): void
    {
        $geologist = $this->positionUser('geologist');
        $chief = $this->positionUser('chief-geologist');
        $this->actingAs($geologist)->post(route('jmv.operations.production.store'), $this->payload())->assertSessionHasNoErrors();
        $log = JmvDailyProductionLog::firstOrFail();

        $this->actingAs($chief)->get(route('jmv.operations.production.index'))->assertOk()->assertSee($log->log_no);
        $this->actingAs($chief)->get(route('jmv.operations.production.export'))->assertOk();
        $this->actingAs($chief)->post(route('jmv.operations.production.store'), $this->payload(['location' => 'Mine 2']))->assertForbidden();
        $this->actingAs($chief)->get(route('jmv.operations.production.edit', $log))->assertForbidden();
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

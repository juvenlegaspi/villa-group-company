<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\JmvDailyProductionLog;
use App\Models\User;
use App\Services\JmvProductionDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JmvProductionDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin'): User
    {
        $division = Division::firstOrCreate(['name' => 'JMV']);
        $department = Department::create(['name' => 'Operations', 'division_id' => $division->id]);
        return User::create(['name' => 'Dashboard', 'lastname' => 'Test', 'username' => 'dash-'.str()->uuid(),
            'email' => str()->uuid().'@example.test', 'password' => 'test-password', 'role' => $role,
            'is_admin' => $role === 'admin', 'status' => true, 'must_change_password' => false,
            'division_id' => $division->id, 'department_id' => $department->id]);
    }

    private function log(User $user, array $overrides = []): JmvDailyProductionLog
    {
        return JmvDailyProductionLog::create(array_merge([
            'division_id' => $user->division_id, 'log_no' => 'TEST-'.str()->uuid(), 'log_date' => today(),
            'location' => 'Mine 3', 'shift' => '12:00 AM - 8:00 AM', 'leadman' => 'Lead', 'foreman' => 'Foreman',
            'total_workers' => 10, 'timber_used' => 1, 'timber_unit' => 'PCS', 'nails_used' => 1, 'nails_unit' => 'KG',
            'fuel_consumed' => 5, 'fuel_unit' => 'LTRS', 'soil_output' => 3, 'coal_output' => 12,
            'daily_target_bagons' => 9, 'excess_deficit' => 3, 'status' => JmvDailyProductionLog::STATUS_COMPLETED,
            'created_by' => $user->id,
        ], $overrides));
    }

    public function test_dashboard_renders_empty_charts_and_enforces_executive_access(): void
    {
        $user = $this->actor();
        $this->actingAs($user)->get(route('division.dashboard', 'JMV'))->assertOk()
            ->assertSee('Production Overview')->assertSee('No completed production logs in this period')
            ->assertSee('jmv-trend')->assertViewHas('production', fn ($p) => $p['coal'] === 0 && $p['achievement'] === null);
        $this->actingAs($this->actor('owner'))->get(route('division.dashboard', 'JMV'))->assertOk();
        $this->actingAs($this->actor('user'))->get(route('division.dashboard', 'JMV'))->assertForbidden();
    }

    public function test_metrics_exclude_unapproved_other_company_and_outside_dates_and_preserve_target_coverage(): void
    {
        $user = $this->actor();
        $log = $this->log($user);
        $this->log($user, ['status' => JmvDailyProductionLog::STATUS_PENDING_REVIEW, 'coal_output' => 999, 'shift' => '4:00 PM - 1:00 AM']);
        $this->log($user, ['status' => JmvDailyProductionLog::STATUS_DRAFT, 'location' => 'Mine 1']);
        $this->log($user, ['daily_target_bagons' => null, 'coal_output' => 4, 'location' => 'Mine 2']);
        $this->log($user, ['log_date' => today()->subMonths(2)]);
        $other = Division::create(['name' => 'Other Mining']);
        $this->log($user, ['division_id' => $other->id, 'coal_output' => 999]);
        $filters = ['from' => today()->toDateString(), 'to' => today()->toDateString()];
        $data = app(JmvProductionDashboardService::class)->build($user->division_id, $filters);
        $this->assertSame(16, $data['coal']);
        $this->assertSame(2, $data['officialCount']);
        $this->assertSame(133.3, $data['achievement']);
        $this->assertSame(3, $data['variance']);
        $this->assertSame(1, $data['missingTargets']);
        $this->assertNull($data['trend'][0]['target']);
        $this->assertSame(12, $data['mines'][0]['coal']);
        $this->assertSame(1, $data['states'][JmvDailyProductionLog::STATUS_PENDING_REVIEW]);
        $this->actingAs($user)->get(route('division.dashboard', ['division' => 'JMV'] + $filters))
            ->assertOk()->assertSee($log->log_no)->assertSee('133.3%')->assertSee('no saved target');
    }

    public function test_dates_are_validated_and_zero_targets_do_not_divide_by_zero(): void
    {
        $user = $this->actor();
        $this->log($user, ['daily_target_bagons' => 0]);
        $this->actingAs($user)->get(route('division.dashboard', ['division' => 'JMV', 'from' => today()->toDateString(), 'to' => today()->subDay()->toDateString()]))->assertSessionHasErrors('to');
        $this->get(route('division.dashboard', ['division' => 'JMV', 'from' => 'invalid']))->assertSessionHasErrors('from');
        $this->get(route('division.dashboard', ['division' => 'JMV', 'to' => today()->addDay()->toDateString()]))->assertSessionHasErrors('to');
        $data = app(JmvProductionDashboardService::class)->build($user->division_id, ['from' => today()->toDateString(), 'to' => today()->toDateString()]);
        $this->assertNull($data['achievement']);
        $this->assertSame(12, $data['variance']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\ShippingCalendarEvent;
use App\Models\ShippingPmsDetail;
use App\Models\ShippingPmsHeader;
use App\Models\User;
use App\Models\Vessel;
use App\Services\PmsCalendarService;
use App\Services\ShippingChecklistReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PmsCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function setupPms(): array
    {
        $division = Division::create(['name' => 'Villa shipping Lines']);
        $department = Department::create(['name' => 'Marine Operations']);
        $user = User::create([
            'name' => 'Developer', 'lastname' => 'Test', 'username' => 'pms-developer',
            'email' => 'pms-developer@example.test', 'password' => 'test-password',
            'role' => 'admin', 'is_admin' => true, 'status' => true, 'must_change_password' => false,
            'division_id' => $division->id, 'department_id' => $department->id,
        ]);
        config()->set('pms.development_only', true);
        config()->set('pms.developer_user_id', $user->id);
        $this->actingAs($user);
        $vessel = Vessel::create(['vessel_name' => 'MV PMS Integration']);
        $header = ShippingPmsHeader::create(['vessel_id' => $vessel->id, 'name' => 'Main Engine']);

        return [$user, $vessel, $header];
    }

    private function payload(string $frequency = 'Monthly'): array
    {
        return [
            'name' => 'Exhaust Valves', 'activity' => 'Inspect and clean', 'in_charge' => 'Chief Engineer',
            'frequency' => $frequency, 'start_date' => '2027-01-31', 'start_time' => '08:00',
            'expected_end_at' => '2027-01-31T09:00', 'reminder_minutes' => 60,
        ];
    }

    public function test_all_frequencies_create_one_linked_preventive_schedule(): void
    {
        [, $vessel, $header] = $this->setupPms();
        $mapping = [
            'Daily' => ['daily', 1], 'Weekly' => ['weekly', 1], 'Bi-Weekly' => ['weekly', 2],
            'Monthly' => ['monthly', 1], 'Quarterly' => ['monthly', 3], 'Semi-annual' => ['monthly', 6],
            'Annually' => ['monthly', 12], 'Five-year' => ['monthly', 60],
            'Every Abandon Ship Drill' => [null, 1], 'Every Fire Drill' => [null, 1],
        ];
        foreach ($mapping as $frequency => [$repeat, $interval]) {
            $this->post(route('shipping.pms.details.store', [$vessel, $header]), $this->payload($frequency))
                ->assertSessionHasNoErrors()->assertRedirect();
            $detail = ShippingPmsDetail::latest('id')->firstOrFail();
            $event = $detail->calendarEvent;
            $this->assertSame('preventive_maintenance', $event->checklist_type);
            $this->assertSame($vessel->id, $event->vessel_id);
            $this->assertSame($repeat, $event->recurrence_frequency);
            $this->assertEquals($interval, $event->recurrence_interval);
            $this->assertSame('Main Engine - Exhaust Valves', $event->title);
            $this->assertSame('2027-01-31 08:00:00', $event->starts_at->format('Y-m-d H:i:s'));
            $this->assertSame($event->id, app(PmsCalendarService::class)->createSchedule($header, $detail)->id);
        }
        $this->assertDatabaseCount('shipping_pms_details', 10);
        $this->assertDatabaseCount('shipping_calendar_events', 10);
        $this->assertDatabaseCount('shipping_calendar_audits', 10);
    }

    public function test_month_end_recurrence_and_completion_are_per_date(): void
    {
        [, $vessel, $header] = $this->setupPms();
        $this->post(route('shipping.pms.details.store', [$vessel, $header]), $this->payload())->assertSessionHasNoErrors();
        $event = ShippingCalendarEvent::sole();
        $range = ['vessel_id' => $vessel->id, 'start' => '2027-01-01', 'end' => '2027-03-31'];
        $events = $this->getJson(route('shipping.calendar.events.index', $range))->assertOk()->json();
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], array_map(fn ($item) => substr($item['start'], 0, 10), $events));
        $this->patchJson(route('shipping.calendar.events.complete', $event), ['occurrence_starts_at' => $events[0]['occurrence_starts_at']])->assertOk();
        $events = $this->getJson(route('shipping.calendar.events.index', $range))->assertOk()->json();
        $this->assertSame(['completed', 'scheduled', 'scheduled'], array_column($events, 'status'));
        $this->assertSame('scheduled', $event->fresh()->status);
    }

    public function test_semi_annual_and_five_year_dates_are_expanded_in_calendar(): void
    {
        [, $vessel, $header] = $this->setupPms();
        foreach (['Semi-annual' => ['2027-01-31', '2027-07-31', '2028-01-31'], 'Five-year' => ['2027-01-31', '2032-01-31', '2037-01-31']] as $frequency => $expected) {
            $this->post(route('shipping.pms.details.store', [$vessel, $header]), $this->payload($frequency))->assertSessionHasNoErrors();
            $event = ShippingCalendarEvent::latest('id')->firstOrFail();
            $events = $this->getJson(route('shipping.calendar.events.index', ['vessel_id' => $vessel->id, 'start' => $expected[0], 'end' => $expected[2]]))->assertOk()->json();
            $dates = array_values(array_map(fn ($item) => substr($item['start'], 0, 10), array_filter($events, fn ($item) => $item['event_id'] === $event->id)));
            $this->assertSame($expected, $dates);
        }
    }

    public function test_development_schedules_are_hidden_and_silent_until_released(): void
    {
        Notification::fake();
        [$user, $vessel, $header] = $this->setupPms();
        $this->post(route('shipping.pms.details.store', [$vessel, $header]), $this->payload())->assertSessionHasNoErrors();
        $event = ShippingCalendarEvent::sole();
        $other = $user->replicate();
        $other->username = 'other-admin';
        $other->email = 'other-admin@example.test';
        $other->save();
        $range = ['vessel_id' => $vessel->id, 'start' => '2027-01-01', 'end' => '2027-02-01'];
        $this->actingAs($other)->getJson(route('shipping.calendar.events.index', $range))->assertOk()->assertExactJson([]);
        $this->getJson(route('shipping.calendar.events.show', $event))->assertForbidden();
        $this->patchJson(route('shipping.calendar.events.complete', $event))->assertForbidden();
        $this->patchJson(route('shipping.calendar.events.cancel', $event))->assertForbidden();
        $this->deleteJson(route('shipping.calendar.events.destroy', $event))->assertForbidden();
        $service = app(ShippingChecklistReminderService::class);
        $this->assertTrue($service->recipients($event)->isEmpty());
        $this->assertSame(0, $service->sendDueReminders($event->starts_at->copy()->subHour())['system_sent']);
        Notification::assertNothingSent();

        config()->set('pms.development_only', false);
        $this->getJson(route('shipping.calendar.events.show', $event))->assertOk()->assertJsonPath('event.checklist_type', 'preventive_maintenance');
    }

    public function test_reminders_resume_for_the_assigned_captain_after_release_without_duplicates(): void
    {
        Notification::fake();
        [$user, $vessel, $header] = $this->setupPms();
        $captain = $user->replicate();
        $captain->username = 'assigned-captain';
        $captain->email = 'assigned-captain@example.test';
        $captain->role = 'captain';
        $captain->is_admin = false;
        $captain->save();
        $vessel->update(['captain_id' => $captain->id]);
        $this->post(route('shipping.pms.details.store', [$vessel, $header]), $this->payload('Semi-annual'))->assertSessionHasNoErrors();
        $event = ShippingCalendarEvent::sole();
        $service = app(ShippingChecklistReminderService::class);
        $due = $event->starts_at->copy()->addMonthsNoOverflow(6)->subHour();
        $this->assertSame(0, $service->sendDueReminders($due)['system_sent']);
        config()->set('pms.development_only', false);
        $sent = $service->sendDueReminders($due);
        $this->assertSame(1, $sent['system_sent']);
        $this->assertSame(1, $sent['email_sent']);
        $this->assertSame(0, $service->sendDueReminders($due)['system_sent']);
        Notification::assertSentTo($captain, \App\Notifications\CalendarEventNotification::class);
    }

    public function test_invalid_schedule_is_rejected_without_partial_records(): void
    {
        [, $vessel, $header] = $this->setupPms();
        $this->post(route('shipping.pms.details.store', [$vessel, $header]), [...$this->payload(), 'expected_end_at' => '2027-01-31T07:00'])->assertSessionHasErrors('expected_end_at');
        $this->post(route('shipping.pms.details.store', [$vessel, $header]), [...$this->payload(), 'frequency' => 'Invalid'])->assertSessionHasErrors('frequency');
        $this->assertDatabaseCount('shipping_pms_details', 0);
        $this->assertDatabaseCount('shipping_calendar_events', 0);
    }
}

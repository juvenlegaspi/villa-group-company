<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
use App\Models\ShippingCalendarEvent;
use App\Models\User;
use App\Models\Vessel;
use App\Notifications\CalendarEventNotification;
use App\Services\ShippingChecklistReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShippingCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_operations_manager_assigned_captain_and_admin_can_create_vessel_checklists(): void
    {
        $org = $this->organization();

        $this->actingAs($org['manager'])->get(route('shipping.calendar'))->assertOk()->assertSee('Add Checklist');
        $this->actingAs($org['captain'])->get(route('shipping.calendar'))->assertOk()->assertSee('Add Checklist');
        $this->actingAs($org['member'])->get(route('shipping.calendar'))->assertOk()->assertDontSee('id="newChecklistButton"', false);

        $response = $this->actingAs($org['manager'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id))
            ->assertCreated()->assertJsonPath('message', 'Checklist scheduled successfully.');
        $eventId = $response->json('event_id');
        $this->assertDatabaseHas('shipping_calendar_events', [
            'id' => $eventId, 'vessel_id' => $org['vessel']->id, 'checklist_type' => 'routine',
            'visibility' => 'vessel', 'email_reminder' => 1,
        ]);
        $this->assertDatabaseHas('shipping_calendar_audits', ['event_id' => $eventId, 'action' => 'created']);

        $captainResponse = $this->actingAs($org['captain'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id, ['title' => 'Captain checklist']))->assertCreated();
        $this->actingAs($org['captain'])->putJson(route('shipping.calendar.events.update', $eventId), $this->payload($org['vessel']->id))->assertForbidden();
        $captainEventId = $captainResponse->json('event_id');
        $this->actingAs($org['captain'])->putJson(route('shipping.calendar.events.update', $captainEventId), $this->payload($org['vessel']->id, ['title' => 'Updated captain checklist']))->assertOk();
        $this->actingAs($org['captain'])->patchJson(route('shipping.calendar.events.cancel', $captainEventId))->assertOk();
        $this->actingAs($org['member'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id))->assertForbidden();
        $this->actingAs($org['otherCaptain'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id))->assertForbidden();
        $this->actingAs($org['wrongDepartmentManager'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id))->assertForbidden();

        $admin = User::create(['name' => 'System', 'lastname' => 'Administrator', 'username' => 'calendar-admin', 'email' => 'calendar-admin@example.test', 'password' => Hash::make('villa@2026'), 'role' => 'admin', 'is_admin' => true, 'must_change_password' => false, 'status' => true, 'division_id' => $org['division']->id, 'department_id' => $org['manager']->department_id, 'position_id' => $org['manager']->position_id]);
        $this->actingAs($admin)->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id, ['title' => 'Administrator checklist']))->assertCreated();
        $this->actingAs($admin)->deleteJson(route('shipping.calendar.events.destroy', $eventId))->assertOk();
        $this->assertSoftDeleted('shipping_calendar_events', ['id' => $eventId]);
    }

    public function test_vessel_filter_multiple_checklists_and_daily_recurrence_are_supported(): void
    {
        $org = $this->organization();
        foreach ([['Morning inspection', '08:00', '09:00'], ['Toolbox meeting', '09:30', '10:00']] as [$title, $start, $end]) {
            $this->actingAs($org['manager'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id, [
                'title' => $title, 'starts_at' => "2026-09-02 {$start}:00", 'ends_at' => "2026-09-02 {$end}:00",
            ]))->assertCreated();
        }
        $this->actingAs($org['manager'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id, [
            'title' => 'Daily engine checklist', 'recurrence_frequency' => 'daily', 'recurrence_ends_on' => '2026-09-05',
        ]))->assertCreated();

        $this->actingAs($org['manager'])->getJson(route('shipping.calendar.events.index', [
            'start' => '2026-09-02', 'end' => '2026-09-05', 'vessel_id' => $org['vessel']->id,
        ]))->assertOk()->assertJsonCount(6);
        $this->actingAs($org['captain'])->getJson(route('shipping.calendar.events.index', [
            'start' => '2026-09-02', 'end' => '2026-09-05', 'vessel_id' => $org['otherVessel']->id,
        ]))->assertForbidden();

        $this->actingAs($org['manager'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id, [
            'title' => 'Weekly safety meeting', 'starts_at' => '2026-09-01 07:30:00', 'ends_at' => '2026-09-01 08:00:00',
            'recurrence_frequency' => 'weekly', 'recurrence_ends_on' => '2026-09-30',
        ]))->assertCreated();
        $this->actingAs($org['manager'])->getJson(route('shipping.calendar.events.index', [
            'start' => '2026-09-01', 'end' => '2026-09-30', 'vessel_id' => $org['vessel']->id,
        ]))->assertOk()->assertJsonFragment(['title' => 'Weekly safety meeting']);

        $this->actingAs($org['manager'])->postJson(route('shipping.calendar.events.store'), $this->payload($org['vessel']->id, [
            'title' => 'Month-end inspection', 'starts_at' => '2026-01-31 08:00:00', 'ends_at' => '2026-01-31 09:00:00',
            'recurrence_frequency' => 'monthly', 'recurrence_ends_on' => '2026-04-30',
        ]))->assertCreated();
        $monthly = $this->actingAs($org['manager'])->getJson(route('shipping.calendar.events.index', [
            'start' => '2026-01-01', 'end' => '2026-04-30', 'vessel_id' => $org['vessel']->id,
        ]))->assertOk()->json();
        $monthEndDates = collect($monthly)->where('title', 'Month-end inspection')->pluck('start')
            ->map(fn (string $date) => substr($date, 0, 10))->values()->all();
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'], $monthEndDates);
    }

    public function test_due_reminder_sends_system_email_and_sms_only_to_assigned_captain_and_operations_manager(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');
        $org = $this->organization();
        Notification::fake();
        Http::fake(['*' => Http::response([['message_id' => 'SMS-1001', 'status' => 'Pending']], 200)]);
        config()->set('services.semaphore.enabled', true);
        config()->set('services.semaphore.api_key', 'test-key');

        $event = ShippingCalendarEvent::create([
            ...$this->payload($org['vessel']->id, ['starts_at' => '2026-09-01 08:00:00', 'ends_at' => '2026-09-01 09:00:00']),
            'division_id' => $org['division']->id, 'created_by' => $org['manager']->id,
            'updated_by' => $org['manager']->id, 'visibility' => 'vessel', 'email_reminder' => true, 'status' => 'scheduled',
        ]);

        $summary = app(ShippingChecklistReminderService::class)->sendDueReminders(now());
        $this->assertSame(2, $summary['system_sent']);
        $this->assertSame(2, $summary['email_sent']);
        $this->assertSame(2, $summary['sms_sent']);
        Notification::assertSentTo($org['manager'], CalendarEventNotification::class, 2);
        Notification::assertSentTo($org['captain'], CalendarEventNotification::class, 2);
        Notification::assertNotSentTo($org['member'], CalendarEventNotification::class);
        Notification::assertNotSentTo($org['otherCaptain'], CalendarEventNotification::class);
        Notification::assertNotSentTo($org['wrongDepartmentManager'], CalendarEventNotification::class);
        $this->assertDatabaseCount('shipping_calendar_reminder_logs', 6);
        $this->assertDatabaseMissing('shipping_calendar_reminder_logs', ['event_id' => $event->id, 'status' => 'failed']);
        Http::assertSentCount(2);
    }

    public function test_checklist_attachments_are_private_audited_and_limited_to_authorized_vessel_users(): void
    {
        Storage::fake('local');
        config()->set('services.shipping_calendar.max_attachments', 1);
        $org = $this->organization();
        $file = UploadedFile::fake()->create('engine-checklist.pdf', 240, 'application/pdf');

        $response = $this->actingAs($org['manager'])->post(
            route('shipping.calendar.events.store'),
            [...$this->payload($org['vessel']->id), 'attachments' => [$file]],
            ['Accept' => 'application/json'],
        )->assertCreated();

        $eventId = $response->json('event_id');
        $attachment = DB::table('shipping_calendar_attachments')->where('event_id', $eventId)->first();
        $this->assertNotNull($attachment);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('shipping_calendar_audits', ['event_id' => $eventId, 'action' => 'attachment_uploaded']);

        $download = route('shipping.calendar.events.attachments.download', [$eventId, $attachment->id]);
        $this->actingAs($org['member'])->get($download)->assertOk();
        $this->actingAs($org['otherCaptain'])->get($download)->assertForbidden();
        $this->actingAs($org['captain'])->deleteJson(route('shipping.calendar.events.attachments.destroy', [$eventId, $attachment->id]))->assertForbidden();

        $extraFile = UploadedFile::fake()->create('additional-checklist.pdf', 100, 'application/pdf');
        $this->actingAs($org['manager'])->post(
            route('shipping.calendar.events.update', $eventId),
            [...$this->payload($org['vessel']->id), '_method' => 'PUT', 'attachments' => [$extraFile]],
            ['Accept' => 'application/json'],
        )->assertUnprocessable();

        $this->actingAs($org['manager'])->deleteJson(route('shipping.calendar.events.attachments.destroy', [$eventId, $attachment->id]))
            ->assertOk()->assertJsonPath('message', 'Checklist attachment removed.');
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('shipping_calendar_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseHas('shipping_calendar_audits', ['event_id' => $eventId, 'action' => 'attachment_deleted']);

        $invalid = UploadedFile::fake()->create('unsafe.exe', 10, 'application/octet-stream');
        $this->actingAs($org['manager'])->post(
            route('shipping.calendar.events.store'),
            [...$this->payload($org['vessel']->id), 'attachments' => [$invalid]],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
    }

    public function test_missed_reminder_is_caught_up_retried_and_not_duplicated(): void
    {
        Carbon::setTestNow('2026-09-01 08:10:00');
        $org = $this->organization();
        Notification::fake();
        Http::fake(['*' => Http::response([['message_id' => 'SMS-CATCHUP', 'status' => 'Pending']], 200)]);
        config()->set('services.semaphore.enabled', true);
        config()->set('services.semaphore.api_key', 'test-key');

        $event = ShippingCalendarEvent::create([
            ...$this->payload($org['vessel']->id, ['starts_at' => '2026-09-01 08:00:00', 'ends_at' => '2026-09-01 09:00:00']),
            'division_id' => $org['division']->id, 'created_by' => $org['manager']->id,
            'updated_by' => $org['manager']->id, 'visibility' => 'vessel', 'email_reminder' => true, 'status' => 'scheduled',
        ]);
        DB::table('shipping_calendar_reminder_logs')->insert([
            'event_id' => $event->id, 'user_id' => $org['manager']->id, 'occurrence_starts_at' => '2026-09-01 08:00:00',
            'channel' => 'sms', 'status' => 'failed', 'attempts' => 4, 'error_message' => 'Temporary provider failure',
            'sent_at' => null, 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute(),
        ]);

        $summary = app(ShippingChecklistReminderService::class)->sendDueReminders(now());
        $this->assertSame(2, $summary['system_sent']);
        $this->assertSame(2, $summary['email_sent']);
        $this->assertSame(2, $summary['sms_sent']);
        $this->assertDatabaseHas('shipping_calendar_reminder_logs', [
            'event_id' => $event->id, 'user_id' => $org['manager']->id, 'channel' => 'sms', 'status' => 'sent', 'attempts' => 5,
        ]);

        $again = app(ShippingChecklistReminderService::class)->sendDueReminders(now());
        $this->assertSame(0, $again['system_sent']);
        $this->assertSame(0, $again['email_sent']);
        $this->assertSame(0, $again['sms_sent']);
        $this->assertDatabaseCount('shipping_calendar_reminder_logs', 6);
    }

    private function organization(): array
    {
        $division = Division::firstOrCreate(['name' => 'Villa shipping Lines']);
        $department = Department::firstOrCreate(['division_id' => $division->id, 'name' => 'Marine Operations']);
        $technicalDepartment = Department::firstOrCreate(['division_id' => $division->id, 'name' => 'Technical Department']);
        $managerPosition = Position::firstOrCreate(['division_id' => $division->id, 'department_id' => $department->id, 'code' => 'operations-manager'], ['name' => 'Operations Manager', 'legacy_role' => 'manager', 'is_active' => true]);
        $captainPosition = Position::firstOrCreate(['division_id' => $division->id, 'department_id' => $department->id, 'code' => 'vessel-captain'], ['name' => 'Vessel Captain', 'legacy_role' => 'captain', 'is_active' => true]);
        $memberPosition = Position::firstOrCreate(['division_id' => $division->id, 'department_id' => $department->id, 'code' => 'deck-officer'], ['name' => 'Deck Officer', 'legacy_role' => 'staff', 'is_active' => true]);
        $wrongManagerPosition = Position::firstOrCreate(['division_id' => $division->id, 'department_id' => $technicalDepartment->id, 'code' => 'operations-manager'], ['name' => 'Operations Manager', 'legacy_role' => 'manager', 'is_active' => true]);
        $manager = $this->user($division, $department, $managerPosition, 'Operations Manager', 'manager@example.test', '09170000001', 'manager');
        $captain = $this->user($division, $department, $captainPosition, 'Assigned Captain', 'captain@example.test', '09170000002', 'captain');
        $otherCaptain = $this->user($division, $department, $captainPosition, 'Other Captain', 'other-captain@example.test', '09170000003', 'captain');
        $member = $this->user($division, $department, $memberPosition, 'Vessel Member', 'member@example.test', '09170000004', 'staff');
        $wrongDepartmentManager = $this->user($division, $technicalDepartment, $wrongManagerPosition, 'Wrong Department Manager', 'wrong-manager@example.test', '09170000005', 'manager');
        $vessel = Vessel::create(['vessel_name' => 'MV Checklist One', 'captain_id' => $captain->id]);
        $otherVessel = Vessel::create(['vessel_name' => 'MV Checklist Two', 'captain_id' => $otherCaptain->id]);
        foreach ([[$captain, $vessel, true], [$member, $vessel, false], [$otherCaptain, $otherVessel, true]] as [$user, $assignedVessel, $primary]) {
            DB::table('user_vessel_assignments')->insert(['user_id' => $user->id, 'vessel_id' => $assignedVessel->id, 'assigned_by' => $manager->id, 'is_primary' => $primary, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return compact('division', 'department', 'manager', 'captain', 'otherCaptain', 'member', 'wrongDepartmentManager', 'vessel', 'otherVessel');
    }

    private function user(Division $division, Department $department, Position $position, string $name, string $email, string $phone, string $role): User
    {
        return User::create(['name' => $name, 'lastname' => 'User', 'username' => str($email)->before('@'), 'email' => $email, 'cell_number' => $phone, 'password' => Hash::make('villa@2026'), 'role' => $role, 'is_admin' => false, 'must_change_password' => false, 'status' => true, 'division_id' => $division->id, 'department_id' => $department->id, 'position_id' => $position->id]);
    }

    private function payload(int $vesselId, array $overrides = []): array
    {
        return array_merge(['title' => 'Deck and engine checklist', 'checklist_type' => 'routine', 'description' => 'Perform the scheduled checks.', 'location' => 'Main Deck', 'starts_at' => '2026-09-02 08:00:00', 'ends_at' => '2026-09-02 09:00:00', 'all_day' => false, 'vessel_id' => $vesselId, 'color' => '#2563eb', 'recurrence_frequency' => null, 'recurrence_interval' => 1, 'recurrence_ends_on' => null, 'reminder_minutes' => 0], $overrides);
    }
}

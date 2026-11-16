<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayBilling;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use App\Services\HaruhayDueReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HaruhayDueReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 12)->setTime(9, 0));
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.semaphore.enabled' => true, 'services.semaphore.api_key' => 'test-only', 'services.semaphore.endpoint' => 'https://sms.example.test/messages', 'haruhay_notifications.due_reminder_sms_enabled' => true, 'haruhay_notifications.due_reminder_days' => 3]);
        Http::fake(['https://sms.example.test/messages' => Http::response([['message_id' => 789, 'status' => 'Pending']], 200)]);
    }

    private function draft(): HaruhayBilling
    {
        $division = Division::firstOrCreate(['name' => 'Haruhay']);
        $department = Department::create(['name' => 'Property Management', 'division_id' => $division->id]);
        $user = User::create(['name' => 'Billing Staff', 'lastname' => 'Test', 'username' => (string) str()->uuid(), 'email' => str()->uuid().'@example.test', 'password' => 'test-password-123', 'division_id' => $division->id, 'department_id' => $department->id, 'role' => 'admin', 'is_admin' => true, 'status' => true, 'must_change_password' => false]);
        $this->actingAs($user);
        $property = HaruhayProperty::create(['name' => 'Haruhay Space', 'owner' => 'Owner', 'type' => 'Dormitory', 'street_barangay' => 'Test Street', 'city' => 'Cebu', 'province' => 'Cebu', 'status' => 'Operational', 'division_id' => $division->id]);
        $unit = HaruhayUnit::create(['property_id' => $property->id, 'floor' => 3, 'unit_type' => 'Single bed', 'monthly_rent' => 10000, 'room_condition' => 'Ready']);
        $tenant = HaruhayTenant::create(['unit_id' => $unit->id, 'name' => 'Billing Applicant', 'email' => 'tenant@example.test', 'contact_number' => '09171234567', 'move_in_date' => today(), 'status' => 'Active']);
        $this->post(route('haruhay.contracts.store', $tenant), ['starts_on' => today()->toDateString(), 'ends_on' => today()->addMonthsNoOverflow(3)->subDay()->toDateString(), 'monthly_rent' => 10000, 'billing_starts_on' => today()->toDateString(), 'first_due_on' => today()->toDateString(), 'due_day' => today()->day, 'billing_cycle' => 'lease_anniversary', 'first_month_policy' => 'full_month', 'deposit_required' => 10000, 'advance_required' => 10000, 'advance_application' => 'Apply to first billing month'])->assertSessionHasNoErrors();
        $contract = HaruhayLeaseContract::sole();
        $this->post(route('haruhay.billing.store'), ['contract_id' => $contract->id, 'period_start' => today()->toDateString(), 'water' => 100, 'electric' => 500, 'utilities_other' => 0, 'parking' => 0, 'addons_other' => 0, 'penalty' => 0, 'discount' => 0, 'utilities_ready' => 1])->assertSessionHasNoErrors();

        return HaruhayBilling::sole();
    }

    private function upcoming(int $days = 3): HaruhayBilling
    {
        $bill = $this->draft();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $bill->update(['due_on' => today('Asia/Manila')->addDays($days)]);

        return $bill->fresh();
    }

    private function pay(HaruhayBilling $bill, int $amount): void
    {
        $this->post(route('haruhay.collections.store', $bill->tenant), ['submission_token' => (string) str()->uuid(), 'receipt_type' => 'payment', 'billing_id' => $bill->id, 'paid_on' => today()->toDateString(), 'amount' => $amount, 'method' => 'Cash'])->assertSessionHasNoErrors();
    }

    public function test_due_soon_bill_receives_one_sms_even_when_scanned_again_next_day(): void
    {
        $bill = $this->upcoming();
        $service = app(HaruhayDueReminderService::class);
        $service->run();
        $service->run();
        $this->travel(1)->days();
        $service->run();
        $this->assertDatabaseCount('haruhay_due_reminders', 1);
        Http::assertSentCount(1);
        Mail::assertNothingSent();
        Http::assertSent(fn ($request) => str_contains($request['message'], 'PHP 10,600.00') && str_contains($request['message'], 'Nov 15, 2026'));
        $this->get(route('haruhay.billing.show', $bill))->assertOk()->assertSee('Upcoming Due Date SMS Reminder')->assertSee('Submitted');
    }

    public function test_drafts_paid_bills_overdue_today_and_far_future_are_excluded(): void
    {
        $bill = $this->draft();
        $bill->update(['due_on' => today()->addDays(3)]);
        $service = app(HaruhayDueReminderService::class);
        $service->run();
        $this->assertDatabaseCount('haruhay_due_reminders', 0);
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        foreach ([-1, 0, 4] as $days) {
            $bill->update(['due_on' => today()->addDays($days)]);
            $service->run();
        }
        $bill->update(['due_on' => today()->addDays(3)]);
        $this->pay($bill, 10600);
        $service->run();
        $this->assertDatabaseCount('haruhay_due_reminders', 0);
        Http::assertNothingSent();
    }

    public function test_queued_reminder_is_suppressed_if_bill_is_fully_paid_before_sending(): void
    {
        $bill = $this->upcoming();
        $service = app(HaruhayDueReminderService::class);
        $service->enqueue();
        $this->pay($bill, 10600);
        $service->run();
        $this->assertDatabaseHas('haruhay_due_reminders', ['billing_id' => $bill->id, 'status' => 'Blocked']);
        Http::assertNothingSent();
    }

    public function test_partial_payment_after_queue_uses_remaining_balance_at_send_time(): void
    {
        $bill = $this->upcoming();
        $service = app(HaruhayDueReminderService::class);
        $service->enqueue();
        $this->pay($bill, 3000);
        $service->run();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['message'], 'PHP 7,600.00') && ! str_contains($request['message'], '10,600.00'));
    }

    public function test_only_haruhay_bills_are_reminded_and_changed_dates_are_not_sent_early(): void
    {
        $bill = $this->upcoming();
        $service = app(HaruhayDueReminderService::class);
        $service->enqueue();
        $bill->update(['due_on' => today()->addDays(10)]);
        $service->run();
        Http::assertNothingSent();
        $foreign = Division::firstOrCreate(['name' => 'Other Company']);
        $bill->unit->property->update(['division_id' => $foreign->id]);
        $bill->update(['due_on' => today()->addDays(2)]);
        $service->run();
        $this->assertDatabaseCount('haruhay_due_reminders', 1);
    }

    public function test_reminders_are_daytime_only_and_can_be_disabled(): void
    {
        $bill = $this->upcoming();
        $service = app(HaruhayDueReminderService::class);
        $this->travelTo(now()->setTime(8, 59));
        $service->run();
        $this->travelTo(now()->setTime(18, 0));
        $service->run();
        $this->travelTo(now()->setTime(9, 0));
        config(['haruhay_notifications.due_reminder_sms_enabled' => false]);
        $service->run();
        $this->assertDatabaseCount('haruhay_due_reminders', 0);
        Http::assertNothingSent();
    }

    public function test_provider_failure_is_not_automatically_retried_and_bad_numbers_are_not_sent(): void
    {
        $bill = $this->upcoming();
        $bill->tenant->update(['contact_number' => 'invalid']);
        $service = app(HaruhayDueReminderService::class);
        $service->run();
        $this->assertDatabaseHas('haruhay_due_reminders', ['status' => 'Blocked']);
        Http::assertNothingSent();
        $bill->tenant->update(['contact_number' => '09171234567']);
        $bill->update(['due_on' => today()->addDays(2)]);
        Http::fake(['https://sms.example.test/messages' => Http::failedConnection()]);
        $service->run();
        $service->run();
        $this->assertDatabaseHas('haruhay_due_reminders',['status' => 'NeedsReview']);
        Http::assertSentCount(1);
    }
}

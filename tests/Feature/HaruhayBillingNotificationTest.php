<?php

namespace Tests\Feature;

use App\Mail\HaruhayBillIssued;
use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayBilling;
use App\Models\HaruhayBillingDelivery;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use App\Services\HaruhayBillingNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HaruhayBillingNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        config(['mail.default' => 'smtp', 'services.semaphore.enabled' => true, 'services.semaphore.api_key' => 'test-only', 'services.semaphore.endpoint' => 'https://sms.example.test/messages', 'haruhay_notifications.email_enabled' => true, 'haruhay_notifications.sms_enabled' => true]);
        Http::fake(['https://sms.example.test/messages' => Http::response([['message_id' => 123, 'status' => 'Pending']], 200)]);
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

    private function issue(HaruhayBilling $bill): void
    {
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
    }

    public function test_only_issue_queues_both_channels_after_applying_advance_and_each_sends_once(): void
    {
        $bill = $this->draft();
        $this->assertDatabaseCount('haruhay_billing_deliveries', 0);
        $this->post(route('haruhay.collections.store', $bill->tenant), ['submission_token' => (string) str()->uuid(), 'receipt_type' => 'advance', 'paid_on' => today()->toDateString(), 'amount' => 3000, 'method' => 'Cash'])->assertSessionHasNoErrors();
        $this->issue($bill);
        $this->assertDatabaseCount('haruhay_billing_deliveries', 2);
        $service = app(HaruhayBillingNotificationService::class);
        $service->enqueue($bill->fresh());
        $this->assertDatabaseCount('haruhay_billing_deliveries', 2);
        $delivery = HaruhayBillingDelivery::where('channel', 'email')->sole();
        $this->assertSame(7600.0, (float) $delivery->payload['balance']);
        $this->assertSame(3000.0, (float) $delivery->payload['paid']);
        Mail::assertNothingSent();
        $service->sendPending();
        $service->sendPending();
        Mail::assertSent(HaruhayBillIssued::class, fn ($mail) => $mail->hasTo('tenant@example.test') && (float) $mail->bill['balance'] === 7600.0);
        Mail::assertSentCount(1);
        Http::assertSentCount(1);
        $this->assertSame(2, HaruhayBillingDelivery::where('status', 'Submitted')->count());
        $this->get(route('haruhay.billing.show', $bill))->assertOk()->assertSee('Tenant Email')->assertSee('Submitted');
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasErrors('billing', null, 'billingForm');
    }

    public function test_missing_contact_is_blocked_and_corrected_recipient_can_retry_without_resending_sms(): void
    {
        $bill = $this->draft();
        $bill->tenant->update(['email' => null]);
        $this->issue($bill);
        $service = app(HaruhayBillingNotificationService::class);
        $service->sendPending();
        $this->assertDatabaseHas('haruhay_billing_deliveries', ['channel' => 'email', 'status' => 'Blocked']);
        $this->assertDatabaseHas('haruhay_billing_deliveries', ['channel' => 'sms', 'status' => 'Submitted']);
        $bill->tenant->update(['email' => 'corrected@example.test']);
        $this->post(route('haruhay.billing.notifications.retry', $bill))->assertSessionHasNoErrors();
        $service->sendPending();
        Mail::assertSent(HaruhayBillIssued::class, fn ($mail) => $mail->hasTo('corrected@example.test'));
        Http::assertSentCount(1);
    }

    public function test_provider_timeout_is_not_retried_or_claimed_delivered(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        Http::fake(['https://sms.example.test/messages' => Http::failedConnection()]);
        $service = app(HaruhayBillingNotificationService::class);
        $service->sendPending();
        $this->assertDatabaseHas('haruhay_billing_deliveries', ['channel' => 'sms', 'status' => 'NeedsReview']);
        $this->post(route('haruhay.billing.notifications.retry', $bill))->assertSessionHasNoErrors();
        $service->sendPending();
        Http::assertSentCount(1);
        Mail::assertSentCount(1);
    }

    public function test_test_mailer_disabled_sms_and_invalid_contacts_never_claim_success(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        config(['mail.default' => 'log', 'haruhay_notifications.sms_enabled' => false]);
        app(HaruhayBillingNotificationService::class)->sendPending();
        $this->assertSame(2, HaruhayBillingDelivery::where('status', 'Blocked')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
        config(['services.semaphore.enabled' => false, 'haruhay_notifications.sms_enabled' => true]);
        $this->post(route('haruhay.billing.notifications.retry', $bill))->assertSessionHasNoErrors();
        app(HaruhayBillingNotificationService::class)->sendPending();
        Http::assertNothingSent();
    }

    public function test_mail_template_contains_breakdown_balance_due_date_and_escapes_names(): void
    {
        $bill = $this->draft();
        $bill->update(['tenant_name' => '<script>test</script>']);
        $this->issue($bill);
        $mail = new HaruhayBillIssued(HaruhayBillingDelivery::where('channel', 'email')->sole()->payload);
        $html = $mail->render();
        $this->assertStringContainsString('Water', $html);
        $this->assertStringContainsString('Electric', $html);
        $this->assertStringContainsString('10,600.00', $html);
        $this->assertStringContainsString($bill->due_on->format('M d, Y'), $html);
        $this->assertStringContainsString('&lt;script&gt;test&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>test</script>', $html);
    }

    public function test_worker_does_not_backfill_earlier_issued_bills(): void
    {
        $bill = $this->draft();
        $bill->update(['issued_on' => today(), 'finalized_at' => now()]);
        $this->artisan('haruhay:send-billing-notifications')->assertSuccessful();
        $this->assertDatabaseCount('haruhay_billing_deliveries', 0);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_readonly_users_cannot_retry_and_invalid_mobile_is_blocked(): void
    {
        $bill = $this->draft();
        $bill->tenant->update(['contact_number' => '123']);
        $this->issue($bill);
        app(HaruhayBillingNotificationService::class)->sendPending();
        $this->assertDatabaseHas('haruhay_billing_deliveries', ['channel' => 'sms', 'status' => 'Blocked']);
        Http::assertNothingSent();
        $user = auth()->user();
        $user->update(['role' => 'owner', 'is_admin' => false]);
        $this->actingAs($user->fresh())->post(route('haruhay.billing.notifications.retry', $bill))->assertForbidden();
    }

    public function test_email_failure_does_not_stop_sms_and_is_not_resent_automatically(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Test SMTP unavailable'));
        $service = app(HaruhayBillingNotificationService::class);
        $service->sendPending();
        $service->sendPending();
        $this->assertDatabaseHas('haruhay_billing_deliveries', ['channel' => 'email', 'status' => 'NeedsReview']);
        $this->assertDatabaseHas('haruhay_billing_deliveries', ['channel' => 'sms', 'status' => 'Submitted']);
        Http::assertSentCount(1);
    }
}

<?php

namespace Tests\Feature;

use App\Mail\HaruhayPaymentReceived;
use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayBilling;
use App\Models\HaruhayCollection;
use App\Models\HaruhayCollectionDelivery;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use App\Services\HaruhayCollectionNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HaruhayCollectionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['mail.default' => 'smtp', 'services.semaphore.enabled' => true, 'services.semaphore.api_key' => 'test-only', 'services.semaphore.endpoint' => 'https://sms.example.test/messages', 'haruhay_notifications.receipt_email_enabled' => true, 'haruhay_notifications.receipt_sms_enabled' => true]);
        Http::fake(['https://sms.example.test/messages' => Http::response([['message_id' => 456, 'status' => 'Pending']], 200)]);
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

    private function payment(HaruhayBilling $bill, array $changes = []): array
    {
        return $changes + ['submission_token' => (string) str()->uuid(), 'receipt_type' => 'payment', 'billing_id' => $bill->id, 'paid_on' => today()->toDateString(), 'amount' => 3000, 'method' => 'Cash', 'remarks' => 'PRIVATE INTERNAL STAFF NOTE'];
    }

    private function receive(HaruhayBilling $bill, array $payload): HaruhayCollection
    {
        $this->post(route('haruhay.collections.store', $bill->tenant), $payload)->assertSessionHasNoErrors();

        return HaruhayCollection::latest('id')->firstOrFail();
    }

    public function test_partial_payment_notifies_once_with_actual_amount_and_remaining_bill_balance(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        $payload = $this->payment($bill);
        $receipt = $this->receive($bill, $payload);
        $this->receive($bill, $payload);
        $this->assertDatabaseCount('haruhay_collections', 1);
        $this->assertDatabaseCount('haruhay_collection_deliveries', 2);
        $delivery = HaruhayCollectionDelivery::where('channel', 'email')->sole();
        $this->assertSame(3000.0, (float) $delivery->payload['amount']);
        $this->assertSame(3000.0, (float) $delivery->payload['applied']);
        $this->assertSame(7600.0, (float) $delivery->payload['allocations'][0]['balance']);
        $this->assertSame('Partially Paid', $delivery->payload['allocations'][0]['status']);
        $service = app(HaruhayCollectionNotificationService::class);
        $service->sendPending();
        $service->sendPending();
        Mail::assertSent(HaruhayPaymentReceived::class, fn ($mail) => $mail->hasTo('tenant@example.test') && (float) $mail->receipt['amount'] === 3000.0);
        Mail::assertSentCount(1);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['message'], 'Payment received PHP 3,000.00') && str_contains($request['message'], 'balance PHP 7,600.00'));
        $this->get(route('haruhay.collections.show', $receipt))->assertOk()->assertSee('Payment Confirmation Email')->assertSee('Submitted');
    }

    public function test_overpayment_receipt_separates_applied_amount_and_credit(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        $this->receive($bill, $this->payment($bill, ['amount' => 12000]));
        $payload = HaruhayCollectionDelivery::where('channel', 'email')->sole()->payload;
        $this->assertSame(12000.0, (float) $payload['amount']);
        $this->assertSame(10600.0, (float) $payload['applied']);
        $this->assertSame(1400.0, (float) $payload['credit']);
        $this->assertSame(0.0, (float) $payload['allocations'][0]['balance']);
        $this->assertSame('Paid', $payload['allocations'][0]['status']);
    }

    public function test_advance_notification_is_not_duplicated_when_credit_is_later_applied_to_bill(): void
    {
        $bill = $this->draft();
        $receipt = $this->receive($bill, $this->payment($bill, ['receipt_type' => 'advance', 'billing_id' => null, 'amount' => 10000]));
        $before = HaruhayCollectionDelivery::where('channel', 'email')->sole()->payload;
        $this->assertSame([], $before['allocations']);
        $this->assertSame(10000.0, (float) $before['credit']);
        $this->issue($bill);
        $this->assertDatabaseCount('haruhay_collection_deliveries', 2);
        $this->assertSame(1000000, $receipt->fresh()->applied_cents);
        $this->assertSame($before, HaruhayCollectionDelivery::where('channel', 'email')->sole()->payload);
    }

    public function test_reversed_unsent_receipt_is_blocked_and_cannot_be_retried(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        $receipt = $this->receive($bill, $this->payment($bill));
        $this->post(route('haruhay.collections.reverse', $receipt), ['reason' => 'Incorrect test payment'])->assertSessionHasNoErrors();
        app(HaruhayCollectionNotificationService::class)->sendPending();
        $this->assertSame(2, HaruhayCollectionDelivery::where('status', 'Blocked')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
        $this->post(route('haruhay.collections.notifications.retry', $receipt))->assertUnprocessable();
    }

    public function test_corrected_email_retries_only_blocked_channel_and_readonly_users_cannot_retry(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        $bill->tenant->update(['email' => null]);
        $receipt = $this->receive($bill, $this->payment($bill));
        $service = app(HaruhayCollectionNotificationService::class);
        $service->sendPending();
        $this->assertDatabaseHas('haruhay_collection_deliveries', ['channel' => 'email', 'status' => 'Blocked']);
        $bill->tenant->update(['email' => 'corrected@example.test']);
        $this->post(route('haruhay.collections.notifications.retry', $receipt))->assertSessionHasNoErrors();
        $service->sendPending();
        Mail::assertSent(HaruhayPaymentReceived::class, fn ($mail) => $mail->hasTo('corrected@example.test'));
        Http::assertSentCount(1);
        $user = auth()->user();
        $user->update(['role' => 'owner', 'is_admin' => false]);
        $this->actingAs($user->fresh())->post(route('haruhay.collections.notifications.retry', $receipt))->assertForbidden();
    }

    public function test_receipt_email_and_pdf_contain_payment_details_without_private_proof_or_internal_notes(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        $receipt = $this->receive($bill, $this->payment($bill));
        $payload = HaruhayCollectionDelivery::where('channel', 'email')->sole()->payload;
        $payload['name'] = '<script>Example</script>';
        $mail = new HaruhayPaymentReceived($payload);
        $html = $mail->render();
        $this->assertStringContainsString('3,000.00', $html);
        $this->assertStringContainsString('7,600.00', $html);
        $this->assertStringContainsString('Partially Paid', $html);
        $this->assertStringContainsString('Cash', $html);
        $this->assertStringContainsString('&lt;script&gt;Example&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>Example</script>', $html);
        $this->assertStringNotContainsString('PRIVATE INTERNAL STAFF NOTE', $html);
        $this->assertStringNotContainsString('storage/app/private', $html);
        $this->assertStringStartsWith('%PDF-', $mail->receiptPdf());
        $this->assertSame('Haruhay-Receipt-'.$receipt->code.'.pdf', $mail->attachments()[0]->as);
        $this->assertSame('application/pdf', $mail->attachments()[0]->mime);
    }

    public function test_rejected_payment_and_existing_receipts_do_not_send_anything(): void
    {
        $bill = $this->draft();
        $this->post(route('haruhay.collections.store', $bill->tenant), $this->payment($bill))->assertSessionHasErrors('billing_id', null, 'collectionForm');
        $this->assertDatabaseCount('haruhay_collection_deliveries', 0);
        $this->artisan('haruhay:send-collection-notifications')->assertSuccessful();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_verified_bank_transfer_receipt_includes_reference_but_not_private_proof(): void
    {
        $bill = $this->draft();
        $this->issue($bill);
        $receipt = $this->receive($bill, $this->payment($bill, [
            'method' => 'Bank Transfer', 'external_reference' => 'TEST-BANK-TRANSFER-001',
            'proof' => UploadedFile::fake()->image('bank-proof.png'),
        ]));
        $this->assertTrue(Storage::disk('local')->exists($receipt->proof_path));
        $payload = HaruhayCollectionDelivery::where('channel', 'email')->sole()->payload;
        $html = (new HaruhayPaymentReceived($payload))->render();
        $this->assertStringContainsString('TEST-BANK-TRANSFER-001', $html);
        $this->assertStringContainsString('Bank Transfer', $html);
        $this->assertStringNotContainsString($receipt->proof_path, $html);
        $this->assertArrayNotHasKey('proof_path', $payload);
    }
}

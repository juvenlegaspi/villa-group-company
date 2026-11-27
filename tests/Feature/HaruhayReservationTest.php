<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayCollection;
use App\Models\HaruhayProperty;
use App\Models\HaruhayReservation;
use App\Models\HaruhayUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HaruhayReservationTest extends TestCase
{
    use RefreshDatabase;

    private function room(): array
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(9, 0));
        $division = Division::firstOrCreate(['name' => 'Haruhay']);
        $dept = Department::create(['name' => 'Property Management', 'division_id' => $division->id]);
        $staff = User::create(['name' => 'Reservation Staff', 'lastname' => 'Test', 'username' => str()->uuid(), 'email' => str()->uuid().'@example.com', 'password' => 'test-password-123', 'division_id' => $division->id, 'department_id' => $dept->id, 'role' => 'admin', 'is_admin' => true, 'status' => true, 'must_change_password' => false]);
        $property = HaruhayProperty::create(['division_id' => $division->id, 'name' => 'Haruhay Space', 'owner' => 'Owner', 'type' => 'Dormitory', 'street_barangay' => 'Test Street', 'city' => 'Cebu', 'province' => 'Cebu', 'status' => 'Operational']);
        $unit = HaruhayUnit::create(['property_id' => $property->id, 'floor' => 3, 'unit_type' => 'Shared room', 'capacity' => 3, 'monthly_rent' => 3000, 'rent_basis' => 'per_person', 'room_condition' => 'Ready']);

        return [$staff, $unit];
    }

    private function payload(HaruhayUnit $unit, array $overrides = []): array
    {
        return $overrides + ['submission_token' => (string) str()->uuid(), 'tracking_token' => str()->random(64), 'room' => strtolower($unit->unit_no), 'name' => 'Primary Applicant', 'email' => 'applicant@example.com', 'phone' => '09170000001', 'occupant_count' => 2, 'other_occupants' => ['Second Occupant'], 'starts_on' => today()->addWeek()->toDateString(), 'duration_months' => 3, 'notes' => 'Viewing request', 'terms_accepted' => 1];
    }

    private function submit(array $payload): HaruhayReservation
    {
        $response = $this->postJson(route('public.haruhay.reservations.store'), $payload)->assertCreated();

        return HaruhayReservation::where('reference', $response->json('data.reference'))->firstOrFail();
    }

    private function approve(User $staff, HaruhayReservation $r): void
    {
        $this->actingAs($staff)->post(route('haruhay.reservations.approve', $r), ['deposit_required' => 0, 'monthly_rent' => 6000, 'starts_on' => $r->starts_on->toDateString(), 'payment_instructions' => 'TEST ONLY: Verify the approved recipient account with staff before paying.'])->assertSessionHasNoErrors();
    }

    private function proof(HaruhayReservation $r, string $token, float $amount = 6000): void
    {
        $data = ['submission_token' => (string) str()->uuid(), 'amount' => $amount, 'method' => 'GCash', 'reference' => 'TEST-'.str()->uuid(), 'paid_on' => today()->toDateString(), 'proof' => UploadedFile::fake()->image('transaction.png'), 'terms_accepted' => 1];
        $this->withToken($token)->postJson(route('public.haruhay.reservations.proof', $r->reference), $data)->assertOk();
    }

    private function verify(User $staff, HaruhayReservation $r, float $amount): void
    {
        $payment = $r->payments()->latest('id')->firstOrFail();
        $this->actingAs($staff)->post(route('haruhay.reservations.review', [$r, $payment]), ['decision' => 'verify', 'reason' => 'Actual transfer verified in test', 'verified_amount' => $amount])->assertSessionHasNoErrors();
    }

    public function test_monthly_request_is_idempotent_private_and_does_not_hold_capacity(): void
    {
        [$staff,$unit] = $this->room();
        HaruhayReservation::creating(function (HaruhayReservation $reservation): void {
            $this->assertLessThanOrEqual(40, strlen($reservation->reference), 'The initial reference must fit the MySQL column before the final reference is assigned.');
        });
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->submit($payload);
        $this->assertDatabaseCount('haruhay_online_reservations', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('haruhay_tracking_sms', 1);
        $this->assertDatabaseCount('haruhay_tenants', 0);
        $this->assertSame(3, $unit->fresh()->available_slots);
        $this->assertSame($r->starts_on->copy()->addMonthsNoOverflow(3)->subDay()->toDateString(), $r->ends_on->toDateString());
        $this->getJson(route('public.haruhay.reservations.show', $r->reference))->assertNotFound();
        $this->withToken(str_repeat('x', 64))->getJson(route('public.haruhay.reservations.show', $r->reference))->assertNotFound();
        $this->withToken($payload['tracking_token'])->getJson(route('public.haruhay.reservations.show', $r->reference))->assertOk()->assertJsonPath('data.status', 'PendingReview')->assertJsonMissingPath('data.tracking_token_hash')->assertJsonMissingPath('data.submission_token');
    }

    public function test_approval_holds_entire_party_for_exactly_twenty_four_hours(): void
    {
        [$staff,$unit] = $this->room();
        $r = $this->submit($this->payload($unit));
        $this->approve($staff, $r);
        $r->refresh();
        $this->assertSame('AwaitingPayment', $r->status);
        $this->assertSame(now()->addHours(24)->toDateTimeString(), $r->hold_expires_at->toDateTimeString());
        $this->assertSame('6000.00', $r->advance_required);
        $this->assertSame(2, $r->tenant->occupant_count);
        $this->assertSame('Pending Move-in', $r->tenant->status);
        $this->assertSame(1, $unit->fresh()->available_slots);
        $this->getJson(route('public.haruhay.rooms'))->assertOk()->assertJsonPath('data.0.available_slots', 1);
        $this->post(route('haruhay.tenants.confirm', $r->tenant))->assertSessionHasErrors('status', null, 'tenantForm');
        $this->get(route('haruhay.reservations.show', $r))->assertOk()->assertDontSee('@include', false)->assertSee('One month advance');
    }

    public function test_competing_approvals_cannot_overbook_a_shared_room(): void
    {
        [$staff,$unit] = $this->room();
        $first = $this->submit($this->payload($unit));
        $second = $this->submit($this->payload($unit, ['email' => 'second@example.com', 'phone' => '09170000002']));
        $this->approve($staff, $first);
        $this->post(route('haruhay.reservations.approve', $second), ['deposit_required' => 0, 'monthly_rent' => 6000, 'starts_on' => $second->starts_on->toDateString(), 'payment_instructions' => 'Test account instructions'])->assertSessionHasErrors('unit_id', null, 'tenantForm');
        $this->assertSame('PendingReview', $second->fresh()->status);
        $this->assertDatabaseCount('haruhay_tenants', 1);
    }

    public function test_verified_advance_creates_one_receipt_and_lease_then_staff_confirms_move_in(): void
    {
        [$staff,$unit] = $this->room();
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->approve($staff, $r);
        $this->proof($r, $payload['tracking_token']);
        $payment = $r->payments()->firstOrFail();
        $this->assertDatabaseCount('haruhay_collections', 0);
        $this->assertDatabaseCount('haruhay_collection_deliveries', 0);
        $this->assertTrue(Storage::disk('local')->exists($payment->proof_path));
        $this->actingAs($staff)->get(route('haruhay.reservations.proof', [$r, $payment]))->assertOk();
        $this->verify($staff, $r, 6000);
        $this->verify($staff, $r, 6000);
        $r->refresh();
        $this->assertSame('Confirmed', $r->status);
        $this->assertDatabaseCount('haruhay_collections', 1);
        $this->assertDatabaseCount('haruhay_collection_deliveries', 2);
        $this->assertDatabaseCount('haruhay_lease_contracts', 1);
        $this->assertSame(600000, HaruhayCollection::sole()->unapplied_cents);
        $this->assertSame('0.00', $r->contract->deposit_required);
        $this->assertSame('Pending Move-in', $r->tenant->status);
        $this->assertSame('Reserved', $unit->fresh()->status);
        $this->post(route('haruhay.tenants.confirm', $r->tenant))->assertSessionHasNoErrors();
        $this->assertSame('MovedIn', $r->fresh()->status);
        $this->assertSame('Occupied', $unit->fresh()->status);
        $this->assertSame(1, $unit->fresh()->available_slots);
    }

    public function test_expiry_releases_only_unpaid_holds(): void
    {
        [$staff,$unit] = $this->room();
        $r = $this->submit($this->payload($unit));
        $this->approve($staff, $r);
        $this->travel(25)->hours();
        $this->artisan('haruhay:expire-reservations')->assertSuccessful();
        $this->assertSame('Expired', $r->fresh()->status);
        $this->assertSame('Cancelled', $r->fresh()->tenant->status);
        $this->assertSame(3, $unit->fresh()->available_slots);
        $this->assertDatabaseCount('haruhay_collections', 0);
    }

    public function test_partial_payment_keeps_hold_and_balance_payment_confirms_once(): void
    {
        [$staff,$unit] = $this->room();
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->approve($staff, $r);
        $this->proof($r, $payload['tracking_token'], 3000);
        $this->verify($staff, $r, 3000);
        $this->travel(25)->hours();
        $this->artisan('haruhay:expire-reservations')->assertSuccessful();
        $this->assertSame('PartiallyPaid', $r->fresh()->status);
        $this->assertSame(1, $unit->fresh()->available_slots);
        $this->proof($r, $payload['tracking_token'], 3000);
        $this->verify($staff, $r, 3000);
        $this->assertSame('Confirmed', $r->fresh()->status);
        $this->assertDatabaseCount('haruhay_collections', 2);
        $this->assertDatabaseCount('haruhay_lease_contracts', 1);
    }

    public function test_pending_proof_does_not_expire_but_late_rejected_proof_releases_hold(): void
    {
        [$staff,$unit] = $this->room();
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->approve($staff, $r);
        $this->proof($r, $payload['tracking_token']);
        $this->travel(25)->hours();
        $this->artisan('haruhay:expire-reservations')->assertSuccessful();
        $this->assertSame('PaymentReview', $r->fresh()->status);
        $payment = $r->payments()->firstOrFail();
        $this->actingAs($staff)->post(route('haruhay.reservations.review', [$r, $payment]), ['decision' => 'reject', 'reason' => 'No actual transfer received'])->assertSessionHasNoErrors();
        $this->assertSame('Expired', $r->fresh()->status);
        $this->assertSame(3, $unit->fresh()->available_slots);
    }

    public function test_only_holder_can_cancel_an_unpaid_reservation(): void
    {
        [$staff,$unit] = $this->room();
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->approve($staff, $r);
        $this->withToken(str_repeat('x', 64))->postJson(route('public.haruhay.reservations.cancel', $r->reference), ['reason' => 'Change of plans'])->assertNotFound();
        $this->withToken($payload['tracking_token'])->postJson(route('public.haruhay.reservations.cancel', $r->reference), ['reason' => 'Change of plans'])->assertOk()->assertJsonPath('data.status', 'Cancelled');
        $this->assertSame(3, $unit->fresh()->available_slots);
    }

    public function test_proofs_are_required_and_executives_are_read_only(): void
    {
        [$staff,$unit] = $this->room();
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->approve($staff, $r);
        $this->withToken($payload['tracking_token'])->postJson(route('public.haruhay.reservations.proof', $r->reference), ['submission_token' => str()->uuid(), 'amount' => 6000, 'method' => 'GCash', 'reference' => 'test', 'paid_on' => today()->toDateString(), 'terms_accepted' => 1])->assertUnprocessable()->assertJsonValidationErrors('proof');
        $staff->update(['role' => 'owner', 'is_admin' => false]);
        $this->actingAs($staff)->get(route('haruhay.reservations.show', $r))->assertOk();
        $this->post(route('haruhay.reservations.approve', $r), [])->assertForbidden();
        $this->assertDatabaseCount('haruhay_collections', 0);
    }

    public function test_invalid_monthly_duration_and_party_size_do_not_create_records(): void
    {
        [$staff,$unit] = $this->room();
        $this->postJson(route('public.haruhay.reservations.store'), $this->payload($unit, ['duration_months' => 2]))->assertUnprocessable()->assertJsonValidationErrors('duration_months');
        $this->postJson(route('public.haruhay.reservations.store'), $this->payload($unit, ['other_occupants' => []]))->assertUnprocessable()->assertJsonValidationErrors('other_occupants');
        $this->assertDatabaseCount('haruhay_online_reservations', 0);
    }

    public function test_per_room_rates_still_hold_only_requested_occupant_slots(): void
    {
        [$staff,$unit] = $this->room();
        $unit->update(['rent_basis' => 'per_room']);
        $r = $this->submit($this->payload($unit, ['occupant_count' => 1, 'other_occupants' => []]));
        $this->approve($staff, $r);
        $this->assertSame(1, $r->fresh()->tenant->capacity_slots);
        $this->assertSame(2, $unit->fresh()->available_slots);
        $this->getJson(route('public.haruhay.rooms'))->assertOk()->assertJsonPath('data.0.reservation_scope', 'slots')->assertJsonPath('data.0.can_reserve', true);
    }

    public function test_verified_overpayment_is_credit_and_move_in_deposit_is_not_collected(): void
    {
        [$staff,$unit] = $this->room();
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->actingAs($staff)->post(route('haruhay.reservations.approve', $r), ['monthly_rent' => 6000, 'deposit_required' => 6000, 'starts_on' => $r->starts_on->toDateString(), 'payment_instructions' => 'TEST ONLY approved account details'])->assertSessionHasNoErrors();
        $this->proof($r, $payload['tracking_token'], 6500);
        $this->verify($staff, $r, 6500);
        $this->assertSame('Confirmed', $r->fresh()->status);
        $this->assertSame('6000.00', $r->fresh()->advance_required);
        $this->assertSame('6000.00', $r->fresh()->contract->deposit_required);
        $this->assertSame(650000, HaruhayCollection::sole()->unapplied_cents);
        $this->assertDatabaseCount('haruhay_deposit_entries', 0);
    }

    public function test_confirmed_advance_is_applied_to_first_bill_without_another_collection(): void
    {
        [$staff,$unit] = $this->room();
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->approve($staff, $r);
        $this->proof($r, $payload['tracking_token']);
        $this->verify($staff, $r, 6000);
        $r->refresh();
        $this->travelTo($r->starts_on->copy()->addMonthNoOverflow()->setTime(10, 0));
        $this->actingAs($staff)->post(route('haruhay.billing.store'), ['contract_id' => $r->contract_id, 'period_start' => $r->starts_on->toDateString(), 'issued_on' => today()->toDateString(), 'water' => 100, 'electric' => 200, 'utilities_other' => 0, 'parking' => 0, 'addons_other' => 0, 'penalty' => 0, 'discount' => 0, 'utilities_ready' => 1])->assertSessionHasNoErrors();
        $bill = \App\Models\HaruhayBilling::sole();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        $this->assertSame(30000, $bill->fresh()->balance_cents);
        $this->assertSame('Partially Paid', $bill->fresh()->payment_status);
        $this->assertDatabaseCount('haruhay_collections', 1);
    }

    public function test_cash_advance_is_idempotent_and_receipt_reversal_cannot_desynchronize_booking(): void
    {
        [$staff,$unit] = $this->room();
        $r = $this->submit($this->payload($unit));
        $this->approve($staff, $r);
        $cash = ['submission_token' => (string) str()->uuid(), 'amount' => 6000, 'paid_on' => today()->toDateString(), 'reason' => 'Actual cash received'];
        $this->post(route('haruhay.reservations.cash', $r), $cash)->assertSessionHasNoErrors();
        $this->post(route('haruhay.reservations.cash', $r), $cash)->assertSessionHasNoErrors();
        $this->assertSame('Confirmed', $r->fresh()->status);
        $this->assertDatabaseCount('haruhay_collections', 1);
        $this->post(route('haruhay.collections.reverse', HaruhayCollection::sole()), ['reason' => 'Booking correction requested'])->assertSessionHasErrors('receipt', null, 'collectionForm');
        $this->assertSame('Confirmed', $r->fresh()->status);
    }

    public function test_access_reset_invalidates_old_key_and_records_audited_update(): void
    {
        [$staff,$unit] = $this->room();
        $payload = $this->payload($unit);
        $r = $this->submit($payload);
        $this->actingAs($staff)->from(route('haruhay.reservations.show', $r))->post(route('haruhay.reservations.reset-access', $r), ['identity_verified' => 1, 'reason' => 'Verified applicant identity through approved channel'])->assertSessionHasNoErrors();
        $token = session('reservation_access_code.token');
        $this->assertSame(64, strlen($token));
        $this->withToken($payload['tracking_token'])->getJson(route('public.haruhay.reservations.show', $r->reference))->assertNotFound();
        $this->withToken($token)->getJson(route('public.haruhay.reservations.show', $r->reference))->assertOk();
        $this->post(route('haruhay.reservations.message', $r), ['message' => 'Your room viewing can be coordinated with staff.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('haruhay_reservation_events', ['reservation_id' => $r->id, 'action' => 'staff_update']);
    }

    public function test_live_reservation_feed_updates_directory_and_marks_only_the_viewing_users_alert_read(): void
    {
        [$staff, $unit] = $this->room();
        $second = $staff->replicate(['username', 'email']);
        $second->fill(['username' => (string) str()->uuid(), 'email' => str()->uuid().'@example.com'])->save();
        $payload = $this->payload($unit, ['name' => '<script>Applicant</script>']);
        $reservation = $this->submit($payload);
        $this->assertDatabaseCount('notifications', 2);
        $response = $this->actingAs($staff)->getJson(route('haruhay.reservations.live', ['directory' => 1, 'since' => 0]));
        $response->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('notification_count', 1)->assertJsonPath('total', 1)->assertJsonPath('items.0.reference', $reservation->reference);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $html = $response->json('directory_html');
        $this->assertStringContainsString('&lt;script&gt;Applicant&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>Applicant</script>', $html);
        $this->assertStringNotContainsString($payload['tracking_token'], $response->getContent());
        $this->assertStringNotContainsString($payload['email'], $response->getContent());
        $this->getJson(route('haruhay.reservations.live', ['directory' => 1, 'since' => $reservation->id, 'version' => $response->json('version')]))->assertOk()->assertJsonMissingPath('directory_html')->assertJsonCount(0, 'items');
        $reservation->update(['status' => 'AwaitingPayment']);
        $changed = $this->getJson(route('haruhay.reservations.live', ['directory' => 1, 'version' => $response->json('version')]))->assertOk()->assertJsonPath('stats.AwaitingPayment', 1);
        $this->assertNotSame($response->json('version'), $changed->json('version'));
        $unit->property->update(['name' => 'Updated Haruhay Property']);
        $propertyChanged = $this->getJson(route('haruhay.reservations.live', ['directory' => 1, 'version' => $changed->json('version')]))->assertOk();
        $this->assertStringContainsString('Updated Haruhay Property', $propertyChanged->json('directory_html'));
        $this->get(route('haruhay.reservations.show', $reservation))->assertOk();
        $this->getJson(route('haruhay.reservations.live'))->assertOk()->assertJsonPath('unread_count', 0);
        $this->actingAs($second)->getJson(route('haruhay.reservations.live'))->assertOk()->assertJsonPath('unread_count', 1);
    }

    public function test_live_directory_preserves_filters_and_pagination_links_point_to_the_page_not_the_feed(): void
    {
        [$staff, $unit] = $this->room();
        for ($i = 0; $i < 11; $i++) {
            $this->submit($this->payload($unit, ['name' => 'Applicant '.$i]));
        }
        $response = $this->actingAs($staff)->getJson(route('haruhay.reservations.live', ['directory' => 1, 'page' => 2, 'status' => 'PendingReview', 'since' => 0]));
        $response->assertOk()->assertJsonPath('total', 11);
        $html = $response->json('directory_html');
        $this->assertStringContainsString('Applicant 0', $html);
        $this->assertStringNotContainsString('/reservations/live', $html);
        $this->assertStringContainsString('page=1', $html);
        $this->getJson(route('haruhay.reservations.live', ['directory' => 1, 'search' => 'Applicant 7']))->assertOk()->assertJsonPath('total', 1);
    }

    public function test_foreign_company_users_do_not_receive_reservation_alerts_or_access_the_feed(): void
    {
        [$staff, $unit] = $this->room();
        $foreignDivision = Division::create(['name' => 'Other Company']);
        $foreign = $staff->replicate(['username', 'email']);
        $foreign->fill(['username' => (string) str()->uuid(), 'email' => str()->uuid().'@example.com', 'division_id' => $foreignDivision->id, 'role' => 'employee', 'is_admin' => false])->save();
        $this->submit($this->payload($unit));
        $this->assertSame(0, $foreign->notifications()->count());
        $this->actingAs($foreign)->getJson(route('haruhay.reservations.live'))->assertForbidden();
    }

    private function fakeTrackingSms(): void
    {
        config(['services.semaphore.enabled' => true, 'services.semaphore.api_key' => 'test-only', 'services.semaphore.endpoint' => 'https://sms.example.test/messages', 'haruhay_notifications.tracking_sms_enabled' => true]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['https://sms.example.test/messages' => \Illuminate\Support\Facades\Http::response([['message_id' => 123, 'status' => 'Pending']])]);
    }

    public function test_tracking_sms_is_encrypted_sent_once_and_erased_after_sending(): void
    {
        [$staff, $unit] = $this->room();
        $this->fakeTrackingSms();
        $payload = $this->payload($unit);
        $reservation = $this->submit($payload);
        $this->submit($payload);
        $delivery = \App\Models\HaruhayTrackingSms::sole();
        $ciphertext = \Illuminate\Support\Facades\DB::table('haruhay_tracking_sms')->value('payload');
        $this->assertStringNotContainsString($payload['tracking_token'], $ciphertext);
        $this->assertSame($payload['tracking_token'], $delivery->payload['code']);
        $this->assertArrayNotHasKey('payload', $delivery->toArray());
        $service = app(\App\Services\HaruhayTrackingSmsService::class);
        $service->sendPending();
        $service->sendPending();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request['message'], $reservation->reference) && str_contains($request['message'], $payload['tracking_token']) && str_contains($request['message'], 'not yet confirmed'));
        $this->assertNull($delivery->fresh()->payload);
        $response = $this->withToken($payload['tracking_token'])->getJson(route('public.haruhay.reservations.show', $reservation->reference));
        $response->assertOk()->assertJsonPath('data.tracking_sms_status', 'Submitted');
        $this->assertStringNotContainsString($payload['tracking_token'], $response->getContent());
    }

    public function test_failed_tracking_sms_preserves_reservation_and_does_not_repeat_or_keep_code(): void
    {
        [$staff, $unit] = $this->room();
        $this->fakeTrackingSms();
        $payload = $this->payload($unit);
        $reservation = $this->submit($payload);
        \Illuminate\Support\Facades\Http::fake(['https://sms.example.test/messages' => \Illuminate\Support\Facades\Http::failedConnection()]);
        $service = app(\App\Services\HaruhayTrackingSmsService::class);
        $service->sendPending();
        $service->sendPending();
        $this->assertSame('PendingReview', $reservation->fresh()->status);
        $delivery = \App\Models\HaruhayTrackingSms::sole();
        $this->assertSame('NeedsReview', $delivery->status);
        $this->assertNull($delivery->payload);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        $this->withToken($payload['tracking_token'])->getJson(route('public.haruhay.reservations.show', $reservation->reference))->assertOk();
    }

    public function test_reset_access_code_cancels_unsent_old_tracking_text(): void
    {
        [$staff, $unit] = $this->room();
        $this->fakeTrackingSms();
        $reservation = $this->submit($this->payload($unit));
        $reservation->update(['tracking_token_hash' => hash('sha256', str()->random(64))]);
        app(\App\Services\HaruhayTrackingSmsService::class)->sendPending();
        $this->assertDatabaseHas('haruhay_tracking_sms', ['status' => 'Blocked']);
        $this->assertNull(\App\Models\HaruhayTrackingSms::sole()->payload);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_tracking_text_expires_when_unsent_without_expiring_the_reservation(): void
    {
        [$staff, $unit] = $this->room();
        $this->fakeTrackingSms();
        $reservation = $this->submit($this->payload($unit));
        $this->travel(1)->days();
        app(\App\Services\HaruhayTrackingSmsService::class)->sendPending();
        $this->assertDatabaseHas('haruhay_tracking_sms', ['status' => 'Expired']);
        $this->assertNull(\App\Models\HaruhayTrackingSms::sole()->payload);
        $this->assertSame('PendingReview', $reservation->fresh()->status);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_reservation_requires_a_valid_mobile_for_tracking_texts(): void
    {
        [$staff, $unit] = $this->room();
        $this->postJson(route('public.haruhay.reservations.store'), $this->payload($unit, ['phone' => '1234567']))->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('haruhay_online_reservations', 0);
        $this->assertDatabaseCount('haruhay_tracking_sms', 0);
    }
    public function test_public_request_rejects_a_room_from_another_selected_property(): void
    {
        [, $unit] = $this->room();
        $this->postJson(route('public.haruhay.reservations.store'), $this->payload($unit, ['property_id' => $unit->property_id + 100]))
            ->assertUnprocessable()->assertJsonValidationErrors('property_id');
        $this->assertSame(0, HaruhayReservation::count());
    }

    public function test_public_request_accepts_matching_property_and_preserves_room_link(): void
    {
        [, $unit] = $this->room();
        $reservation = $this->submit($this->payload($unit, ['property_id' => $unit->property_id]));
        $this->assertSame($unit->id, $reservation->unit_id);
        $this->assertSame($unit->property_id, $reservation->unit->property_id);
    }

}

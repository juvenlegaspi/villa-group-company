<?php

namespace App\Services;

use App\Models\HaruhayCollection;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayReservation;
use App\Models\HaruhayReservationPayment;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class HaruhayReservationService
{
    public function __construct(private HaruhayTenantService $tenants, private HaruhayCollectionService $collections) {}

    public function request(array $data): HaruhayReservation
    {
        return DB::transaction(function () use ($data) {
            $secret = $data['tracking_token'];
            unset($data['tracking_token'], $data['terms_accepted'], $data['website']);
            $fingerprint = hash('sha256', json_encode($data));
            $existing = HaruhayReservation::where('submission_token', $data['submission_token'])->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->tracking_token_hash, hash('sha256', $secret)) || ! hash_equals($existing->submission_fingerprint, $fingerprint)) {
                    $this->invalid('submission_token', 'This request was already submitted with different details. Start a new request.');
                }

                return $existing;
            }
            $unit = $this->room($data['room']);
            $requiredSlots = (int) $data['occupant_count'];
            if ($data['occupant_count'] > ($unit->capacity ?? 1) || $unit->room_condition !== 'Ready' || ! in_array($unit->property->status, ['Active', 'Operational'], true) || $unit->available_slots < $requiredSlots) {
                $this->invalid('room', 'The room does not currently have enough ready slots. Choose another room or contact the team.');
            }
            if (count($data['other_occupants']) !== (int) $data['occupant_count'] - 1) {
                $this->invalid('other_occupants', 'Enter one full name for each additional occupant.');
            }
            unset($data['room']);
            $data['requested_starts_on'] = $data['starts_on'];
            $data['ends_on'] = Carbon::parse($data['starts_on'])->addMonthsNoOverflow((int) $data['duration_months'])->subDay()->toDateString();
            $reservation = HaruhayReservation::create($data + ['reference' => (string) str()->uuid(), 'unit_id' => $unit->id, 'status' => 'PendingReview', 'tracking_token_hash' => hash('sha256', $secret), 'submission_fingerprint' => $fingerprint, 'terms_version' => '2026-11-12', 'terms_accepted_at' => now()]);
            $reservation->update(['reference' => 'RSV-'.now()->format('Ymd').'-'.str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT)]);
            $this->event($reservation, 'requested', 'Monthly reservation requested. No room is held until staff approval.');
            app(HaruhayReservationAlertService::class)->notify($reservation);
            app(HaruhayTrackingSmsService::class)->enqueue($reservation, $secret);

            return $reservation;
        }, 3);
    }

    public function room(string $slug): HaruhayUnit
    {
        return HaruhayUnit::whereRaw('LOWER(unit_no) = ?', [strtolower($slug)])
            ->whereHas('property', fn ($q) => $q->whereIn('division_id', \App\Models\Division::whereRaw('LOWER(name) = ?', ['haruhay'])->select('id')))->with('property')->firstOrFail();
    }

    public function approve(HaruhayReservation $reservation, array $data): HaruhayReservation
    {
        return DB::transaction(function () use ($reservation, $data) {
            $r = HaruhayReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'PendingReview') {
                $this->invalid('status', 'Only a pending request can be approved. Refresh this page.');
            }
            $room = $r->unit;
            if (! in_array($room->property->status, ['Active', 'Operational'], true)) {
                $this->invalid('room', 'The property is not operational.');
            }
            if (HaruhayTenant::whereIn('status', HaruhayTenant::HELD_STATUSES)->whereHas('unit.property', fn ($q) => $q->where('division_id', $room->property->division_id))->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [strtolower($r->email)])->orWhere('contact_number', $r->phone))->exists()) {
                $this->invalid('tenant', 'An active or pending tenant already uses this contact. Resolve duplicate requests or use the existing tenant workflow before approving.');
            }
            $tenant = $this->tenants->save([
                'unit_id' => $r->unit_id, 'name' => $r->name, 'contact_number' => $r->phone, 'email' => $r->email,
                'status' => 'Pending Move-in', 'move_in_date' => $data['starts_on'], 'occupant_count' => $r->occupant_count,
                'capacity_slots' => $r->occupant_count,
                'remarks' => 'Online reservation '.$r->reference.($r->other_occupants ? "\nOther occupants: ".implode(', ', $r->other_occupants) : ''),
            ], (int) $room->property->division_id);
            $r->update([
                'tenant_id' => $tenant->id, 'status' => 'AwaitingPayment', 'starts_on' => $data['starts_on'],
                'ends_on' => Carbon::parse($data['starts_on'])->addMonthsNoOverflow($r->duration_months)->subDay(),
                'monthly_rent' => $data['monthly_rent'], 'advance_required' => $data['monthly_rent'],
                'deposit_required' => $data['deposit_required'],
                'hold_expires_at' => now()->addHours(24), 'payment_instructions' => $data['payment_instructions'],
                'approved_at' => now(), 'approved_by' => auth()->id(),
            ]);
            $this->event($r, 'approved', 'Approved with a 24-hour hold. One month advance is required; upload proof for verification.');

            return $r;
        }, 3);
    }

    public function close(HaruhayReservation $reservation, string $status, string $reason): HaruhayReservation
    {
        return DB::transaction(function () use ($reservation, $status, $reason) {
            $r = HaruhayReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (in_array($r->status, ['Declined', 'Cancelled', 'Expired'], true)) {
                return $r;
            }
            if ((float) $r->advance_received > 0 || ! in_array($r->status, ['PendingReview', 'AwaitingPayment'], true)) {
                $this->invalid('status', 'A paid reservation requires staff and finance assistance. Payments are preserved; no automatic refund or cancellation is performed.');
            }
            if ($status === 'Expired' && ($r->status !== 'AwaitingPayment' || ! $r->hold_expires_at?->isPast())) {
                return $r;
            }
            if ($r->tenant) {
                if ($r->tenant->status !== 'Pending Move-in') {
                    $this->invalid('status', 'This tenant is no longer awaiting move-in. Resolve the stay with staff.');
                }
                $this->tenants->save([], (int) $r->unit->property->division_id, $r->tenant, 'cancel', false, true);
            }
            $r->update(['status' => $status, 'decision_reason' => $reason]);
            $r->payments()->where('status', 'Pending')->update(['status' => 'Rejected', 'review_reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => auth()->id()]);
            $this->event($r, strtolower($status), $reason);

            return $r;
        }, 3);
    }

    public function submitProof(HaruhayReservation $reservation, array $data, bool $cash = false): HaruhayReservationPayment
    {
        $path = null;
        try {
            $payment = DB::transaction(function () use ($reservation, $data, $cash, &$path) {
                $r = HaruhayReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                $existing = HaruhayReservationPayment::where('submission_token', $data['submission_token'])->first();
                if ($existing) {
                    if ((int) $existing->reservation_id !== (int) $r->id || (float) $existing->amount !== (float) $data['amount'] || $existing->method !== $data['method'] || $existing->reference !== ($data['reference'] ?? null)) {
                        $this->invalid('submission_token', 'This payment submission was already used.');
                    }

                    return $existing;
                }
                if (! in_array($r->status, ['AwaitingPayment', 'PartiallyPaid'], true) || ! $r->tenant || $r->tenant->status !== 'Pending Move-in') {
                    $this->invalid('status', 'This reservation is not accepting another payment proof.');
                }
                if ((float) $r->advance_received === 0.0 && $r->hold_expires_at->lte(now())) {
                    $this->invalid('status', 'The unpaid hold has expired. Submit a new request.');
                }
                if (! $cash && $r->payments()->count() >= 10) {
                    $this->invalid('proof', 'Contact staff to review your payment submissions.');
                }
                if (! $cash) {
                    $path = $data['proof']->store('haruhay/reservation-proofs/'.$r->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('Unable to save payment proof.');
                    }
                }
                $payment = $r->payments()->create(collect($data)->except(['proof', 'terms_accepted'])->all() + ['proof_path' => $path, 'proof_name' => $cash ? null : $data['proof']->getClientOriginalName()]);
                $r->update(['status' => 'PaymentReview', 'quote_accepted_at' => now()]);
                $this->event($r, 'proof_submitted', $cash ? 'Cash advance submitted for staff verification.' : 'Payment proof submitted. Staff verification is pending; this is not yet a receipt.');

                return $payment;
            }, 3);
            if ($path && $payment->proof_path !== $path) {
                Storage::disk('local')->delete($path);
            }

            return $payment;
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    public function review(HaruhayReservationPayment $payment, bool $accept, string $reason, ?float $amount = null): HaruhayReservation
    {
        return DB::transaction(function () use ($payment, $accept, $reason, $amount) {
            $r = HaruhayReservation::whereKey($payment->reservation_id)->lockForUpdate()->firstOrFail();
            $payment = HaruhayReservationPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'Pending') {
                return $r;
            }
            if ($r->status !== 'PaymentReview' || ! $r->tenant || $r->tenant->status !== 'Pending Move-in') {
                $this->invalid('status', 'This payment cannot be verified for the current reservation status.');
            }
            if (! $accept) {
                $payment->update(['status' => 'Rejected', 'review_reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => auth()->id()]);
                $r->update(['status' => (float) $r->advance_received > 0 ? 'PartiallyPaid' : 'AwaitingPayment']);
                $this->event($r, 'proof_rejected', 'Payment proof was not accepted: '.$reason);
                if ((float) $r->advance_received === 0.0 && $r->hold_expires_at->isPast()) {
                    return $this->close($r, 'Expired', 'The unpaid hold expired after payment proof was rejected.');
                }

                return $r;
            }
            if (! $amount || $amount <= 0 || (float) $r->advance_received + $amount > 9999999999.99) {
                $this->invalid('verified_amount', 'Enter the actual positive amount received within the supported receipt limit.');
            }
            if ($payment->reference && (HaruhayReservationPayment::where('method', $payment->method)->where('verified_reference', $payment->reference)->exists() || HaruhayCollection::active()->where('method', $payment->method)->where('external_reference', $payment->reference)->exists())) {
                $this->invalid('reference', 'This transaction reference has already been verified or recorded.');
            }
            $receipt = $this->collections->receive($r->tenant, [
                'submission_token' => (string) str()->uuid(), 'receipt_type' => 'advance', 'amount' => $amount, 'paid_on' => $payment->paid_on->toDateString(),
                'method' => $payment->method, 'external_reference' => $payment->reference, 'remarks' => 'Verified online reservation '.$r->reference,
            ], (int) $r->unit->property->division_id, true);
            if ($payment->proof_path) {
                $receipt->update(['proof_path' => $payment->proof_path, 'proof_name' => $payment->proof_name]);
            }
            $payment->update(['status' => 'Verified', 'verified_amount' => $amount, 'verified_reference' => $payment->reference, 'collection_id' => $receipt->id, 'review_reason' => $reason, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            $r->advance_received = ((int) round((float) $r->advance_received * 100) + (int) round($amount * 100)) / 100;
            $r->status = $r->remaining_cents === 0 ? 'Confirmed' : 'PartiallyPaid';
            if ($r->status === 'Confirmed') {
                $contract = $this->contract($r);
                $r->contract_id = $contract->id;
                $r->confirmed_at = now();
            }
            $r->save();
            $this->event($r, 'payment_verified', $r->status === 'Confirmed' ? 'One month advance verified. Reservation confirmed; staff will coordinate the signed lease and actual move-in.' : 'Partial advance verified. The slot remains held while staff assists with the remaining balance.');

            return $r;
        }, 3);
    }

    private function contract(HaruhayReservation $r): HaruhayLeaseContract
    {
        $tenant = HaruhayTenant::whereKey($r->tenant_id)->lockForUpdate()->firstOrFail();
        $room = HaruhayUnit::whereKey($tenant->unit_id)->lockForUpdate()->firstOrFail();
        if ($tenant->contracts()->exists()) {
            $this->invalid('contract', 'This tenant already has a contract. Resolve it before confirming the online reservation.');
        }
        $billing = $r->starts_on->copy()->addMonthNoOverflow();
        $contract = $tenant->contracts()->create([
            'code' => 'pending-'.str()->uuid(), 'unit_id' => $room->id, 'tenant_name' => $tenant->name, 'property_name' => $room->property->name, 'room_no' => $room->unit_no, 'floor' => $room->floor,
            'starts_on' => $r->starts_on, 'ends_on' => $r->ends_on, 'monthly_rent' => $r->monthly_rent, 'billing_starts_on' => $billing,
            'first_due_on' => $billing, 'due_day' => $billing->day, 'billing_cycle' => 'lease_anniversary', 'first_month_policy' => 'full_month', 'first_month_charge' => $r->monthly_rent,
            'deposit_required' => $r->deposit_required ?? 0, 'advance_required' => $r->advance_required, 'advance_application' => 'Apply to first billing month',
            'remarks' => 'Online reservation '.$r->reference.'. One month advance verified. Signed lease document must be completed with staff.', 'created_by' => auth()->id(),
        ]);
        $contract->update(['code' => 'CTR-'.str_pad((string) $contract->id, 4, '0', STR_PAD_LEFT)]);

        return $contract;
    }

    public function expire(): int
    {
        $count = 0;
        HaruhayReservation::where('status', 'AwaitingPayment')->where('advance_received', 0)->where('hold_expires_at', '<=', now())->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                if ($this->close($row, 'Expired', 'The 24-hour hold expired without a verified payment. Room capacity has been released.')->status === 'Expired') {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function event(HaruhayReservation $r, string $action, string $message): void
    {
        $r->events()->create(['actor_id' => auth()->id(), 'action' => $action, 'status' => $r->status, 'message' => $message, 'created_at' => now()]);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

<?php

namespace App\Services;

use App\Models\{HaruhayBilling, HaruhayCollection, HaruhayTenant};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class HaruhayCollectionService
{
    public function receive(HaruhayTenant $tenant, array $data, int $divisionId, bool $reservationPayment = false): HaruhayCollection
    {
        $proofPath = null;
        try {
            $receipt = DB::transaction(function () use ($tenant, $data, $divisionId, &$proofPath, $reservationPayment) {
                $person = HaruhayTenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                if (! $reservationPayment && \App\Models\HaruhayReservation::where('tenant_id', $person->id)->whereIn('status', ['AwaitingPayment', 'PaymentReview', 'PartiallyPaid'])->exists()) $this->invalid('amount', 'Record and verify this advance through Online Reservations so its confirmation status stays synchronized.');
                abort_unless((int) $person->unit->property->division_id === $divisionId, 404);
                if (($data['receipt_type'] ?? 'payment') === 'payment' && empty($data['billing_id'])) $this->invalid('billing_id', 'Issue the current bill before recording a bill payment. Use Receive Advance for early payments.');
                if (($data['receipt_type'] ?? 'payment') === 'advance' && !empty($data['billing_id'])) $this->invalid('billing_id', 'An advance is held as credit and cannot be assigned to a bill at receipt.');
                $existing = HaruhayCollection::where('submission_token', $data['submission_token'])->first();
                if ($existing) {
                    if ((int) $existing->tenant_id !== $person->id || $existing->amount !== number_format((float) $data['amount'], 2, '.', '') || $existing->method !== $data['method'] || $existing->paid_on->toDateString() !== $data['paid_on'] || (int) $existing->initial_billing_id !== (int) ($data['billing_id'] ?? 0) || $existing->external_reference !== ($data['external_reference'] ?? null)) $this->invalid('amount', 'This submission was already used for a different receipt. Refresh before recording another payment.');
                    return $existing;
                }
                if (!empty($data['external_reference']) && HaruhayCollection::active()->where('method', $data['method'])->where('external_reference', $data['external_reference'])->exists()) $this->invalid('external_reference', 'This payment reference has already been recorded.');
                if (($data['receipt_type'] ?? 'payment') === 'advance' && $this->hasUnpaidIssuedBills($person)) {
                    $this->invalid('receipt_type', 'Pay the outstanding issued bill using Record Payment before receiving an advance. Any excess payment will remain as tenant credit.');
                }
                $bill = !empty($data['billing_id']) ? $this->currentBill($person, (int) $data['billing_id'], $divisionId) : null;
                if ($bill && $data['paid_on'] < $bill->issued_on->toDateString()) $this->invalid('paid_on', 'The payment date cannot precede the invoice issue date. Record earlier receipts as unapplied credit.');
                if (!empty($data['proof']) && !$proofPath) {
                    $proofPath = $data['proof']->store('haruhay/collection-proofs/'.$person->id, 'local');
                    if (!$proofPath) throw new \RuntimeException('Unable to save the payment proof.');
                }
                $receipt = HaruhayCollection::create([
                    'code' => 'pending-'.str()->uuid(), 'submission_token' => $data['submission_token'], 'tenant_id' => $person->id,
                    'initial_billing_id' => $bill?->id, 'tenant_name' => $person->name, 'property_id' => $bill ? $bill->unit->property_id : $person->unit->property_id,
                    'property_name' => $bill?->property_name ?? $person->unit->property->name, 'room_no' => $bill?->room_no ?? $person->unit->unit_no,
                    'paid_on' => $data['paid_on'], 'amount' => $data['amount'], 'amount_due' => $bill ? $bill->balance_cents / 100 : null,
                    'method' => $data['method'], 'external_reference' => $data['external_reference'] ?? null, 'remarks' => $data['remarks'] ?? null, 'created_by' => auth()->id(),
                    'active_reference' => $data['external_reference'] ?? null,
                    'proof_path' => $proofPath, 'proof_name' => !empty($data['proof']) ? $data['proof']->getClientOriginalName() : null,
                ]);
                $receipt->update(['code' => 'OR-'.str_pad((string) $receipt->id, 5, '0', STR_PAD_LEFT)]);
                if ($bill) $this->allocate($receipt, $bill);
                app(HaruhayCollectionNotificationService::class)->enqueue($receipt);
                return $receipt;
            }, 3);
            if ($proofPath && $receipt->proof_path !== $proofPath) Storage::disk('local')->delete($proofPath);
            return $receipt;
        } catch (\Throwable $e) {
            if ($proofPath) Storage::disk('local')->delete($proofPath);
            throw $e;
        }
    }

    public function hasUnpaidIssuedBills(HaruhayTenant $tenant): bool
    {
        return $tenant->billings()->whereNotNull('finalized_at')->withSum('payments', 'amount')->get()
            ->contains(fn (HaruhayBilling $bill) => $bill->balance_cents > 0);
    }

    public function applyCredit(HaruhayCollection $receipt, int $billId, int $divisionId): void
    {
        DB::transaction(function () use ($receipt, $billId, $divisionId) {
            $person = HaruhayTenant::whereKey($receipt->tenant_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $person->unit->property->division_id === $divisionId, 404);
            $receipt = HaruhayCollection::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->unapplied_cents <= 0) $this->invalid('billing_id', 'This receipt has no unapplied credit remaining.');
            $bill = $this->currentBill($person, $billId, $divisionId);
            $this->allocate($receipt, $bill);
        }, 3);
    }

    public function applyAvailableCredit(HaruhayTenant $tenant, int $billId, int $divisionId): void
    {
        DB::transaction(function () use ($tenant, $billId, $divisionId) {
            $person = HaruhayTenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $person->unit->property->division_id === $divisionId, 404);
            $receipts = $person->collections()->active()->orderBy('paid_on')->orderBy('id')->lockForUpdate()->get();
            $bill = $person->billings()->whereKey($billId)->lockForUpdate()->firstOrFail();
            if (!$bill->finalized_at || $bill->balance_cents === 0) return;
            $bill = $this->currentBill($person, $billId, $divisionId);
            foreach ($receipts as $receipt) {
                if ($receipt->unapplied_cents === 0) continue;
                $this->allocate($receipt, $bill);
                $bill->refresh();
                if ($bill->balance_cents === 0) break;
            }
        }, 3);
    }

    private function currentBill(HaruhayTenant $tenant, int $billId, int $divisionId): HaruhayBilling
    {
        $bill = $tenant->billings()->whereKey($billId)->lockForUpdate()->first();
        if (!$bill || !$bill->finalized_at || $bill->balance_cents <= 0) $this->invalid('billing_id', 'Select this tenant\'s issued, unpaid current bill.');
        if (app(HaruhayBillingWorkflow::class)->currentKey($tenant, $divisionId) !== $bill->contract_id.':'.$bill->period_start->toDateString()) $this->invalid('billing_id', 'Pay the current billing month before the next bill.');
        return $bill;
    }

    public function reverse(HaruhayCollection $receipt, string $reason, int $divisionId): void
    {
        if (\App\Models\HaruhayReservationPayment::where('collection_id', $receipt->id)->where('status', 'Verified')->exists()) {
            $this->invalid('receipt', 'This receipt confirms an online reservation. Coordinate the reservation and financial correction with staff; it cannot be reversed independently while leaving the booking confirmed.');
        }
        DB::transaction(function () use ($receipt, $reason, $divisionId) {
            $person = HaruhayTenant::whereKey($receipt->tenant_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $person->unit->property->division_id === $divisionId, 404);
            $receipt = HaruhayCollection::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->voided_at) $this->invalid('receipt', 'This receipt has already been reversed.');
            $allocations = $receipt->allocations()->orderBy('billing_id')->lockForUpdate()->get();
            HaruhayBilling::whereIn('id', $allocations->pluck('billing_id'))->orderBy('id')->lockForUpdate()->get();
            $audit = ['voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => $reason];
            foreach ($allocations as $allocation) $allocation->update($audit);
            $receipt->update($audit + ['active_reference' => null]);
        }, 3);
    }

    private function allocate(HaruhayCollection $receipt, HaruhayBilling $bill): void
    {
        $cents = min($receipt->unapplied_cents, $bill->balance_cents);
        if ($cents <= 0) $this->invalid('amount', 'There is no available amount to apply.');
        $bill->payments()->create(['collection_id' => $receipt->id, 'amount' => number_format($cents / 100, 2, '.', ''), 'paid_on' => $receipt->paid_on, 'reference' => $receipt->code.' / '.$bill->code, 'created_by' => auth()->id()]);
    }
    private function invalid(string $field, string $message): never { throw ValidationException::withMessages([$field => $message])->errorBag('collectionForm'); }
}

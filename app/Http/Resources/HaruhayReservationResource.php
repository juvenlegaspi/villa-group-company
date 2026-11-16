<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HaruhayReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference, 'status' => $this->status,
            'tracking_sms_status' => $this->trackingSms?->status,
            'room' => $this->unit->unit_no, 'room_type' => $this->unit->unit_type, 'property' => $this->unit->property->name,
            'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone, 'occupant_count' => $this->occupant_count,
            'other_occupants' => $this->other_occupants, 'requested_starts_on' => $this->requested_starts_on->toDateString(),
            'starts_on' => $this->starts_on->toDateString(), 'ends_on' => $this->ends_on->toDateString(), 'duration_months' => $this->duration_months,
            'monthly_rent' => $this->monthly_rent, 'advance_required' => $this->advance_required, 'advance_received' => $this->advance_received,
            'deposit_required' => $this->deposit_required === null ? null : number_format((float) $this->deposit_required, 2, '.', ''),
            'remaining' => number_format($this->remaining_cents / 100, 2, '.', ''), 'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
            'extra_received' => number_format(max(0, (float) $this->advance_received - (float) $this->advance_required), 2, '.', ''),
            'payment_instructions' => $this->payment_instructions, 'decision_reason' => $this->decision_reason,
            'can_upload' => in_array($this->status, ['AwaitingPayment', 'PartiallyPaid'], true) && ((float) $this->advance_received > 0 || $this->hold_expires_at?->isFuture()),
            'can_cancel' => in_array($this->status, ['PendingReview', 'AwaitingPayment'], true) && (float) $this->advance_received === 0.0,
            'contract_reference' => $this->contract?->code,
            'actual_move_in_on' => $this->tenant?->move_in_date?->toDateString(),
            'payments' => $this->payments->map(fn ($p) => ['id' => $p->id, 'amount' => $p->amount, 'verified_amount' => $p->verified_amount, 'method' => $p->method, 'reference' => $p->reference, 'paid_on' => $p->paid_on->toDateString(), 'status' => $p->status, 'review_reason' => $p->review_reason, 'receipt' => $p->collection?->code])->values(),
            'timeline' => $this->events->map(fn ($e) => ['status' => $e->status, 'message' => $e->message, 'at' => $e->created_at->toIso8601String()])->values(),
        ];
    }
}

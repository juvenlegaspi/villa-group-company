<?php

namespace App\Http\Controllers;

use App\Http\Resources\HaruhayReservationResource;
use App\Models\HaruhayReservation;
use App\Services\HaruhayReservationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HaruhayPublicReservationController extends Controller
{
    public function store(Request $request, HaruhayReservationService $service)
    {
        $data = $request->validate([
            'submission_token' => 'required|uuid', 'tracking_token' => 'required|string|size:64|alpha_num:ascii',
            'room' => 'required|string|regex:/^dr-\d+$/', 'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255', 'phone' => ['required', 'string', 'max:50', function ($attribute, $value, $fail) {
                if (! app(\App\Services\SemaphoreSmsService::class)->normalizePhilippineNumber($value)) {
                    $fail('Enter a valid Philippine mobile number so we can text your reservation reference and tracking code.');
                }
            }],
            'occupant_count' => 'required|integer|min:1|max:20', 'other_occupants' => 'present|array|max:19', 'other_occupants.*' => 'required|string|min:2|max:100|distinct',
            'starts_on' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.today()->addYear()->toDateString(),
            'duration_months' => ['required', Rule::in([1, 3, 6, 12])], 'notes' => 'nullable|string|max:2000',
            'terms_accepted' => 'required|accepted', 'website' => 'nullable|string|size:0',
        ]);
        try {
            return $this->resource($service->request($data), 201);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['submission_token' => 'This request has already been submitted. Check your reservation or start a new request.']);
        }
    }

    public function show(Request $request, string $reference)
    {
        return $this->resource($this->authorized($request, $reference));
    }

    public function proof(Request $request, string $reference, HaruhayReservationService $service)
    {
        $reservation = $this->authorized($request, $reference);
        $request->merge(['reference' => trim((string) $request->input('reference'))]);
        $data = $request->validate([
            'submission_token' => 'required|uuid', 'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'method' => ['required', Rule::in(['Bank Transfer', 'GCash', 'Maya'])], 'reference' => 'required|string|max:100',
            'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'proof' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:10240',
            'terms_accepted' => 'required|accepted',
        ]);
        $service->submitProof($reservation, $data);

        return $this->resource($reservation->fresh());
    }

    public function cancel(Request $request, string $reference, HaruhayReservationService $service)
    {
        $reservation = $this->authorized($request, $reference);
        $data = $request->validate(['reason' => 'required|string|min:3|max:500']);

        return $this->resource($service->close($reservation, 'Cancelled', 'Applicant cancelled the request: '.$data['reason']));
    }

    private function authorized(Request $request, string $reference): HaruhayReservation
    {
        $r = HaruhayReservation::where('reference', $reference)->first();
        $valid = hash_equals($r?->tracking_token_hash ?? str_repeat('0', 64), hash('sha256', (string) $request->bearerToken()));
        abort_unless($r && $valid, 404);

        return $r;
    }

    private function resource(HaruhayReservation $r, int $status = 200)
    {
        return (new HaruhayReservationResource($r->load(['unit.property', 'contract', 'payments.collection', 'events', 'trackingSms'])))->response()->setStatusCode($status)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}

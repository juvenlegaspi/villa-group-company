<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\HaruhayReservation;
use App\Models\HaruhayReservationPayment;
use App\Services\HaruhayAccessService;
use App\Services\HaruhayReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HaruhayReservationController extends Controller
{
    public function index(Request $request)
    {
        return view('haruhay.reservations.index', $this->directoryData($request));
    }

    private function directoryData(Request $request): array
    {
        $request->validate(['search' => 'nullable|string|max:255', 'status' => ['nullable', Rule::in(HaruhayReservation::STATUSES)]]);
        $base = $this->visible();
        $counts = (clone $base)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $stats = [];
        foreach (['PendingReview', 'AwaitingPayment', 'PaymentReview', 'PartiallyPaid', 'Confirmed'] as $status) {
            $stats[$status] = (int) ($counts[$status] ?? 0);
        }
        $reservations = $base->with('unit.property')->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n->where('reference', 'like', '%'.$request->input('search').'%')->orWhere('name', 'like', '%'.$request->input('search').'%')->orWhere('email', 'like', '%'.$request->input('search').'%')))
            ->orderByRaw("CASE status WHEN 'PaymentReview' THEN 0 WHEN 'PendingReview' THEN 1 WHEN 'PartiallyPaid' THEN 2 ELSE 3 END")->orderByDesc('id')->paginate(10)->withPath(route('haruhay.reservations.index'))->appends($request->only('search', 'status'));

        return compact('reservations', 'stats');
    }

    public function live(Request $request)
    {
        $request->validate(['since' => 'nullable|integer|min:0', 'directory' => 'nullable|boolean', 'version' => 'nullable|string|max:64']);
        $user = $request->user();
        $unread = $user->visibleUnreadNotifications()->where('type', \App\Notifications\HaruhayReservationRequested::class);
        $latest = (int) $this->visible()->max('id');
        $response = [
            'unread_count' => (clone $unread)->count(),
            'notification_count' => $user->visibleUnreadNotifications()->count(),
            'latest_id' => $latest,
            'items' => (clone $unread)->where('data->reservation_id', '>', $request->integer('since'))->latest()->limit(5)->get()->map(fn ($n) => [
                'reference' => $n->data['reference'], 'name' => $n->data['applicant'], 'room' => $n->data['room'], 'url' => $n->data['url'],
            ])->values(),
        ];
        if ($request->boolean('directory')) {
            $revision = \Illuminate\Support\Facades\Cache::rememberForever('haruhay.reservations.directory-revision', fn () => (string) str()->uuid());
            $version = hash('sha256', json_encode([$revision, $user->id, $user->position_id, $user->department_id, $user->access_role_id, $request->input('search', ''), $request->input('status', ''), $request->integer('page', 1)]));
            $response['version'] = $version;
            if ($request->input('version') !== $version) {
                $data = $this->directoryData($request);
                $response += ['directory_html' => view('haruhay.reservations.directory', $data)->render(), 'stats' => $data['stats'], 'total' => $data['reservations']->total()];
            }
        }

        return response()->json($response)->header('Cache-Control', 'private, no-store');
    }

    public function show(HaruhayReservation $reservation)
    {
        $this->authorizeRecord($reservation);
        auth()->user()->visibleUnreadNotifications()->where('type', \App\Notifications\HaruhayReservationRequested::class)->where('data->reservation_id', $reservation->id)->update(['read_at' => now()]);
        $reservation->load(['unit.property', 'tenant', 'contract', 'payments.collection', 'events', 'trackingSms']);

        return response()->view('haruhay.reservations.show', compact('reservation') + ['canManage' => $this->allows('reservations.manage'), 'canVerify' => $this->allows('reservations.verify')])->header('Cache-Control', 'private, no-store');
    }

    public function approve(Request $request, HaruhayReservation $reservation, HaruhayReservationService $service)
    {
        $this->authorizeRecord($reservation);
        abort_unless($this->allows('reservations.manage'), 403);
        if (is_string($request->input('monthly_rent'))) {
            $request->merge(['monthly_rent' => str_replace(',', '', $request->input('monthly_rent'))]);
        }
        $data = $request->validate([
            'monthly_rent' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2', 'starts_on' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.today()->addYear()->toDateString(),
            'deposit_required' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'payment_instructions' => 'required|string|min:10|max:3000',
        ]);
        $service->approve($reservation, $data);

        return back()->with('success', 'Approved. Capacity held for 24 hours; one month advance is required.');
    }

    public function close(Request $request, HaruhayReservation $reservation, HaruhayReservationService $service)
    {
        $this->authorizeRecord($reservation);
        abort_unless($this->allows('reservations.manage'), 403);
        $data = $request->validate(['reason' => 'required|string|min:3|max:2000', 'decision' => 'required|in:Declined,Cancelled']);
        $service->close($reservation, $data['decision'], $data['reason']);

        return back()->with('success', 'Reservation closed; unpaid held capacity released.');
    }

    public function review(Request $request, HaruhayReservation $reservation, HaruhayReservationPayment $payment, HaruhayReservationService $service)
    {
        $this->authorizeRecord($reservation);
        abort_unless($this->allows('reservations.verify'), 403);
        abort_unless((int) $payment->reservation_id === (int) $reservation->id, 404);
        $data = $request->validate(['decision' => 'required|in:verify,reject', 'reason' => 'required|string|min:3|max:2000', 'verified_amount' => 'nullable|required_if:decision,verify|numeric|min:0.01|max:9999999999.99|decimal:0,2']);
        try {
            $service->review($payment, $data['decision'] === 'verify', $data['reason'], isset($data['verified_amount']) ? (float) $data['verified_amount'] : null);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reference' => 'This transaction reference has already been recorded.']);
        }

        return back()->with('success', $data['decision'] === 'verify' ? 'Payment verified and recorded once in Collections. Reservation status updated.' : 'Payment proof rejected; the applicant can see the reason.');
    }

    public function cash(Request $request, HaruhayReservation $reservation, HaruhayReservationService $service)
    {
        $this->authorizeRecord($reservation);
        abort_unless($this->allows('reservations.verify'), 403);
        $data = $request->validate(['submission_token' => 'required|uuid', 'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2', 'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'reason' => 'required|string|min:3|max:2000']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $service, $data) {
            $p = $service->submitProof($reservation, collect($data)->except('reason')->all() + ['method' => 'Cash', 'reference' => null], true);
            $service->review($p, true, $data['reason'], (float) $data['amount']);
        }, 3);

        return back()->with('success', 'Actual cash advance recorded and reservation status updated.');
    }

    public function proof(HaruhayReservation $reservation, HaruhayReservationPayment $payment)
    {
        $this->authorizeRecord($reservation);
        abort_unless((int) $payment->reservation_id === (int) $reservation->id && $payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, $payment->proof_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function resetAccess(Request $request, HaruhayReservation $reservation, HaruhayReservationService $service)
    {
        $this->authorizeRecord($reservation);
        abort_unless($this->allows('reservations.manage'), 403);
        $data = $request->validate(['identity_verified' => 'required|accepted', 'reason' => 'required|string|min:5|max:1000']);
        $token = str()->random(64);
        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $token, $data, $service) {
            $r = HaruhayReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $r->update(['tracking_token_hash' => hash('sha256', $token)]);
            $service->event($r, 'access_reset', 'Staff reset the secure access code after identity verification. '.$data['reason']);
        });

        return back()->with('success', 'Access code reset. Share only through the identity-verified contact channel; the old code is invalid.')->with('reservation_access_code', ['reference' => $reservation->reference, 'token' => $token]);
    }

    public function updateMessage(Request $request, HaruhayReservation $reservation, HaruhayReservationService $service)
    {
        $this->authorizeRecord($reservation);
        abort_unless($this->allows('reservations.manage'), 403);
        $data = $request->validate(['message' => 'required|string|min:3|max:2000']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $service, $data) {
            $r = HaruhayReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $service->event($r, 'staff_update', $data['message']);
        });

        return back()->with('success', 'Update posted to the applicant’s secure tracking page.');
    }

    private function visible()
    {
        return HaruhayReservation::whereHas('unit.property', fn ($q) => $q->whereIn('division_id', Division::whereRaw('LOWER(name) = ?', ['haruhay'])->select('id')));
    }

    private function authorizeRecord(HaruhayReservation $r): void
    {
        abort_unless($this->visible()->whereKey($r->id)->exists(), 404);
    }

    private function allows(string $permission): bool
    {
        return app(HaruhayAccessService::class)->allows(auth()->user(), $permission);
    }
}

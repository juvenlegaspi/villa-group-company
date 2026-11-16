<?php

namespace App\Http\Controllers;

use App\Models\{Division, HaruhayCollection, HaruhayTenant, User};
use App\Services\{HaruhayBillingWorkflow, HaruhayCollectionService};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\{Rule, ValidationException};

class HaruhayCollectionController extends Controller
{
    public const METHODS = ['Cash', 'Bank Transfer', 'GCash', 'Maya', 'Check'];
    public const ONLINE_METHODS = ['Bank Transfer', 'GCash', 'Maya'];

    public function index(Request $request, HaruhayBillingWorkflow $workflow)
    {
        $request->validate(['search' => 'nullable|string|max:255']);
        $tenants = $this->tenants()->with('unit.property')->with(['collections' => fn ($q) => $q->withSum('allocations', 'amount')->orderByDesc('paid_on')->orderByDesc('id')])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->input('search').'%')->orWhere('code', 'like', '%'.$request->input('search').'%')->orWhereHas('unit', fn ($u) => $u->where('unit_no', 'like', '%'.$request->input('search').'%'))))
            ->orderBy('name')->paginate(10)->withQueryString();
        $summaries = [];
        foreach ($tenants as $tenant) {
            $unpaid = $workflow->rows($tenant, $this->divisionId())->filter(fn ($row) => !$row['paid'])->values();
            $current = $unpaid->first();
            $next = $current && $current['invoice']?->finalized_at ? $unpaid->get(1) : $current;
            $summaries[$tenant->id] = ['current' => $current, 'next' => $next, 'collected' => $tenant->collections->filter(fn ($r) => !$r->voided_at && $r->paid_on->format('Y-m') === today()->format('Y-m'))->sum('amount'), 'credit' => $tenant->collections->sum('unapplied_cents'), 'last' => $tenant->collections->first(fn ($r) => !$r->voided_at)];
        }
        return view('haruhay.collections.index', compact('tenants', 'summaries') + ['stats' => $this->stats()]);
    }

    public function tenant(HaruhayTenant $tenant, HaruhayBillingWorkflow $workflow, HaruhayCollectionService $service)
    {
        $this->authorizeTenant($tenant);
        $tenant->load('unit.property');
        $current = $workflow->rows($tenant, $this->divisionId())->first(fn ($row) => !$row['paid']);
        $currentBill = $current && $current['invoice']?->finalized_at ? $current['invoice'] : null;
        $canReceiveAdvance = !$service->hasUnpaidIssuedBills($tenant);
        $onlineReservation = \App\Models\HaruhayReservation::where('tenant_id', $tenant->id)->whereIn('status', ['AwaitingPayment', 'PaymentReview', 'PartiallyPaid'])->first();
        if ($onlineReservation) $canReceiveAdvance = false;
        $receipts = $tenant->collections()->with(['invoice' => fn ($q) => $q->withSum('payments', 'amount'), 'receiver'])->withSum('allocations', 'amount')->orderByDesc('paid_on')->orderByDesc('id')->paginate(10)->withQueryString();
        $credits = $tenant->collections()->withSum('allocations', 'amount')->orderBy('paid_on')->orderBy('id')->get()->filter(fn ($r) => $r->unapplied_cents > 0);
        return view('haruhay.collections.tenant', compact('tenant', 'current', 'currentBill', 'receipts', 'credits', 'canReceiveAdvance', 'onlineReservation') + ['canManage' => $this->canManage() && !$onlineReservation, 'methods' => self::METHODS]);
    }

    public function store(Request $request, HaruhayTenant $tenant, HaruhayCollectionService $service)
    {
        abort_unless($this->canManage(), 403);
        $this->authorizeTenant($tenant);
        $value = $request->input('amount');
        if (is_string($value) && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/D', $value)) $request->merge(['amount' => str_replace(',', '', $value)]);
        $reference = $request->input('external_reference');
        if (is_string($reference)) $request->merge(['external_reference' => trim($reference) !== '' ? trim($reference) : null]);
        $data = $request->validateWithBag('collectionForm', [
            'submission_token' => 'required|uuid', 'receipt_type' => 'required|in:payment,advance',
            'billing_id' => ['nullable', 'integer', Rule::requiredIf($request->input('receipt_type') === 'payment'), Rule::prohibitedIf($request->input('receipt_type') === 'advance')],
            'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'method' => ['required', Rule::in(self::METHODS)],
            'external_reference' => ['nullable', Rule::requiredIf($request->input('method') !== 'Cash'), 'string', 'max:100'], 'remarks' => 'nullable|string|max:5000',
            'proof' => ['nullable', Rule::requiredIf(in_array($request->input('method'), self::ONLINE_METHODS, true)), 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        try { $receipt = $service->receive($tenant, $data, $this->divisionId()); }
        catch (UniqueConstraintViolationException $e) { throw ValidationException::withMessages(['external_reference' => 'This receipt or reference was already recorded. Check the collection history before trying again.'])->errorBag('collectionForm'); }
        return to_route('haruhay.collections.show', $receipt)->with('success', $data['receipt_type'] === 'advance' ? 'Advance received and held as tenant credit. It will be applied automatically when the completed bill is issued.' : 'Payment received. The applied amount and any unapplied credit have been recorded.');
    }

    public function apply(Request $request, HaruhayCollection $collection, HaruhayCollectionService $service)
    {
        abort_unless($this->canManage(), 403);
        abort_unless($this->visible()->whereKey($collection->id)->exists(), 404);
        $data = $request->validateWithBag('collectionForm', ['billing_id' => 'required|integer']);
        $service->applyCredit($collection, $data['billing_id'], $this->divisionId());
        return to_route('haruhay.collections.show', $collection)->with('success', 'Existing credit applied to the bill. No additional cash receipt was created.');
    }

    public function show(HaruhayCollection $collection)
    {
        abort_unless($this->visible()->whereKey($collection->id)->exists(), 404);
        $collection->load(['tenant', 'invoice' => fn ($q) => $q->withSum('payments', 'amount'), 'receiver', 'allocations.billing' => fn ($q) => $q->withSum('payments', 'amount')])->loadSum('allocations', 'amount');
        $collection->load(['allocationHistory.billing','reverser','deliveries']);
        return view('haruhay.collections.show', compact('collection') + ['canManage' => $this->canManage(), 'canReverse' => app(\App\Services\HaruhayAccessService::class)->allows(auth()->user(), 'collections.reverse')]);
    }

    public function retryNotifications(HaruhayCollection $collection)
    {
        abort_unless($this->canManage(), 403);
        abort_unless($this->visible()->whereKey($collection->id)->exists(), 404);
        abort_if($collection->voided_at, 422, 'A reversed receipt cannot be sent as a payment confirmation.');
        app(\App\Services\HaruhayCollectionNotificationService::class)->retryBlocked($collection);
        return back()->with('success', 'Blocked receipt notifications queued again. Submitted messages will not be resent.');
    }

    public function transactions(Request $request)
    {
        $filters = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'method' => ['nullable', Rule::in(self::METHODS)], 'received_by' => 'nullable|integer', 'search' => 'nullable|string|max:255']);
        $receipts = $this->visible()->with(['invoice' => fn ($q) => $q->withSum('payments', 'amount'), 'receiver'])->withSum('allocations', 'amount')
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('paid_on', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('paid_on', '<=', $filters['to']))
            ->when(filled($filters['method'] ?? null), fn ($q) => $q->where('method', $filters['method']))
            ->when(filled($filters['received_by'] ?? null), fn ($q) => $q->where('created_by', $filters['received_by']))
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where(fn ($s) => $s->where('code', 'like', '%'.$filters['search'].'%')->orWhere('tenant_name', 'like', '%'.$filters['search'].'%')->orWhere('external_reference', 'like', '%'.$filters['search'].'%')))
            ->orderByDesc('paid_on')->orderByDesc('id')->paginate(10)->withQueryString();
        $receivers = User::whereIn('id', $this->visible()->select('created_by'))->orderBy('name')->get();
        return view('haruhay.collections.transactions', compact('receipts', 'receivers') + ['methods' => self::METHODS]);
    }

    public function proof(HaruhayCollection $collection)
    {
        abort_unless($this->visible()->whereKey($collection->id)->exists(), 404);
        abort_unless($collection->proof_path && Storage::disk('local')->exists($collection->proof_path), 404);
        return Storage::disk('local')->response($collection->proof_path, $collection->proof_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function reverse(Request $request, HaruhayCollection $collection, HaruhayCollectionService $service)
    {
        abort_unless(app(\App\Services\HaruhayAccessService::class)->allows(auth()->user(),'collections.reverse'),403);
        abort_unless($this->visible()->whereKey($collection->id)->exists(),404);
        $data=$request->validateWithBag('collectionForm',['reason'=>'required|string|max:2000']);
        $service->reverse($collection,$data['reason'],$this->divisionId());
        return to_route('haruhay.collections.show',$collection)->with('success','Receipt reversed. Original details and allocation history are preserved. Record a new receipt for the corrected payment.');
    }

    private function stats(): array
    {
        $month = $this->visible()->active()->whereDate('paid_on', '>=', today()->startOfMonth())->whereDate('paid_on', '<=', today()->endOfMonth());
        $received = (clone $month)->sum('amount');
        $cash = (clone $month)->where('method', 'Cash')->sum('amount');
        $unapplied = $this->visible()->withSum('allocations', 'amount')->get()->sum('unapplied_cents');
        return ['Collections This Month' => $received, 'Cash This Month' => $cash, 'Non-cash This Month' => $received - $cash, 'Transactions This Month' => (clone $month)->count(), 'Unapplied Credit · All Receipts' => $unapplied / 100];
    }
    private function divisionId(): int { return (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id; }
    private function tenants() { return HaruhayTenant::whereHas('unit.property', fn ($q) => $q->where('division_id', $this->divisionId())); }
    private function visible() { return HaruhayCollection::whereHas('tenant.unit.property', fn ($q) => $q->where('division_id', $this->divisionId())); }
    private function authorizeTenant(HaruhayTenant $tenant): void { abort_unless($this->tenants()->whereKey($tenant->id)->exists(), 404); }
    private function canManage(): bool { return app(\App\Services\HaruhayAccessService::class)->allows(auth()->user(), 'collections.manage'); }
}

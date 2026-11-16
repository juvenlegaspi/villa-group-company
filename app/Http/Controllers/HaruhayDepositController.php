<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\HaruhayDepositEntry;
use App\Models\HaruhayTenant;
use App\Services\HaruhayAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HaruhayDepositController extends Controller
{
    public function index(HaruhayTenant $tenant)
    {
        $this->authorizeTenant($tenant);
        $tenant->load(['unit.property', 'latestContract']);

        return view('haruhay.deposits.index', ['tenant' => $tenant, 'entries' => $tenant->depositEntries()->with(['creator', 'reverser'])->latest('id')->paginate(10), 'held' => HaruhayDepositEntry::heldCents($tenant->id), 'canReceive' => $this->allows('deposits.receive') || $this->allows('deposits.manage'), 'canManage' => $this->allows('deposits.manage'), 'methods' => HaruhayCollectionController::METHODS]);
    }

    public function store(Request $request, HaruhayTenant $tenant)
    {
        $this->authorizeTenant($tenant);
        $type = $request->input('type');
        abort_unless($type === 'received' ? ($this->allows('deposits.receive') || $this->allows('deposits.manage')) : $this->allows('deposits.manage'), 403);
        $amount = $request->input('amount');
        if (is_string($amount) && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/D', $amount)) {
            $request->merge(['amount' => str_replace(',', '', $amount)]);
        }
        $data = $request->validateWithBag('depositForm', [
            'submission_token' => 'required|uuid', 'type' => 'required|in:received,deduction,refund', 'amount' => 'required|numeric|decimal:0,2|min:0.01|max:9999999999.99', 'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'reason' => 'required|string|max:2000', 'method' => ['nullable', Rule::requiredIf($type !== 'deduction'), Rule::in(HaruhayCollectionController::METHODS)], 'reference' => ['nullable', Rule::requiredIf($type !== 'deduction' && $request->input('method') !== 'Cash'), 'string', 'max:100'],
            'proof' => ['nullable', Rule::requiredIf($type !== 'deduction' && in_array($request->input('method'), HaruhayCollectionController::ONLINE_METHODS, true)), 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        if ($data['type'] === 'deduction') {
            $data['method'] = null;
            $data['reference'] = null;
        }
        $path = null;
        try {
            $saved = DB::transaction(function () use ($tenant, $data, &$path) {
                $person = HaruhayTenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $existing = HaruhayDepositEntry::where('submission_token', $data['submission_token'])->first();
                if ($existing) {
                    if ((int) $existing->tenant_id !== $person->id || $existing->type !== $data['type'] || (float) $existing->amount !== (float) $data['amount'] || $existing->occurred_on->toDateString() !== $data['occurred_on'] || $existing->method !== ($data['method'] ?? null) || $existing->reference !== ($data['reference'] ?? null)) {
                        $this->invalid('amount', 'This submission already belongs to a different deposit entry.');
                    }

return $existing;
                }
                if (! empty($data['reference']) && HaruhayDepositEntry::active()->where('method', $data['method'] ?? null)->where('reference', $data['reference'])->exists()) {
                    $this->invalid('reference', 'This deposit transaction reference was already recorded.');
                }
                if ($data['type'] === 'refund' && $person->status !== 'Moved Out') {
                    $this->invalid('type', 'Confirm tenant move-out before recording a deposit refund.');
                }
                if ($data['type'] !== 'received' && (int) round($data['amount'] * 100) > HaruhayDepositEntry::heldCents($person->id)) {
                    $this->invalid('amount', 'The amount exceeds the security deposit currently held.');
                }
                if (! empty($data['proof']) && ! $path) {
                    $path = $data['proof']->store('haruhay/deposit-proofs/'.$person->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('Unable to save deposit proof.');
                    }
                }
                $entry = HaruhayDepositEntry::create(collect($data)->except('proof')->all() + ['tenant_id' => $person->id, 'property_id' => $person->unit->property_id, 'contract_id' => $person->contracts()->latest('starts_on')->value('id'), 'tenant_name' => $person->name, 'property_name' => $person->unit->property->name, 'room_no' => $person->unit->unit_no, 'code' => 'pending-'.str()->uuid(), 'created_by' => auth()->id(), 'proof_path' => $path, 'proof_name' => ! empty($data['proof']) ? $data['proof']->getClientOriginalName() : null]);
                $entry->update(['code' => 'DEP-'.str_pad((string) $entry->id, 5, '0', STR_PAD_LEFT)]);
                if (! HaruhayDepositEntry::hasNonNegativeHistory($person->id)) {
                    $this->invalid('occurred_on', 'This date would make the historical deposit balance negative.');
                }

                return $entry;
            }, 3);
            if ($path && $saved->proof_path !== $path) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }if ($e instanceof \Illuminate\Database\UniqueConstraintViolationException) {
                $this->invalid('reference', 'This deposit submission or reference is already recorded.');
            }throw $e;
        }

        return to_route('haruhay.deposits.index', $tenant)->with('success', 'Deposit entry recorded. Rent and advance balances are unchanged.');
    }

    public function reverse(Request $request, HaruhayTenant $tenant, HaruhayDepositEntry $entry)
    {
        $this->authorizeTenant($tenant);
        abort_unless($this->allows('deposits.manage'), 403);
        abort_unless((int) $entry->tenant_id === $tenant->id, 404);
        $data = $request->validateWithBag('depositForm', ['reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($tenant, $entry, $data) {
            HaruhayTenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $entry = HaruhayDepositEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->voided_at) {
                $this->invalid('reason', 'This deposit entry is already reversed.');
            }
            if ($entry->type === 'received' && HaruhayDepositEntry::heldCents($tenant->id) < (int) round((float) $entry->amount * 100)) {
                $this->invalid('amount', 'This deposit has already been used. Reverse the related deduction/refund first.');
            }
            $entry->update(['voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => $data['reason']]);
            if (! HaruhayDepositEntry::hasNonNegativeHistory($tenant->id)) {
                $this->invalid('reason', 'This correction would make the historical deposit balance negative. Reverse related deductions/refunds first.');
            }
        }, 3);

        return to_route('haruhay.deposits.index', $tenant)->with('success', 'Deposit entry reversed; the original record remains in history.');
    }

    public function proof(HaruhayTenant $tenant, HaruhayDepositEntry $entry)
    {
        $this->authorizeTenant($tenant);
        abort_unless((int) $entry->tenant_id === $tenant->id && $entry->proof_path && Storage::disk('local')->exists($entry->proof_path), 404);

        return Storage::disk('local')->response($entry->proof_path, $entry->proof_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function allows(string $permission): bool
    {
        return app(HaruhayAccessService::class)->allows(auth()->user(), $permission);
    }

    private function authorizeTenant(HaruhayTenant $tenant): void
    {
        abort_unless((int) $tenant->unit->property->division_id === (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id, 404);
    }

    private function invalid(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field => $message])->errorBag('depositForm');
    }
}

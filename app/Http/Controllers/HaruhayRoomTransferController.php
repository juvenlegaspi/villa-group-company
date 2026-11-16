<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Services\HaruhayAccessService;
use App\Services\HaruhayBillingSchedule;
use App\Services\HaruhayTenantService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HaruhayRoomTransferController extends Controller
{
    public function create(HaruhayTenant $tenant)
    {
        $this->authorizeTenant($tenant);
        $tenant->load('unit.property');
        $contract = $tenant->contracts()->whereNull('terminated_on')->whereDoesntHave('amendment')->whereDate('starts_on', '<=', today())->latest('starts_on')->first();
        abort_unless($contract, 422, 'Add a lease contract before creating a room-transfer amendment.');
        $rooms = HaruhayUnit::whereHas('property', fn ($q) => $q->where('division_id', $this->divisionId()))->whereKeyNot($tenant->unit_id)->where('room_condition', 'Ready')->with('property')->get()->filter(fn ($r) => $r->available_slots > 0);
        $coverage = $this->coverage($contract);

        return view('haruhay.tenants.transfer', compact('tenant', 'contract', 'rooms', 'coverage'));
    }

    public function store(Request $request, HaruhayTenant $tenant, HaruhayTenantService $tenants)
    {
        $this->authorizeTenant($tenant);
        if (is_string($request->input('monthly_rent'))) {
            $request->merge(['monthly_rent' => str_replace(',', '', $request->input('monthly_rent'))]);
        }
        $data = $request->validateWithBag('transferForm', ['unit_id' => 'required|integer', 'monthly_rent' => 'required|numeric|decimal:0,2|min:0|max:9999999999.99', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:today', 'reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($tenant, $data, $tenants) {
            $person = HaruhayTenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (! in_array($person->status, HaruhayTenant::LIVE_STATUSES, true)) {
                $this->invalid('unit_id', 'Room amendments are for tenants who have moved in.');
            }
            if ((int) $data['unit_id'] === (int) $person->unit_id) {
                $this->invalid('unit_id', 'Select a different room.');
            }
            if ($person->contracts()->whereNull('terminated_on')->whereDate('starts_on', '>', today())->exists()) {
                $this->invalid('unit_id', 'Resolve the scheduled future contract/renewal in Lease Contracts before transferring rooms.');
            }
            $contract = $person->contracts()->whereNull('terminated_on')->whereDoesntHave('amendment')->whereDate('starts_on', '<=', today())->latest('starts_on')->lockForUpdate()->first();
            if (! $contract) {
                $this->invalid('unit_id', 'No current contract is available for amendment.');
            }
            if ($contract->billings()->whereNull('finalized_at')->whereDate('period_end', '>=', today())->exists()) {
                $this->invalid('unit_id', 'Complete and issue the current draft bill before transferring rooms.');
            }
            $coverage = $this->coverage($contract);
            if (Carbon::parse($data['ends_on'])->lt($coverage)) {
                $this->invalid('ends_on', 'The amended lease must cover the first unbilled day: '.$coverage->toDateString());
            }
            $room = HaruhayUnit::whereKey($data['unit_id'])->whereHas('property', fn ($q) => $q->where('division_id', $this->divisionId()))->firstOrFail();
            $person = $tenants->save($person->only(['name', 'contact_number', 'email', 'move_in_date', 'scheduled_move_in_date', 'move_out_date', 'status', 'emergency_contact_name', 'emergency_contact_number', 'remarks']) + ['unit_id' => $room->id], $this->divisionId(), $person, 'save', true);
            $period = collect(app(HaruhayBillingSchedule::class)->periods($contract))->firstWhere('start', $coverage->toDateString());
            $due = $period ? Carbon::parse($period['due']) : $coverage->copy()->addMonthNoOverflow();
            $new = HaruhayLeaseContract::create([
                'code' => 'pending-'.str()->uuid(), 'tenant_id' => $person->id, 'unit_id' => $room->id, 'amends_contract_id' => $contract->id,
                'tenant_name' => $person->name, 'property_name' => $room->property->name, 'room_no' => $room->unit_no, 'floor' => $room->floor,
                'starts_on' => today(), 'ends_on' => $data['ends_on'], 'billing_coverage_starts_on' => $coverage, 'monthly_rent' => $data['monthly_rent'],
                'billing_starts_on' => $due, 'first_due_on' => $due, 'due_day' => $contract->billing_cycle === 'lease_anniversary' ? $due->day : $contract->due_day, 'billing_cycle' => $contract->billing_cycle,
                'first_month_policy' => 'full_month', 'first_month_charge' => $data['monthly_rent'], 'deposit_required' => $contract->deposit_required, 'advance_required' => 0,
                'amendment_reason' => $data['reason'], 'remarks' => 'Room-transfer amendment of '.$contract->code.'. New rent coverage starts '.$coverage->toDateString().'. Existing billed coverage is preserved.', 'created_by' => auth()->id(),
            ]);
            $new->update(['code' => 'CTR-'.str_pad((string) $new->id, 4, '0', STR_PAD_LEFT)]);
        }, 3);

        return to_route('haruhay.contracts.tenant', $tenant)->with('success', 'Room transferred and contract amendment recorded. Existing invoices, advances and deposit history are preserved.');
    }

    private function coverage(HaruhayLeaseContract $contract): Carbon
    {
        $end = today()->subDay();
        foreach (app(HaruhayBillingSchedule::class)->periods($contract) as $p) {
            if ($p['start'] <= today()->toDateString() && $p['end'] >= today()->toDateString()) {
                $end = Carbon::parse($p['end']);
            }
        }
        $billed = $contract->billings()->whereNotNull('finalized_at')->max('period_end');
        if ($billed && Carbon::parse($billed)->gt($end)) {
            $end = Carbon::parse($billed);
        }

        return $end->addDay();
    }

    private function divisionId(): int
    {
        return (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id;
    }

    private function authorizeTenant(HaruhayTenant $tenant): void
    {
        abort_unless(app(HaruhayAccessService::class)->allows(auth()->user(), 'transfers.manage'), 403);
        abort_unless((int) $tenant->unit->property->division_id === $this->divisionId(), 404);
    }

    private function invalid(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field => $message])->errorBag('transferForm');
    }
}

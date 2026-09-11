<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use App\Models\YatiraSalesHistory;
use App\Models\YatiraSalesLead;
use App\Models\YatiraSalesStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class YatiraSalesController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('yatira.sales.view');
        $query = $this->visibleLeads()->with(['stage:id,name,probability_percent', 'agent:id,name,lastname']);
        $search = $request->string('search')->trim()->toString();
        $query->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $n) => $n->where('lead_code', 'like', "%{$search}%")->orWhere('client_name', 'like', "%{$search}%")->orWhere('project_name', 'like', "%{$search}%")->orWhere('project_location', 'like', "%{$search}%")))
            ->when($request->filled('stage_id'), fn (Builder $q) => $q->where('sales_stage_id', $request->integer('stage_id')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('agent_id'), fn (Builder $q) => $q->where('assigned_agent_id', $request->integer('agent_id')));
        $leads = $query->latest('date_registered')->latest('id')->paginate(12)->withQueryString();
        $statsQuery = $this->visibleLeads();
        $stats = ['total' => (clone $statsQuery)->count(), 'active' => (clone $statsQuery)->where('status', 'ACTIVE')->count(), 'awarded' => (clone $statsQuery)->where('status', 'AWARDED')->count(), 'cancelled' => (clone $statsQuery)->where('status', 'CANCELLED')->count(), 'pipeline' => (float) (clone $statsQuery)->where('status', 'ACTIVE')->sum('estimated_value'), 'weighted' => (float) (clone $statsQuery)->where('status', 'ACTIVE')->selectRaw('COALESCE(SUM(estimated_value * probability_percent / 100),0) total')->value('total')];

        return view('yatira.sales.index', ['leads' => $leads, 'stats' => $stats, 'stages' => $this->stages(), 'agents' => $this->agents(), 'canCreate' => $this->can('yatira.sales.create'), 'canManage' => $this->can('yatira.sales.manage')]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('yatira.sales.create');
        $data = $this->validateLead($request);
        $stage = YatiraSalesStage::where('is_active', true)->orderBy('sequence')->firstOrFail();
        $data['assigned_agent_id'] = $this->can('yatira.sales.manage') ? ($data['assigned_agent_id'] ?? auth()->id()) : auth()->id();
        $lead = DB::transaction(function () use ($data, $stage): YatiraSalesLead {
            $lead = YatiraSalesLead::create([...$data, 'division_id' => $this->divisionId(), 'lead_code' => 'TMP-'.str()->uuid(), 'date_registered' => $data['date_registered'] ?? today(), 'sales_stage_id' => $stage->id, 'probability_percent' => $stage->probability_percent, 'status' => 'ACTIVE', 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
            $lead->update(['lead_code' => 'LD-'.$lead->date_registered->format('Y').'-'.str_pad((string) $lead->id, 4, '0', STR_PAD_LEFT)]);
            $this->history($lead, 'LEAD_CREATED', null, $stage, null, ['lead_code' => $lead->lead_code]);

            return $lead;
        });

        return redirect()->route('yatira.sales.show', $lead)->with('success', 'Lead registered successfully.');
    }

    public function show(YatiraSalesLead $lead)
    {
        $this->authorizeLead($lead, false);
        $lead->load(['stage', 'agent:id,name,lastname', 'creator:id,name,lastname']);
        $histories = $lead->histories()->with(['user:id,name,lastname', 'fromStage:id,name', 'toStage:id,name'])->paginate(20);

        return view('yatira.sales.show', ['lead' => $lead, 'histories' => $histories, 'stages' => $this->stages(), 'agents' => $this->agents(), 'canUpdate' => $this->canUpdate($lead), 'canManage' => $this->can('yatira.sales.manage')]);
    }

    public function update(Request $request, YatiraSalesLead $lead)
    {
        $this->authorizeLead($lead, true);
        abort_if($lead->status === 'CANCELLED', 422, 'A cancelled lead can no longer be edited.');
        $data = $this->validateLead($request, $lead);
        if (! $this->can('yatira.sales.manage')) {
            unset($data['assigned_agent_id']);
        }$before = $lead->only(array_keys($data));
        $lead->update([...$data, 'updated_by' => auth()->id()]);
        $this->history($lead, 'LEAD_UPDATED', null, null, $request->input('remarks'), ['before' => $before, 'after' => $lead->fresh()->only(array_keys($before))]);

        return back()->with('success', 'Lead information updated successfully.');
    }

    public function updateStage(Request $request, YatiraSalesLead $lead)
    {
        $this->authorizeLead($lead, true);
        abort_if(in_array($lead->status, ['CANCELLED', 'AWARDED'], true), 422, 'A completed lead cannot receive stage updates.');
        $data = $request->validate(['sales_stage_id' => 'required|exists:yatira_sales_stages,id', 'stage_remarks' => 'nullable|string|max:2000']);
        $stage = YatiraSalesStage::whereKey($data['sales_stage_id'])->where('is_active', true)->firstOrFail();
        $from = $lead->sales_stage_id;
        $status = $stage->probability_percent >= 100 ? 'AWARDED' : 'ACTIVE';
        $lead->update(['sales_stage_id' => $stage->id, 'probability_percent' => $stage->probability_percent, 'status' => $status, 'updated_by' => auth()->id()]);
        $this->history($lead, 'STAGE_UPDATED', $from, $stage, $data['stage_remarks'] ?? null, ['probability' => $stage->probability_percent, 'status' => $status]);

        return back()->with('success', 'Sales stage and probability updated successfully.');
    }

    public function cancel(Request $request, YatiraSalesLead $lead)
    {
        $this->authorizeLead($lead, true);
        abort_if($lead->status === 'CANCELLED', 422, 'This lead is already cancelled.');
        $data = $request->validate(['cancellation_reason' => 'required|string|max:2000']);
        $before = $lead->status;
        $lead->update(['status' => 'CANCELLED', 'probability_percent' => 0, 'cancellation_reason' => $data['cancellation_reason'], 'cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'updated_by' => auth()->id()]);
        $this->history($lead, 'LEAD_CANCELLED', $lead->sales_stage_id, null, $data['cancellation_reason'], ['status_before' => $before, 'probability' => 0]);

        return back()->with('success', 'Lead cancelled and retained in the permanent history.');
    }

    public function criteria()
    {
        $this->authorizePermission('yatira.sales.view');

        return view('yatira.sales.criteria', ['stages' => $this->stages()]);
    }

    private function validateLead(Request $request, ?YatiraSalesLead $lead = null): array
    {
        $divisionId = $this->divisionId();
        $data = $request->validate(['date_registered' => $lead ? 'required|date|before_or_equal:today' : 'nullable|date|before_or_equal:today', 'client_name' => 'required|string|max:255', 'contact_person' => 'nullable|string|max:255', 'contact_number' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255', 'client_address' => 'nullable|string|max:2000', 'project_name' => 'required|string|max:255', 'project_type' => 'nullable|string|max:255', 'project_description' => 'nullable|string|max:3000', 'estimated_value' => 'required|numeric|min:0|max:999999999999.99', 'project_location' => 'required|string|max:255', 'lead_source' => 'nullable|string|max:255', 'expected_close_date' => 'nullable|date|after_or_equal:date_registered', 'assigned_agent_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('division_id', $divisionId)->where('status', true))], 'remarks' => 'nullable|string|max:2000']);
        if (! empty($data['assigned_agent_id']) && ! $this->agents()->contains('id', (int) $data['assigned_agent_id'])) {
            throw ValidationException::withMessages(['assigned_agent_id' => 'Select an active Yatira Sales Manager or Sales Agent.']);
        }

        return $data;
    }

    private function visibleLeads(): Builder
    {
        $query = YatiraSalesLead::where('division_id', $this->divisionId());
        if (! $this->can('yatira.sales.manage')) {
            $query->where(fn ($q) => $q->where('assigned_agent_id', auth()->id())->orWhere('created_by', auth()->id()));
        }

return $query;
    }

    private function authorizeLead(YatiraSalesLead $lead, bool $update): void
    {
        abort_unless((int) $lead->division_id === $this->divisionId(), 404);
        $this->authorizePermission($update ? 'yatira.sales.update' : 'yatira.sales.view');
        if (! $this->can('yatira.sales.manage')) {
            abort_unless((int) $lead->assigned_agent_id === (int) auth()->id() || (int) $lead->created_by === (int) auth()->id(), 403);
        }
    }

    private function canUpdate(YatiraSalesLead $lead): bool
    {
        return $this->can('yatira.sales.update') && ($this->can('yatira.sales.manage') || (int) $lead->assigned_agent_id === (int) auth()->id() || (int) $lead->created_by === (int) auth()->id());
    }

    private function history(YatiraSalesLead $lead, string $action, $from, $to, ?string $remarks, array $changes = []): void
    {
        YatiraSalesHistory::create(['yatira_sales_lead_id' => $lead->id, 'user_id' => auth()->id(), 'action' => $action, 'from_stage_id' => $from instanceof YatiraSalesStage ? $from->id : $from, 'to_stage_id' => $to instanceof YatiraSalesStage ? $to->id : $to, 'remarks' => $remarks, 'changes' => $changes ?: null, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent() ? mb_substr(request()->userAgent(), 0, 1000) : null, 'created_at' => now()]);
    }

    private function stages()
    {
        return YatiraSalesStage::where('is_active', true)->orderBy('sequence')->get();
    }

    private function agents()
    {
        return User::where('division_id', $this->divisionId())->where('status', true)->whereHas('position', fn ($q) => $q->whereIn('code', ['sales-manager', 'sales-agent']))->orderBy('name')->get(['id', 'name', 'lastname']);
    }

    private function can(string $permission): bool
    {
        return auth()->user()->isSystemAdministrator() || auth()->user()->hasPermission($permission);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless($this->can($permission),403,'Your position is not authorized for Yatira Sales Monitoring.');
    }

    private function divisionId(): int
    {
        return (int) Division::whereRaw('LOWER(name) = ?',['yatira'])->value('id');
    }
}

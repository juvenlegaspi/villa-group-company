@php($editing = isset($lead))
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="{{ $prefix }}date_registered">Date Registered <span class="text-rose-600">*</span></label>
        <input class="form-control" id="{{ $prefix }}date_registered" type="date" name="date_registered" value="{{ old('date_registered', $editing ? $lead->date_registered?->format('Y-m-d') : now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
    </div>
    <div>
        <label for="{{ $prefix }}assigned_agent_id">Assigned Sales Agent</label>
        @if($canManage)
            <select class="form-select" id="{{ $prefix }}assigned_agent_id" name="assigned_agent_id">
                <option value="">Select sales agent</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" @selected((string) old('assigned_agent_id', $editing ? $lead->assigned_agent_id : '') === (string) $agent->id)>{{ $agent->name }} {{ $agent->lastname }}</option>
                @endforeach
            </select>
        @else
            <input class="form-control bg-slate-50" value="{{ auth()->user()->name }} {{ auth()->user()->lastname }}" disabled>
            <p class="mt-1 text-xs text-slate-500">The lead will be assigned to you automatically.</p>
        @endif
    </div>
    <div>
        <label for="{{ $prefix }}client_name">Client Name <span class="text-rose-600">*</span></label>
        <input class="form-control" id="{{ $prefix }}client_name" name="client_name" value="{{ old('client_name', $editing ? $lead->client_name : '') }}" maxlength="255" required>
    </div>
    <div>
        <label for="{{ $prefix }}contact_person">Contact Person</label>
        <input class="form-control" id="{{ $prefix }}contact_person" name="contact_person" value="{{ old('contact_person', $editing ? $lead->contact_person : '') }}" maxlength="255">
    </div>
    <div>
        <label for="{{ $prefix }}contact_number">Contact Number</label>
        <input class="form-control" id="{{ $prefix }}contact_number" name="contact_number" value="{{ old('contact_number', $editing ? $lead->contact_number : '') }}" maxlength="30">
    </div>
    <div>
        <label for="{{ $prefix }}email">Email Address</label>
        <input class="form-control" id="{{ $prefix }}email" type="email" name="email" value="{{ old('email', $editing ? $lead->email : '') }}" maxlength="255">
    </div>
    <div class="md:col-span-2">
        <label for="{{ $prefix }}client_address">Client Address</label>
        <textarea class="form-control" id="{{ $prefix }}client_address" name="client_address" rows="2" maxlength="2000">{{ old('client_address', $editing ? $lead->client_address : '') }}</textarea>
    </div>
    <div>
        <label for="{{ $prefix }}project_name">Project Name <span class="text-rose-600">*</span></label>
        <input class="form-control" id="{{ $prefix }}project_name" name="project_name" value="{{ old('project_name', $editing ? $lead->project_name : '') }}" maxlength="255" required>
    </div>
    <div>
        <label for="{{ $prefix }}project_type">Project Type</label>
        <input class="form-control" id="{{ $prefix }}project_type" name="project_type" value="{{ old('project_type', $editing ? $lead->project_type : '') }}" placeholder="e.g. Building construction" maxlength="255">
    </div>
    <div>
        <label for="{{ $prefix }}estimated_value">Estimated Value <span class="text-rose-600">*</span></label>
        <div class="relative"><span class="pointer-events-none absolute inset-y-0 left-0 grid place-items-center pl-4 font-bold text-slate-500">₱</span><input class="form-control pl-9" id="{{ $prefix }}estimated_value" type="number" name="estimated_value" value="{{ old('estimated_value', $editing ? $lead->estimated_value : '') }}" min="0" step="0.01" required></div>
    </div>
    <div>
        <label for="{{ $prefix }}project_location">Project Location <span class="text-rose-600">*</span></label>
        <input class="form-control" id="{{ $prefix }}project_location" name="project_location" value="{{ old('project_location', $editing ? $lead->project_location : '') }}" maxlength="255" required>
    </div>
    <div>
        <label for="{{ $prefix }}lead_source">Lead Source</label>
        <input class="form-control" id="{{ $prefix }}lead_source" name="lead_source" value="{{ old('lead_source', $editing ? $lead->lead_source : '') }}" placeholder="Referral, website, walk-in..." maxlength="255">
    </div>
    <div>
        <label for="{{ $prefix }}expected_close_date">Expected Close Date</label>
        <input class="form-control" id="{{ $prefix }}expected_close_date" type="date" name="expected_close_date" value="{{ old('expected_close_date', $editing ? $lead->expected_close_date?->format('Y-m-d') : '') }}">
    </div>
    <div class="md:col-span-2">
        <label for="{{ $prefix }}project_description">Project Description</label>
        <textarea class="form-control" id="{{ $prefix }}project_description" name="project_description" rows="3" maxlength="3000">{{ old('project_description', $editing ? $lead->project_description : '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label for="{{ $prefix }}remarks">Remarks</label>
        <textarea class="form-control" id="{{ $prefix }}remarks" name="remarks" rows="2" maxlength="2000">{{ old('remarks', $editing ? $lead->remarks : '') }}</textarea>
    </div>
</div>

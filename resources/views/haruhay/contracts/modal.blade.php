@php
    $suggestedStart = $renewalOf ? ($renewalOf->terminated_on && $renewalOf->terminated_on->lt($renewalOf->ends_on) ? $renewalOf->terminated_on : $renewalOf->ends_on)->copy()->addDay() : ($tenant->move_in_date ?? $tenant->scheduled_move_in_date ?? today());
@endphp
<div class="modal" id="contractModal" tabindex="-1" role="dialog" aria-labelledby="contractModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form method="POST" enctype="multipart/form-data" action="{{ route('haruhay.contracts.store', $tenant) }}" class="modal-content">
    @csrf
    <input type="hidden" name="previous_contract_id" value="{{ old('previous_contract_id', $renewalOf?->id) }}">
    <header class="modal-header"><div><p class="mb-1 text-xs font-bold uppercase tracking-widest text-teal-700">{{ $tenant->code }} · {{ $tenant->name }}</p><h2 class="modal-title" id="contractModalTitle">{{ $renewalOf ? 'Renew '.$renewalOf->code : 'Add Lease Contract' }}</h2><p class="mb-0 mt-1 text-xs text-slate-500">{{ $tenant->unit->property->name }} · {{ $tenant->unit->unit_no }}. Contract number is generated on save.</p></div><button type="button" data-bs-dismiss="modal" aria-label="Close contract form" class="ml-auto grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-xl text-slate-500">&times;</button></header>
    <div class="modal-body space-y-5 bg-slate-50">
        @if($errors->contractForm->any())<div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Please check the following:</strong><ul class="mb-0 mt-2">@foreach($errors->contractForm->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p class="mb-0 mt-2">Reselect the signed document if you attached one.</p></div>@endif
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-4 text-base font-black text-slate-900">Lease Period &amp; Rent</h3><div class="grid gap-4 sm:grid-cols-2">
            <label for="leaseStart">Lease Start Date *<input id="leaseStart" type="date" name="starts_on" required value="{{ old('starts_on', $suggestedStart->toDateString()) }}" class="form-control mt-2"></label>
            <label for="leaseDuration">Lease Duration *<select id="leaseDuration" name="duration_months" required class="form-control mt-2">@foreach([1 => '1 Month', 3 => '3 Months', 6 => '6 Months', 12 => '1 Year'] as $months => $label)<option value="{{ $months }}" @selected((int) old('duration_months', 12) === $months)>{{ $label }}</option>@endforeach</select></label>
            <label for="leaseEnd" class="sm:col-span-2">Lease End Date<input id="leaseEnd" type="date" name="ends_on" readonly required class="form-control mt-2"><span class="mt-2 block text-xs font-normal text-slate-500">Calculated from the start date and duration. This is the last day covered by the contract.</span></label>
            <label for="contractRent" class="sm:col-span-2">Agreed Monthly Rent (₱) *<input id="contractRent" type="text" inputmode="decimal" name="monthly_rent" data-contract-money maxlength="20" required value="{{ old('monthly_rent', $tenant->unit->monthly_rent ?? $renewalOf?->monthly_rent) }}" placeholder="e.g. 10,000" class="form-control mt-2"><span class="mt-2 block text-xs font-normal text-slate-500">Defaults to the unit's monthly rate. You can change this to the agreed rent for this tenant. Saved contracts keep their agreed amount.</span></label>
        </div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-4 text-base font-black text-slate-900">Billing Schedule</h3><div class="grid gap-4 sm:grid-cols-2">
            <label>Billing Starts *<input type="date" name="billing_starts_on" required value="{{ old('billing_starts_on', $suggestedStart->copy()->addMonthNoOverflow()->toDateString()) }}" class="form-control mt-2"></label>
            <label>First Due Date *<input type="date" name="first_due_on" required value="{{ old('first_due_on', $suggestedStart->copy()->addMonthNoOverflow()->toDateString()) }}" class="form-control mt-2"></label>
            <label>Billing Cycle *<select name="billing_cycle" required class="form-control mt-2"><option value="calendar_month" @selected(old('billing_cycle', $renewalOf?->billing_cycle) !== 'lease_anniversary')>Calendar Month</option><option value="lease_anniversary" @selected(old('billing_cycle', $renewalOf?->billing_cycle) === 'lease_anniversary')>Lease Anniversary</option></select></label>
            <label>Monthly Due Day *<input type="number" name="due_day" min="1" max="31" required value="{{ old('due_day', $suggestedStart->copy()->addMonthNoOverflow()->day) }}" class="form-control mt-2"></label>
            <p class="mb-0 text-xs leading-5 text-slate-500 sm:col-span-2">Due days 29–31 use the last day in shorter months. Anniversary billing uses the billing start day. These terms will be the basis for monthly billing.</p>
            <label for="firstMonthPolicy">First Month Policy *<select id="firstMonthPolicy" name="first_month_policy" required class="form-control mt-2"><option value="full_month" @selected(old('first_month_policy') !== 'prorated')>Full Month</option><option value="prorated" @selected(old('first_month_policy') === 'prorated')>Prorated · Agreed Amount</option></select></label>
            <label id="firstChargeField" for="firstMonthCharge">Agreed First Month Charge (₱) *<input id="firstMonthCharge" type="text" inputmode="decimal" name="first_month_charge" data-contract-money maxlength="20" value="{{ old('first_month_charge') }}" class="form-control mt-2"></label>
        </div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-1 text-base font-black text-slate-900">Deposit &amp; Advance Requirements</h3><p class="mb-4 text-xs leading-5 text-slate-500">Both default to one month of agreed rent and can be edited. These are requirements, not received payments. Enter 0 when none is required.</p><div class="grid gap-4 sm:grid-cols-2">
            <label>Security Deposit Required (₱) *<input type="text" inputmode="decimal" name="deposit_required" data-contract-money maxlength="20" required value="{{ old('deposit_required', old('monthly_rent', $tenant->unit->monthly_rent ?? $renewalOf?->monthly_rent)) }}" class="form-control mt-2"></label>
            <label>Advance Rent Required (₱) *<input type="text" inputmode="decimal" name="advance_required" data-contract-money maxlength="20" required value="{{ old('advance_required', old('monthly_rent', $tenant->unit->monthly_rent ?? $renewalOf?->monthly_rent)) }}" class="form-control mt-2"></label>
            <label class="sm:col-span-2">Advance Application / Covered Period<textarea name="advance_application" rows="2" maxlength="2000" placeholder="e.g. Apply to November 2026 rent. Required when advance is greater than zero." class="form-control mt-2">{{ old('advance_application') }}</textarea></label>
        </div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-4 text-base font-black text-slate-900">Document &amp; Terms</h3><label>Signed Contract<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control mt-2"><span class="mt-2 block text-xs font-normal text-slate-500">PDF or scanned image, up to 10 MB. You can attach the signed copy later if needed.</span></label><label class="mt-4">Terms / Remarks<textarea name="remarks" rows="3" maxlength="10000" class="form-control mt-2">{{ old('remarks', $renewalOf?->remarks) }}</textarea></label></section>
    </div><footer class="modal-footer"><button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button><button class="inline-flex min-h-11 items-center rounded-xl border-0 bg-teal-800 px-6 text-sm font-extrabold text-white hover:bg-teal-900">{{ $renewalOf ? 'Save Renewal' : 'Save Contract' }}</button></footer>
</form></div></div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const start = document.getElementById('leaseStart');
    const duration = document.getElementById('leaseDuration');
    const updateEnd = () => {
        const end = document.getElementById('leaseEnd');
        if (!start.value || !start.validity.valid) { end.value = ''; return; }
        const [year, month, day] = start.value.split('-').map(Number);
        const next = new Date(Date.UTC(year, month - 1 + Number(duration.value), 1));
        const lastDay = new Date(Date.UTC(next.getUTCFullYear(), next.getUTCMonth() + 1, 0)).getUTCDate();
        next.setUTCDate(Math.min(day, lastDay));
        next.setUTCDate(next.getUTCDate() - 1);
        end.value = next.toISOString().slice(0, 10);
    };
    start.addEventListener('input', updateEnd);
    duration.addEventListener('change', updateEnd);
    updateEnd();
    const billingStart = document.querySelector('[name="billing_starts_on"]');
    const firstDue = document.querySelector('[name="first_due_on"]');
    const dueDay = document.querySelector('[name="due_day"]');
    const editedSchedule = new Set();
    [billingStart, firstDue, dueDay].forEach(input => input.addEventListener('input', () => editedSchedule.add(input)));
    start.addEventListener('input', () => {
        if (!start.value || !start.validity.valid) return;
        const [year, month, day] = start.value.split('-').map(Number);
        const next = new Date(Date.UTC(year, month, 1));
        const lastDay = new Date(Date.UTC(next.getUTCFullYear(), next.getUTCMonth() + 1, 0)).getUTCDate();
        next.setUTCDate(Math.min(day, lastDay));
        const date = next.toISOString().slice(0, 10);
        if (!editedSchedule.has(billingStart)) billingStart.value = date;
        if (!editedSchedule.has(firstDue)) firstDue.value = billingStart.value;
        if (!editedSchedule.has(dueDay)) dueDay.value = Number(billingStart.value.slice(-2));
    });
    const billingCycle = document.querySelector('[name="billing_cycle"]');
    const syncDueDay = () => {
        const anniversary = billingCycle.value === 'lease_anniversary';
        dueDay.readOnly = anniversary;
        if (anniversary && billingStart.value) dueDay.value = Number(billingStart.value.slice(-2));
    };
    billingStart.addEventListener('input', () => {
        if (!billingStart.value || !billingStart.validity.valid) return;
        if (!editedSchedule.has(firstDue)) firstDue.value = billingStart.value;
        if (!editedSchedule.has(dueDay)) dueDay.value = Number(billingStart.value.slice(-2));
        syncDueDay();
    });
    billingCycle.addEventListener('change', syncDueDay);
    start.addEventListener('input', syncDueDay);
    syncDueDay();
    const policy = document.getElementById('firstMonthPolicy');
    const updateCharge = () => {
        const prorated = policy.value === 'prorated';
        document.getElementById('firstChargeField').style.display = prorated ? '' : 'none';
        document.getElementById('firstMonthCharge').required = prorated;
    };
    policy.addEventListener('change', updateCharge); updateCharge();
    document.querySelectorAll('[data-contract-money]').forEach(input => {
        const format = () => {
            const raw = input.value.replaceAll(',', '');
            const valid = /^\d*(?:\.\d{0,2})?$/.test(raw);
            input.setCustomValidity(valid ? '' : 'Enter a valid amount with up to two decimal places.');
            if (!valid || raw === '') return;
            const before = input.value.slice(0, input.selectionStart ?? input.value.length).replaceAll(',', '').length;
            const [whole, decimals] = raw.split('.');
            input.value = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (decimals !== undefined ? '.' + decimals : '');
            let cursor = 0, count = 0;
            while (cursor < input.value.length && count < before) { if (input.value[cursor] !== ',') count++; cursor++; }
            input.setSelectionRange(cursor, cursor);
        };
        input.addEventListener('input', format); format();
    });
    const rent = document.getElementById('contractRent');
    const requirements = ['deposit_required', 'advance_required'].map(name => document.querySelector(`[name="${name}"]`));
    const manuallyEdited = new Set();
    requirements.forEach(input => {
        if (input.value.replaceAll(',', '') !== rent.value.replaceAll(',', '')) manuallyEdited.add(input);
        input.addEventListener('input', event => { if (event.isTrusted) manuallyEdited.add(input); });
    });
    rent.addEventListener('input', () => {
        requirements.forEach(input => {
            if (manuallyEdited.has(input)) return;
            input.value = rent.value;
            input.dispatchEvent(new Event('input'));
        });
    });
    @if($renewalOf || $errors->contractForm->any())
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('contractModal')).show();
    @endif
});
</script>
@endpush

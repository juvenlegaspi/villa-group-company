@php
    $isUpdate = isset($billing) && $billing->exists;
    $rent = $isUpdate ? $billing->rent : $draftPeriod['period']['rent'];
@endphp
<div class="modal" id="billingModal" tabindex="-1" role="dialog" aria-labelledby="billingModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form method="POST" action="{{ $isUpdate ? route('haruhay.billing.update', $billing) : route('haruhay.billing.store') }}" class="modal-content">
@csrf
@if($isUpdate)@method('PUT')@else<input type="hidden" name="contract_id" value="{{ $draftPeriod['contract']->id }}"><input type="hidden" name="period_start" value="{{ $draftPeriod['period']['start'] }}">@endif
<header class="modal-header"><div><p class="mb-1 text-xs font-bold uppercase text-teal-700">{{ $isUpdate ? $billing->tenant_name : $tenant->name }}</p><h2 id="billingModalTitle" class="modal-title">Update Monthly Billing</h2><p class="mb-0 mt-1 text-xs text-slate-500">{{ $isUpdate ? $billing->period_start->format('M d, Y').' – '.$billing->period_end->format('M d, Y') : $draftPeriod['period']['start'].' – '.$draftPeriod['period']['end'] }}</p></div><button type="button" data-bs-dismiss="modal" aria-label="Close billing form" class="ml-auto grid h-10 w-10 place-items-center rounded-xl border bg-white text-xl">&times;</button></header>
<div class="modal-body space-y-4 bg-slate-50">
@if($errors->billingForm->any())<div role="alert" class="rounded-xl bg-rose-50 p-3 text-xs text-rose-800"><ul class="mb-0">@foreach($errors->billingForm->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="rounded-xl bg-teal-50 p-3 text-xs text-teal-900">Rent from contract: <strong>₱{{ number_format((float) $rent, 2) }}</strong>. Due: {{ $isUpdate ? $billing->due_on->format('M d, Y') : $draftPeriod['period']['due'] }}</div>
<section class="rounded-xl border border-slate-200 bg-white p-4"><h3 class="mb-3 text-sm font-black">Utilities &amp; Other Charges</h3><p class="mb-3 text-xs text-slate-500">Leave water and electric blank while computation is pending. Enter 0 when no charge applies.</p><div class="grid gap-3 sm:grid-cols-2">@foreach($charges as $field => $label)<label class="text-xs font-bold">{{ $label }} (₱)<input name="{{ $field }}" type="text" inputmode="decimal" data-invoice-charge maxlength="20" @required(!in_array($field, ['water','electric'])) value="{{ old($field, $isUpdate ? $billing->$field : (in_array($field, ['water','electric']) ? '' : '0')) }}" placeholder="{{ in_array($field, ['water','electric']) ? 'Pending computation' : '0' }}" class="form-control mt-1"></label>@endforeach</div><label class="mt-3 block text-xs font-bold">Remarks / Charge Details<textarea name="remarks" rows="2" maxlength="5000" class="form-control mt-1">{{ old('remarks', $isUpdate ? $billing->remarks : '') }}</textarea></label><label class="mt-4 flex items-start gap-2 text-xs"><input type="checkbox" name="utilities_ready" value="1" @checked(old('utilities_ready', $isUpdate && $billing->utilities_ready))><span>All charges have been checked and are ready for final billing.</span></label></section>
<div class="flex justify-between rounded-xl bg-teal-50 p-4 text-teal-900"><span class="text-xs font-bold">Provisional Total</span><strong id="invoiceTotal" aria-live="polite">₱{{ number_format((float) $rent, 2) }}</strong></div><p class="mb-0 text-xs text-slate-500">Save the draft first, then issue the bill when all charges are complete. Available tenant credit is applied automatically on issue. Issued charges are locked.</p>
</div><footer class="modal-footer"><button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button><button class="btn bg-teal-800 text-white">Save Draft</button></footer></form></div></div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rent = Number(@json($rent));
    const money = value => Number(value).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    const update = () => {
        let cents = Math.round(rent * 100);
        document.querySelectorAll('[data-invoice-charge]').forEach(input => {
            const raw = input.value.replaceAll(',', '');
            const valid = raw === '' ? !input.required : /^\d+(?:\.\d{0,2})?$/.test(raw);
            input.setCustomValidity(valid ? '' : 'Enter a valid amount with up to two decimals.');
            cents += (input.name === 'discount' ? -1 : 1) * (valid ? Math.round(Number(raw) * 100) : 0);
        });
        document.getElementById('invoiceTotal').textContent = '₱' + money(cents / 100);
    };
    document.querySelectorAll('[data-invoice-charge]').forEach(input => {
        const format = () => {
            const raw = input.value.replaceAll(',', '');
            if (/^\d+(?:\.\d{0,2})?$/.test(raw)) {
                const position = input.selectionStart, length = input.value.length;
                const [whole, decimal] = raw.split('.');
                input.value = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (decimal === undefined ? '' : '.' + decimal);
                const next = Math.max(0, (position ?? 0) + input.value.length - length); input.setSelectionRange(next, next);
            }
            update();
        };
        input.addEventListener('input', format); format();
    });
    update();
    @if(($autoOpen ?? false) || $errors->billingForm->any())
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById('billingModal')).show();
    @endif
});
</script>
@endpush

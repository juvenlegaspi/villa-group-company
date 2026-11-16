<div class="modal" id="collectionModal" tabindex="-1" role="dialog" aria-labelledby="collectionModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form method="POST" enctype="multipart/form-data" action="{{ route('haruhay.collections.store', $tenant) }}" class="modal-content">
@csrf<input id="receiptType" type="hidden" name="receipt_type" value="{{ old('receipt_type', $currentBill ? 'payment' : 'advance') }}"><input type="hidden" name="submission_token" value="{{ old('submission_token', (string) str()->uuid()) }}">
<header class="modal-header"><div><p class="mb-1 text-xs font-bold uppercase text-teal-700">{{ $tenant->code }} · {{ $tenant->name }}</p><h2 id="collectionModalTitle" class="modal-title">Record Payment</h2><p class="mb-0 mt-1 text-xs text-slate-500">OR number and received-by user are assigned on save.</p></div><button type="button" data-bs-dismiss="modal" aria-label="Close payment form" class="ml-auto grid h-10 w-10 place-items-center rounded-xl border bg-white text-xl">&times;</button></header>
<div class="modal-body space-y-4 bg-slate-50">@if($errors->collectionForm->any())<div role="alert" class="rounded-xl bg-rose-50 p-3 text-xs text-rose-800"><ul class="mb-0">@foreach($errors->collectionForm->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="rounded-xl border border-slate-200 bg-white p-4"><div class="grid gap-3 sm:grid-cols-2"><label id="receiptBillField" class="text-xs font-bold sm:col-span-2">Apply Payment To<select name="billing_id" id="receiptBill" class="form-control mt-1">@if($currentBill)<option value="{{ $currentBill->id }}" @selected(old('billing_id', $currentBill->id) == $currentBill->id)>{{ $currentBill->code }} · {{ $currentBill->period_start->format('M Y') }} · Balance ₱{{ number_format($currentBill->balance_cents / 100, 2) }}</option>@endif
@if(!$currentBill)<option value="">No issued current bill</option>@endif</select></label><label class="text-xs font-bold">Collection Date *<input type="date" name="paid_on" required max="{{ today()->toDateString() }}" value="{{ old('paid_on', today()->toDateString()) }}" class="form-control mt-1"></label><label class="text-xs font-bold">Amount Received (₱) *<input type="text" inputmode="decimal" id="receiptAmount" name="amount" required maxlength="20" value="{{ old('amount', $currentBill ? number_format($currentBill->balance_cents / 100, 2) : '') }}" placeholder="e.g. 10,000" class="form-control mt-1"></label><label class="text-xs font-bold">Payment Method *<select name="method" id="receiptMethod" required class="form-control mt-1">@foreach($methods as $method)<option @selected(old('method', 'Cash') === $method)>{{ $method }}</option>@endforeach</select></label><label class="text-xs font-bold">Reference No.<input type="text" id="receiptReference" name="external_reference" maxlength="100" value="{{ old('external_reference') }}" class="form-control mt-1"><span class="mt-1 block text-[.65rem] font-normal text-slate-500">Required for non-cash payments.</span></label><label id="receiptProofField" class="text-xs font-bold sm:col-span-2" hidden>Proof of Payment *<input type="file" id="receiptProof" name="proof" accept=".jpg,.jpeg,.png,.webp" class="form-control mt-1"><span class="mt-1 block text-[.65rem] font-normal text-slate-500">Upload the transaction screenshot or receipt image. JPG, PNG or WebP, up to 10 MB. Required for Bank Transfer, GCash and Maya. Reselect the image if the form shows an error.</span></label><label class="text-xs font-bold sm:col-span-2">Remarks<textarea name="remarks" rows="2" maxlength="5000" class="form-control mt-1">{{ old('remarks') }}</textarea></label></div></section>
<div class="rounded-xl bg-teal-50 p-4 text-xs text-teal-900" aria-live="polite"><div class="flex justify-between"><span>Applied to Bill</span><strong id="receiptApplied">₱0.00</strong></div><div class="mt-2 flex justify-between"><span>Unapplied Credit</span><strong id="receiptCredit">₱0.00</strong></div></div><p class="mb-0 text-xs leading-5 text-slate-500"><span id="receiptModeHint">Only the remaining invoice balance is applied. Any excess stays as tenant credit.</span> Saving records an actual received payment; verify the amount and reference first.</p>
</div><footer class="modal-footer"><button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button><button id="receiptSave" class="btn bg-teal-800 text-white">Save Payment</button></footer></form></div></div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const amount = document.getElementById('receiptAmount'), bill = document.getElementById('receiptBill'), method = document.getElementById('receiptMethod');
    const balance = @json($currentBill?->balance_cents ?? 0);
    const money = cents => '₱' + (cents / 100).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    const update = () => {
        const raw = amount.value.replaceAll(',', '');
        const valid = /^\d+(?:\.\d{0,2})?$/.test(raw) && Number(raw) > 0;
        amount.setCustomValidity(valid ? '' : 'Enter an amount greater than zero with up to two decimals.');
        const received = valid ? Math.round(Number(raw) * 100) : 0;
        const applied = document.getElementById('receiptType').value === 'payment' && bill.value ? Math.min(received, balance) : 0;
        document.getElementById('receiptApplied').textContent = money(applied);
        document.getElementById('receiptCredit').textContent = money(received - applied);
        document.getElementById('receiptReference').required = method.value !== 'Cash';
        const online = ['Bank Transfer', 'GCash', 'Maya'].includes(method.value);
        document.getElementById('receiptProofField').hidden = !online;
        document.getElementById('receiptProof').required = online;
        document.getElementById('receiptProof').disabled = !online;
    };
    amount.addEventListener('input', () => {
        const raw = amount.value.replaceAll(',', '');
        if (/^\d+(?:\.\d{0,2})?$/.test(raw)) {
            const position = amount.selectionStart, length = amount.value.length;
            const [whole, decimals] = raw.split('.');
            amount.value = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (decimals === undefined ? '' : '.' + decimals);
            const next = Math.max(0, (position ?? 0) + amount.value.length - length); amount.setSelectionRange(next, next);
        }
        update();
    });
    const mode = document.getElementById('receiptType');
    const setMode = (value, preserveAmount = false) => {
        const advance = value === 'advance';
        mode.value = value;
        bill.disabled = advance;
        bill.required = !advance;
        document.getElementById('receiptBillField').hidden = advance;
        document.getElementById('collectionModalTitle').textContent = advance ? 'Receive Advance' : 'Record Payment';
        document.getElementById('receiptSave').textContent = advance ? 'Save Advance' : 'Save Payment';
        document.getElementById('receiptModeHint').textContent = advance ? 'The full amount stays as tenant credit. Available credit is applied automatically when the completed monthly bill is issued.' : 'Only the remaining invoice balance is applied. Any excess stays as tenant credit.';
        if (!preserveAmount) {
            const defaultCents = advance ? Math.round(Number(@json($current['period']['rent'] ?? 0)) * 100) : balance;
            amount.value = defaultCents > 0 ? (defaultCents / 100).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
        }
        update();
    };
    document.querySelectorAll('[data-receipt-mode]').forEach(button => button.addEventListener('click', () => setMode(button.dataset.receiptMode)));
    setMode(mode.value, true);
    bill.addEventListener('change', update); method.addEventListener('change', update); update();
    @if($errors->collectionForm->any())
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById('collectionModal')).show();
    @endif
});
</script>
@endpush

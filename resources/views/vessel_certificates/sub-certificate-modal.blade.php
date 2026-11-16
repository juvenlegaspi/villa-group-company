<dialog id="sub-certificate-modal" class="certificate-calendar-dialog" style="max-width:760px" aria-labelledby="sub-certificate-modal-title">
    <header class="certificate-calendar-header">
        <div><p>VESSEL CERTIFICATE COMPLIANCE</p><h2 id="sub-certificate-modal-title">Add sub-certificate / Annual audit</h2></div>
        <button type="button" data-sub-certificate-close aria-label="Close sub-certificate form">&times;</button>
    </header>
    <form method="POST" enctype="multipart/form-data" data-sub-certificate-form class="p-5 sm:p-6">
        @csrf
        <input type="hidden" name="vessel_id" value="{{ $vessel->id }}">
        <div class="mb-5 rounded-xl bg-blue-50 p-3 text-sm text-blue-900"><strong>Main certificate: <span data-sub-certificate-parent></span></strong><p class="mt-1 text-xs">{{ $vessel->vessel_name }} · Enter this audit's dates and document. The main certificate expiry remains unchanged.</p></div>
        <div data-sub-certificate-errors class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert" hidden></div>
        <p data-sub-certificate-note class="mb-4 text-sm text-slate-600"></p>
        <a data-sub-certificate-document hidden class="mb-4 inline-block text-sm font-bold text-villa-700" target="_blank" rel="noopener"></a>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-bold text-slate-700 sm:col-span-2">Sub-certificate name *<input autofocus required name="certificate_name" maxlength="255" placeholder="e.g. Annual Audit 2027" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-bold text-slate-700">Certificate number<input name="certificate_number" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-bold text-slate-700">Certificate type<input name="certificate_type" maxlength="255" placeholder="Annual audit, endorsement, etc." class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-bold text-slate-700 sm:col-span-2">Issuing authority<input name="issuing_authority" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-bold text-slate-700">Audit / Issue date *<input type="date" required name="issue_date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-bold text-slate-700">Expiry / Next due date *<input type="date" required name="expiry_date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            <label class="block text-sm font-bold text-slate-700 sm:col-span-2">Remarks<textarea name="remarks" maxlength="2000" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></textarea></label>
            <label class="block text-sm font-bold text-slate-700 sm:col-span-2">Certificate document <span class="text-xs font-normal">(required for Add / Renew)</span><input type="file" required name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-lg border border-slate-300 p-2 text-sm font-normal"><span class="mt-1 block text-xs font-normal text-slate-500">PDF, Word, Excel, JPG, or PNG · Maximum 5 MB.</span></label>
        </div>
        <footer class="mt-5 flex justify-end gap-2 border-t border-slate-200 pt-4">
            <button type="button" data-sub-certificate-close class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold">Cancel</button>
            <button type="submit" class="rounded-lg bg-villa-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Save sub-certificate</button>
        </footer>
    </form>
</dialog>

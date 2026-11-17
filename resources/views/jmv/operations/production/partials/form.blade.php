@php
    $current = $log ?? null;
    $locationOptions = config('jmv.production_locations');
    $shiftOptions = config('jmv.production_shifts');
    $selectedLocation = old('location', $current?->location);
    $selectedShift = old('shift', $current?->shift);
@endphp
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <label class="block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Date <b class="text-rose-600">*</b></span><input type="date" name="log_date" max="{{ now()->format('Y-m-d') }}" value="{{ old('log_date', $current?->log_date?->format('Y-m-d') ?? '') }}" required class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></label>
    <label class="block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Location <b class="text-rose-600">*</b></span><select name="location" required class="min-h-11 w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3"><option value="">Select mine</option>@if($current?->exists && filled($selectedLocation) && !in_array($selectedLocation, $locationOptions, true))<option selected value="{{ $selectedLocation }}">{{ $selectedLocation }} (current)</option>@endif @foreach($locationOptions as $locationOption)<option value="{{ $locationOption }}" @selected($selectedLocation === $locationOption)>{{ $locationOption }}</option>@endforeach</select></label>
    <label class="block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Shift <b class="text-rose-600">*</b></span><select name="shift" required class="min-h-11 w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3"><option value="">Select shift</option>@if($current?->exists && filled($selectedShift) && !array_key_exists($selectedShift, $shiftOptions))<option selected value="{{ $selectedShift }}">{{ $selectedShift }} (current)</option>@endif @foreach($shiftOptions as $shiftValue => $shiftLabel)<option value="{{ $shiftValue }}" @selected($selectedShift === $shiftValue)>{{ $shiftLabel }}</option>@endforeach</select></label>
    <label class="block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Leadman <b class="text-rose-600">*</b></span><input name="leadman" maxlength="255" value="{{ old('leadman', $current?->leadman) }}" required placeholder="Given Name, Family Name" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></label>
    <label class="block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Foreman <b class="text-rose-600">*</b></span><input name="foreman" maxlength="255" value="{{ old('foreman', $current?->foreman) }}" required placeholder="Given Name, Family Name" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></label>
    <label class="block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Total Workers <b class="text-rose-600">*</b></span><input type="number" min="1" name="total_workers" value="{{ old('total_workers', $current?->total_workers) }}" required placeholder="No." class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></label>
</div>

<div class="my-5 border-t border-slate-200 pt-5"><h3 class="mb-4 text-sm font-black uppercase tracking-wider text-villa-800">Additional details</h3>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach([['timber_used','timber_unit','Timber Used'],['nails_used','nails_unit','Nail(s) Used']] as [$amount,$unit,$label])
        <div><span class="mb-1 block text-sm font-extrabold text-slate-700">{{ $label }} <b class="text-rose-600">*</b></span><div class="flex"><input type="number" min="0" step="0.01" name="{{ $amount }}" value="{{ old($amount, $current?->{$amount} ?? '') }}" required class="min-h-11 min-w-0 flex-1 rounded-l-xl border border-r-0 border-slate-300 px-3"><select name="{{ $unit }}" required class="min-h-11 rounded-r-xl border border-slate-300 bg-blue-50 px-3 font-bold"><option value="PCS" @selected(old($unit,$current?->{$unit} ?? 'PCS')==='PCS')>PCS</option><option value="KG" @selected(old($unit,$current?->{$unit})==='KG')>KG</option></select></div></div>
        @endforeach
        <div><span class="mb-1 block text-sm font-extrabold text-slate-700">Fuel <b class="text-rose-600">*</b></span><div class="flex"><input type="number" min="0" step="0.01" name="fuel_consumed" value="{{ old('fuel_consumed', $current?->fuel_consumed ?? '') }}" required class="min-h-11 min-w-0 flex-1 rounded-l-xl border border-r-0 border-slate-300 px-3"><input type="hidden" name="fuel_unit" value="LTRS"><span class="grid min-h-11 place-items-center rounded-r-xl border border-slate-300 bg-blue-50 px-4 font-bold text-slate-600">LTRS</span></div></div>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-3">
    <label class="block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Total Soil Output (Bagon) <b class="text-rose-600">*</b></span><input type="number" min="0" name="soil_output" value="{{ old('soil_output', $current?->soil_output ?? '') }}" required class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></label>
    <label class="block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Total Coal Output (Bagon) <b class="text-rose-600">*</b></span><input type="number" min="0" name="coal_output" value="{{ old('coal_output', $current?->coal_output ?? '') }}" required class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></label>
    @php($dailyTarget = $current?->daily_target_bagons ?? ($productionTarget ?? null)?->coal_target)
    <div><span class="mb-1 block text-sm font-extrabold text-slate-700">Excess / Deficit (automatic)</span><output data-production-variance data-daily-target="{{ $dailyTarget }}" class="flex min-h-11 w-full items-center rounded-xl border border-slate-200 bg-slate-100 px-3 font-bold" aria-live="polite">{{ $dailyTarget === null ? 'Target not set' : 'Enter coal output' }}</output><small class="mt-1 block text-xs text-slate-500">Daily target {{ $dailyTarget === null ? '(not set)' : '('.number_format($dailyTarget).' Bagon)' }} − actual coal output. Positive = deficit; negative = excess.</small></div>
</div>
<label class="mt-4 block"><span class="mb-1 block text-sm font-extrabold text-slate-700">Remarks</span><textarea name="remarks" rows="3" maxlength="3000" class="w-full rounded-xl border border-slate-300 px-3 py-2">{{ old('remarks', $current?->remarks) }}</textarea></label>
{{-- Keep the native file button visible even when the hosted CSS build is older. --}}
<style>
    #productionAttachments::file-selector-button {
        margin-right: 12px;
        padding: 10px 16px;
        border: 0;
        border-radius: 8px;
        background: #1d4ed8;
        color: #fff;
        font: inherit;
        font-weight: 700;
        opacity: 1;
        cursor: pointer;
    }
    #productionAttachments:hover::file-selector-button { background: #1e40af; }
    #productionAttachments:focus-visible { outline: 2px solid #1d4ed8; outline-offset: 3px; }
</style>
<label for="productionAttachments" class="mt-4 block cursor-pointer rounded-2xl border-2 border-dashed border-blue-300 bg-blue-50 p-5 transition hover:border-blue-600 hover:bg-blue-100 focus-within:border-blue-600 focus-within:ring-2 focus-within:ring-blue-200">
    <span class="mb-4 flex items-center gap-3"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-700 text-white"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m-4 4 4-4 4 4M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg></span><span><span class="block text-sm font-extrabold text-blue-950">Upload Daily Accomplishment Report</span><span class="mt-1 block text-xs text-blue-800">Click here or choose files below to attach your supporting documents.</span></span></span>
    <input id="productionAttachments" type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" aria-describedby="productionAttachmentsHelp" class="block w-full cursor-pointer rounded-xl border border-blue-200 bg-white p-2 text-sm text-slate-700 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-blue-700 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-white hover:file:bg-blue-800">
    <small id="productionAttachmentsHelp" class="mt-3 block text-xs leading-5 text-slate-600">PDF, JPG, PNG, Word or Excel. Up to 5 files per save, maximum 10 MB each. At least one supporting document is required before submission.</small>
</label>

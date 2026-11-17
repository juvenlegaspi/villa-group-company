<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-1 text-base font-black text-slate-900">Property Details</h2>
    <p class="mb-5 text-xs text-slate-500">Property ID, date added and occupancy figures are generated automatically.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        @if($isCorporateProperty ?? false)
            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4 sm:col-span-2">
                <input type="hidden" name="use_in_haruhay" value="0">
                <label class="flex items-center gap-2 text-sm font-bold text-teal-900"><input type="checkbox" name="use_in_haruhay" value="1" @checked(old('use_in_haruhay', $property->exists && (int) $property->division_id === (int) $haruhayDivisionId))>Use this property in Haruhay</label>
                <p class="mt-2 text-xs text-teal-800">Enable this to select the property when adding rooms in Haruhay. The property remains listed in Villa Group Properties.</p>
                @error('use_in_haruhay')<p class="mt-2 text-xs font-bold text-rose-700">{{ $message }}</p>@enderror
            </div>
        @endif
        <label for="name" class="text-sm font-bold text-slate-700 sm:col-span-2">Property Name *<input id="name" name="name" value="{{ old('name', $property->name) }}" required maxlength="255" placeholder="e.g. Haruhay Space T. Padilla" class="form-control mt-2"></label>
        <label for="location" class="text-sm font-bold text-slate-700 sm:col-span-2">Location *<textarea id="location" name="location" required rows="2" maxlength="2000" placeholder="Full address / location" class="form-control mt-2">{{ old('location', $property->location) }}</textarea></label>
        <label for="type" class="text-sm font-bold text-slate-700">Property Type *<select id="type" name="type" required class="form-control mt-2"><option value="">Select property type</option>@foreach($types as $type)<option @selected(old('type', $property->type) === $type)>{{ $type }}</option>@endforeach</select></label>
        <label for="status" class="text-sm font-bold text-slate-700">Status *<select id="status" name="status" required class="form-control mt-2"><option value="">Select status</option>@foreach($statuses as $status)<option @selected(old('status', $property->status ?? 'Active') === $status)>{{ $status }}</option>@endforeach</select></label>
        <label for="person_in_charge" class="text-sm font-bold text-slate-700 sm:col-span-2">Property Manager<input id="person_in_charge" name="person_in_charge" value="{{ old('person_in_charge', $property->person_in_charge) }}" maxlength="255" placeholder="Manager's name" class="form-control mt-2"></label>
        <label for="owner" class="text-sm font-bold text-slate-700">Registered Owner<input id="owner" name="owner" value="{{ old('owner', $property->owner) }}" maxlength="255" placeholder="Optional" class="form-control mt-2"></label>
        <label for="floors" class="text-sm font-bold text-slate-700">Number of Floors<input id="floors" type="number" name="floors" min="1" max="999" step="1" value="{{ old('floors', $property->floors) }}" placeholder="Optional" class="form-control mt-2"></label>
        <label for="lot_area" class="text-sm font-bold text-slate-700">Lot Area (sq.m)<input id="lot_area" type="number" name="lot_area" min="0" max="999999999999.99" step="0.01" value="{{ old('lot_area', $property->lot_area) }}" placeholder="Optional" class="form-control mt-2"></label>
        <label for="floor_area" class="text-sm font-bold text-slate-700">Floor Area (sq.m)<input id="floor_area" type="number" name="floor_area" min="0" max="999999999999.99" step="0.01" value="{{ old('floor_area', $property->floor_area) }}" placeholder="Optional" class="form-control mt-2"></label>
        <label for="remarks" class="text-sm font-bold text-slate-700 sm:col-span-2">Description / Remarks<textarea id="remarks" name="remarks" rows="3" maxlength="10000" placeholder="Optional property description or notes" class="form-control mt-2">{{ old('remarks', $property->remarks) }}</textarea></label>
    </div>
</section>

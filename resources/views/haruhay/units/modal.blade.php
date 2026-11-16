<div class="modal" id="unitModal" tabindex="-1" role="dialog" aria-labelledby="unitModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" action="{{ $room->exists ? route('haruhay.units.update', $room) : route('haruhay.units.store') }}" class="modal-content">
        @csrf @if($room->exists) @method('PUT') @endif
        <header class="modal-header"><div><p class="mb-1 text-xs font-bold uppercase tracking-widest text-teal-700">Haruhay Space</p><h2 class="modal-title" id="unitModalTitle">{{ $room->exists ? 'Edit Room · '.$room->unit_no : 'Add Room' }}</h2><p class="mb-0 mt-1 text-xs text-slate-500">Connect the room to a property and floor. Fields marked * are required.</p></div><button type="button" data-bs-dismiss="modal" aria-label="Close room form" class="ml-auto grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-xl text-slate-500">&times;</button></header>
        <div class="modal-body space-y-5 bg-slate-50">
            @if($errors->unitForm->any())<div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Please check the following:</strong><ul class="mb-0 mt-2">@foreach($errors->unitForm->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-4 text-base font-black text-slate-900">Room Details</h3><div class="grid gap-4 sm:grid-cols-2">
                <label for="roomProperty" class="sm:col-span-2">Property *<select id="roomProperty" name="property_id" required class="form-control mt-2"><option value="">Select property</option>@foreach($properties as $property)<option value="{{ $property->id }}" data-status="{{ $property->status }}" @selected((string) old('property_id', $room->property_id) === (string) $property->id)>{{ $property->name }} ({{ $property->code }})</option>@endforeach</select></label>
                <label for="roomNumber">Unit / Room No.<input id="roomNumber" value="{{ $room->exists ? $room->unit_no : 'Auto-generated on save' }}" readonly class="form-control mt-2 bg-slate-100"><span class="mt-1 block text-xs font-normal text-slate-500">DR- + room ID. Assigned automatically and stays the same when edited.</span></label>
                <label for="roomFloor">Floor *<input id="roomFloor" type="number" name="floor" min="0" max="999" value="{{ old('floor', $room->floor) }}" required placeholder="0 = Ground floor" class="form-control mt-2"></label>
                <label for="roomType" class="sm:col-span-2">Unit Type / Room Description *<input id="roomType" name="unit_type" value="{{ old('unit_type', $room->unit_type) }}" required maxlength="255" placeholder="e.g. 2 single beds, air-conditioned, with CR" class="form-control mt-2"></label>
                <label for="roomArea">Area (sq.m)<input id="roomArea" type="number" name="area" min="0" max="9999999999.99" step="0.01" value="{{ old('area', $room->area) }}" class="form-control mt-2"></label>
                <label for="roomCapacity">Maximum Occupants<input id="roomCapacity" type="number" name="capacity" min="1" max="999" value="{{ old('capacity', $room->capacity) }}" class="form-control mt-2"></label>
            </div></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-1 text-base font-black text-slate-900">Monthly Rental Rate</h3><p class="mb-4 text-xs text-slate-500">Leave the rate blank if pricing is not yet available. Amounts are formatted automatically, e.g. 10,000.</p><div class="grid gap-4 sm:grid-cols-2">
                <label for="rentBasis">Rate Basis *<select id="rentBasis" name="rent_basis" class="form-control mt-2" required>@foreach($rentBases as $value => $label)<option value="{{ $value }}" @selected(old('rent_basis', $room->rent_basis ?? 'per_room') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label for="monthlyRent"><span id="monthlyRentLabel">Monthly Rent (₱)</span><input id="monthlyRent" type="text" inputmode="decimal" name="monthly_rent" maxlength="20" data-rent-amount value="{{ old('monthly_rent', $room->monthly_rent) }}" class="form-control mt-2"></label>
                <label for="sharedRate" id="sharedRateField" class="sm:col-span-2">Shared Occupancy Rate per Person / Month (₱)<input id="sharedRate" type="text" inputmode="decimal" name="shared_rate" maxlength="20" data-rent-amount value="{{ old('shared_rate', $room->shared_rate) }}" class="form-control mt-2"><span class="mt-2 block text-xs font-normal text-slate-500">For example: ₱5,000 single occupancy, or ₱3,000 per person when two people share the room.</span></label>
                <label for="rateNotes" class="sm:col-span-2">Rate Notes<textarea id="rateNotes" name="rate_notes" rows="2" maxlength="2000" placeholder="e.g. ₱12,000 per room, good for 4 pax" class="form-control mt-2">{{ old('rate_notes', $room->rate_notes) }}</textarea></label>
            </div></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-4 text-base font-black text-slate-900">Occupancy &amp; Remarks</h3><div class="grid gap-4 sm:grid-cols-2">
                <label for="roomStatus">Occupancy Status<input id="roomStatus" readonly value="{{ $room->exists ? $room->status : 'Vacant' }}" class="form-control mt-2 bg-slate-100"><span class="mt-2 block text-xs font-normal text-slate-500">New rooms start as Vacant. Occupancy will be managed through tenant assignments.</span></label>
                <label for="roomCondition">Room Condition *<select id="roomCondition" name="room_condition" required class="form-control mt-2">@foreach($conditions as $condition)<option @selected(old('room_condition', $room->room_condition ?? 'Ready') === $condition)>{{ $condition }}</option>@endforeach</select></label>
                @if($room->exists && $room->status === 'Occupied')
                <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2"><label>Occupied By / Tenant<textarea readonly rows="2" class="form-control mt-2 bg-slate-100">{{ $room->occupied_by }}</textarea></label><label>Move-in Date<input readonly value="{{ $room->move_in_date?->format('M d, Y') }}" class="form-control mt-2 bg-slate-100"></label></div>
                @endif
                <label for="roomPropertyStatus" class="sm:col-span-2">Property Status<input id="roomPropertyStatus" readonly class="form-control mt-2 bg-slate-100" placeholder="Select a property first"><span class="mt-2 block text-xs font-normal text-slate-500">Automatically follows the selected property's current status.</span></label>
                <label for="roomRemarks" class="sm:col-span-2">Remarks<textarea id="roomRemarks" name="remarks" rows="3" maxlength="10000" class="form-control mt-2" placeholder="Optional notes about this room">{{ old('remarks', $room->remarks) }}</textarea></label>
            </div></section>
        </div>
        <footer class="modal-footer"><button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button><button class="inline-flex min-h-11 items-center rounded-xl border-0 bg-teal-800 px-6 text-sm font-extrabold text-white hover:bg-teal-900" @disabled($properties->isEmpty())>{{ $room->exists ? 'Save Changes' : 'Save Room' }}</button></footer>
    </form>
</div></div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-rent-amount]').forEach(input => {
        const formatAmount = () => {
            const raw = input.value.replaceAll(',', '');
            const valid = /^\d*(?:\.\d{0,2})?$/.test(raw);
            input.setCustomValidity(valid ? '' : 'Enter a valid amount with up to two decimal places.');
            if (!valid || raw === '') return;
            const cursor = input.selectionStart ?? input.value.length;
            const charactersBeforeCursor = input.value.slice(0, cursor).replaceAll(',', '').length;
            const [whole, fraction] = raw.split('.');
            input.value = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (fraction !== undefined ? '.' + fraction : '');
            let position = 0, characters = 0;
            while (position < input.value.length && characters < charactersBeforeCursor) {
                if (input.value[position] !== ',') characters++;
                position++;
            }
            input.setSelectionRange(position, position);
        };
        input.addEventListener('input', formatAmount);
        formatAmount();
    });
    const basis = document.getElementById('rentBasis');
    const property = document.getElementById('roomProperty');
    const updatePropertyStatus = () => {
        document.getElementById('roomPropertyStatus').value = property.selectedOptions[0]?.dataset.status || '';
    };
    property.addEventListener('change', updatePropertyStatus);
    updatePropertyStatus();
    const updateForm = () => {
        const shared = basis.value === 'occupancy_based';
        document.getElementById('sharedRateField').style.display = shared ? '' : 'none';
        document.getElementById('sharedRate').required = shared;
        document.getElementById('monthlyRent').required = shared;
        document.getElementById('monthlyRentLabel').textContent = shared ? 'Single Occupancy / Month (₱) *' : (basis.value === 'per_person' ? 'Monthly Rent per Person (₱)' : 'Monthly Rent per Room (₱)');
    };
    basis.addEventListener('change', updateForm);
    updateForm();
    @if($room->exists || $errors->unitForm->any() || request('add') === '1')
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('unitModal')).show();
    @endif
});
</script>
@endpush

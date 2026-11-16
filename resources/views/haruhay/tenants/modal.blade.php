@php
    $selectableRooms = $rooms->filter(fn ($room) => $room->available_slots > 0
        || ($person->exists && (int) $person->unit_id === (int) $room->id));
@endphp
<div class="modal" id="tenantModal" tabindex="-1" role="dialog" aria-labelledby="tenantModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form method="POST" action="{{ $person->exists ? route('haruhay.tenants.update', $person) : route('haruhay.tenants.store') }}" class="modal-content">
    @csrf @if($person->exists) @method('PUT') @endif
    <header class="modal-header"><div><p class="mb-1 text-xs font-bold uppercase tracking-widest text-teal-700">Haruhay Space</p><h2 class="modal-title" id="tenantModalTitle">{{ $person->exists ? 'Edit Tenant · '.$person->code : 'Add Tenant' }}</h2><p class="mb-0 mt-1 text-xs text-slate-500">Tenant ID is generated automatically. Fields marked * are required.</p></div><button type="button" data-bs-dismiss="modal" aria-label="Close tenant form" class="ml-auto grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-xl text-slate-500">&times;</button></header>
    <div class="modal-body space-y-5 bg-slate-50">
        @if($errors->tenantForm->any())<div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Please check the following:</strong><ul class="mb-0 mt-2">@foreach($errors->tenantForm->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-4 text-base font-black text-slate-900">Tenant Information</h3><div class="grid gap-4 sm:grid-cols-2">
            <label for="tenantName" class="sm:col-span-2">Full Name *<input id="tenantName" name="name" value="{{ old('name', $person->name) }}" maxlength="255" required class="form-control mt-2"></label><label for="tenantContact">Contact No. *<input id="tenantContact" type="tel" name="contact_number" value="{{ old('contact_number', $person->contact_number) }}" maxlength="50" required class="form-control mt-2"></label><label for="tenantEmail">Email Address<input id="tenantEmail" type="email" name="email" value="{{ old('email', $person->email) }}" maxlength="255" class="form-control mt-2"></label>
            <label for="emergencyName">Emergency Contact Name<input id="emergencyName" name="emergency_contact_name" value="{{ old('emergency_contact_name', $person->emergency_contact_name) }}" maxlength="255" class="form-control mt-2"></label><label for="emergencyNumber">Emergency Contact No.<input id="emergencyNumber" type="tel" name="emergency_contact_number" value="{{ old('emergency_contact_number', $person->emergency_contact_number) }}" maxlength="50" class="form-control mt-2"></label>
        </div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-4 text-base font-black text-slate-900">Room Assignment</h3><div class="grid gap-4 sm:grid-cols-2">
            <label for="tenantProperty">Property *<select id="tenantProperty" name="property_id" required class="form-control mt-2"><option value="">Select property</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected((string) old('property_id', $person->unit?->property_id) === (string) $property->id)>{{ $property->name }}</option>@endforeach</select></label>
            <label for="tenantRoom">Unit / Room *<select id="tenantRoom" name="unit_id" required class="form-control mt-2"><option value="">Select room</option>@foreach($selectableRooms as $room)<option value="{{ $room->id }}" data-property="{{ $room->property_id }}" @selected((string) old('unit_id', $person->unit_id) === (string) $room->id)>{{ $room->unit_no }} · Floor {{ $room->floor }} · {{ $room->room_condition }} · {{ $room->occupants_count ?? 0 }} occupied / {{ $room->reservations_count ?? 0 }} reserved / {{ $room->available_slots }} available</option>@endforeach</select></label>
            <p class="mb-0 text-xs leading-5 text-slate-500 sm:col-span-2">Reservations and occupants share the room capacity. New assignments require a Ready room with an available slot. A room without a configured capacity is limited to one slot. Existing tenants can remain while a room is under maintenance.</p>
            <label for="tenantMoveIn"><span id="moveInLabel">Scheduled Move-in Date *</span><input id="tenantMoveIn" type="date" name="move_in_date" value="{{ old('move_in_date', ($person->move_in_date ?? $person->scheduled_move_in_date)?->toDateString()) }}" required class="form-control mt-2"></label>
            <label for="tenantStatus">Status *<select id="tenantStatus" name="status" required class="form-control mt-2">@foreach($statuses as $status)@continue(!in_array($status, !$person->exists ? ['Pending Move-in', 'Active', 'On Notice', 'Moved Out'] : ($person->status === 'Pending Move-in' ? ['Pending Move-in'] : ($person->status === 'Cancelled' ? ['Cancelled'] : ['Active', 'On Notice', 'Moved Out'])), true))<option @selected(old('status', $person->status ?? 'Pending Move-in') === $status)>{{ $status }}</option>@endforeach</select></label>
            <label id="moveOutField" for="tenantMoveOut" class="sm:col-span-2">Move-out Date *<input id="tenantMoveOut" type="date" name="move_out_date" value="{{ old('move_out_date', $person->move_out_date?->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control mt-2"></label>
        </div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="mb-2 text-base font-black text-slate-900">Contract</h3>@if($person->exists) @include('haruhay.contracts.summary', ['tenant' => $person]) @else <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-500">No Contract Yet</span> @endif<p class="mb-0 mt-3 text-xs leading-5 text-slate-500">Manage agreements and renewal history in Lease Contracts.</p></section>
        <label for="tenantRemarks">Remarks<textarea id="tenantRemarks" name="remarks" rows="3" maxlength="10000" class="form-control mt-2">{{ old('remarks', $person->remarks) }}</textarea></label>
    </div><footer class="modal-footer"><button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button><button class="inline-flex min-h-11 items-center rounded-xl border-0 bg-teal-800 px-6 text-sm font-extrabold text-white hover:bg-teal-900" @disabled($selectableRooms->isEmpty())>{{ $person->exists ? 'Save Changes' : 'Save Tenant' }}</button></footer>
</form></div></div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const property = document.getElementById('tenantProperty');
    const room = document.getElementById('tenantRoom');
    const status = document.getElementById('tenantStatus');
    const options = [...room.options];
    const filterRooms = () => {
        options.forEach(option => {
            const unavailable = Boolean(option.value && option.dataset.property !== property.value);
            option.hidden = unavailable;
            option.disabled = unavailable;
        });
        if (room.selectedOptions[0]?.disabled) room.value = '';
        const hasRoom = options.some(option => option.value && !option.disabled);
        room.options[0].textContent = !property.value ? 'Select a property first' : (hasRoom ? 'Select room' : 'No available rooms in this property');
    };
    const showMoveOut = () => {
        const pending = status.value === 'Pending Move-in' || status.value === 'Cancelled';
        document.getElementById('moveInLabel').textContent = pending ? 'Scheduled Move-in Date *' : 'Actual Move-in Date *';
        const moveIn = document.getElementById('tenantMoveIn');
        if (pending) moveIn.removeAttribute('max'); else moveIn.max = @json(today()->toDateString());
        const movedOut = status.value === 'Moved Out';
        document.getElementById('moveOutField').style.display = movedOut ? '' : 'none';
        document.getElementById('tenantMoveOut').required = movedOut;
    };
    property.addEventListener('change', filterRooms);
    status.addEventListener('change', showMoveOut);
    filterRooms(); showMoveOut();
    @if(request()->routeIs('haruhay.tenants.edit') || $errors->tenantForm->any() || request('add') === '1')
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('tenantModal')).show();
    @endif
});
</script>
@endpush

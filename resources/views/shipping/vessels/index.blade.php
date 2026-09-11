@extends('layouts.app')

@section('title', 'Vessel Management | Villa Shipping Lines')

@section('content')
@php
    $canCreateVessels = auth()->user()->isSystemAdministrator();
    $canManageVessels = app(\App\Services\VesselAccessService::class)->canAccessAllVessels(auth()->user());
@endphp

<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <header class="mx-auto mb-8 flex max-w-7xl flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-1 text-xs font-extrabold uppercase tracking-[.14em] text-villa-600">Operations</p>
            <h1 class="m-0 text-2xl font-extrabold tracking-tight text-villa-900 sm:text-3xl">Vessel List</h1>
            <p class="mt-1 text-sm text-slate-500">Select a vessel to view its voyages and operational records.</p>
        </div>

        @if($canCreateVessels)
            <a href="{{ route('vessels.create') }}" class="btn btn-primary">
                <i class="bi bi-plus" aria-hidden="true"></i> Add Vessel
            </a>
        @endif
    </header>

    @if(session('success'))
        <div class="alert alert-success mx-auto mb-5 max-w-7xl" role="status">{{ session('success') }}</div>
    @endif

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse($vessels as $vessel)
            @php
                $status = strtolower(trim((string) $vessel->vessel_status));
                $captainName = trim(($vessel->captain->name ?? '').' '.($vessel->captain->lastname ?? '')) ?: 'Not assigned';
            @endphp

            <article class="group relative flex min-h-32 items-center gap-4 rounded-[1.65rem] border-2 border-villa-700 bg-white p-3.5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl focus-within:ring-4 focus-within:ring-villa-100 sm:p-4">
                <a class="absolute inset-0 rounded-[1.55rem] focus:outline-none" href="{{ route('vessels.show', $vessel->id) }}" aria-label="Open {{ $vessel->vessel_name }}"></a>

                <span class="relative grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-sky-100 via-blue-50 to-villa-100 text-villa-700 sm:h-28 sm:w-28">
                    <svg class="h-14 w-14 transition group-hover:scale-110" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                        <path d="M32 7v13M22 20h20l5 17 10 5-6 12H13L7 42l10-5 5-17Z" fill="currentColor" opacity=".14"/>
                        <path d="M32 7v13M22 20h20l5 17 10 5-6 12H13L7 42l10-5 5-17ZM17 37h30M32 20v17" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 48c5 3 9 3 14 0 5 3 9 3 14 0 5 3 9 3 14 0" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </span>

                <div class="relative min-w-0 flex-1 pointer-events-none">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-extrabold text-slate-950 sm:text-lg">{{ $vessel->vessel_name }}</h2>
                            <span class="text-[.68rem] font-extrabold uppercase tracking-wider text-slate-400">VN-{{ str_pad($vessel->id, 3, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        @if($canManageVessels)
                            <button type="button" class="editBtn pointer-events-auto relative z-10 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-villa-700 shadow-sm transition hover:border-villa-500 hover:bg-villa-50 focus:outline-none focus:ring-4 focus:ring-villa-100" title="Edit vessel" aria-label="Edit {{ $vessel->vessel_name }}"
                                data-id="{{ $vessel->id }}" data-name="{{ $vessel->vessel_name }}" data-captain="{{ $vessel->captain_id }}"
                                data-imo="{{ $vessel->imo_number }}" data-call="{{ $vessel->call_sign }}" data-type="{{ $vessel->vessel_type }}"
                                data-dwt="{{ $vessel->dwt }}" data-fuel="{{ $vessel->fuel_type }}" data-speed="{{ $vessel->service_speed }}"
                                data-charter="{{ $vessel->charter_type }}" data-status="{{ $vessel->vessel_status }}">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                        @endif
                    </div>

                    <dl class="space-y-1 text-xs">
                        <div class="flex gap-1.5"><dt class="font-bold uppercase text-slate-400">Captain:</dt><dd class="truncate font-semibold text-slate-600">{{ $captainName }}</dd></div>
                        <div class="flex items-center gap-1.5"><dt class="font-bold uppercase text-slate-400">Status:</dt><dd @class([
                            'rounded-full px-2 py-0.5 text-[.66rem] font-extrabold uppercase tracking-wide',
                            'bg-emerald-50 text-emerald-700' => $status === 'operational',
                            'bg-amber-50 text-amber-700' => $status === 'dry docking',
                            'bg-rose-50 text-rose-700' => in_array($status, ['non-operational', 'decommissioned'], true),
                            'bg-slate-100 text-slate-600' => !in_array($status, ['operational', 'dry docking', 'non-operational', 'decommissioned'], true),
                        ])>{{ $vessel->vessel_status ?: 'Not set' }}</dd></div>
                    </dl>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                <span class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-villa-50 text-2xl text-villa-700"><i class="bi bi-ship"></i></span>
                <h2 class="text-lg font-extrabold text-slate-800">No vessels found</h2>
                <p class="mt-1 text-sm text-slate-500">Vessels added to the system will appear here.</p>
            </div>
        @endforelse
    </div>

    @if($vessels->hasPages())
        <div class="mx-auto mt-7 max-w-7xl">{{ $vessels->links() }}</div>
    @endif
</section>

@if($canManageVessels)
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" id="editForm" class="modal-content">
            @csrf
            @method('PUT')

            <div class="modal-header">
                <div><p class="mb-1 text-xs font-extrabold uppercase tracking-wider text-villa-600">Vessel Management</p><h2 class="modal-title">Edit Vessel</h2></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="vessel_name">Vessel Name</label><input type="text" name="vessel_name" id="vessel_name" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label" for="captain_id">Captain</label><select name="captain_id" id="captain_id" class="form-select"><option value="">Select Captain</option>@foreach($captains as $captain)<option value="{{ $captain->id }}">{{ $captain->name }} {{ $captain->lastname }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="imo_number">IMO</label><input type="text" name="imo_number" id="imo_number" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label" for="call_sign">Call Sign</label><input type="text" name="call_sign" id="call_sign" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label" for="vessel_type">Type</label><select name="vessel_type" id="vessel_type" class="form-select"><option value="">Select</option><option value="Dry Cargo">Dry Cargo</option><option value="Tanker">Tanker</option><option value="RO-RO">RO-RO</option></select></div>
                    <div class="col-md-4"><label class="form-label" for="dwt">DWT</label><input type="number" name="dwt" id="dwt" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label" for="fuel_type">Fuel</label><select name="fuel_type" id="fuel_type" class="form-select"><option value="Heavy Fuel Oil">Heavy Fuel Oil</option><option value="Marine Diesel Oil">Marine Diesel Oil</option></select></div>
                    <div class="col-md-4"><label class="form-label" for="service_speed">Speed</label><input type="text" name="service_speed" id="service_speed" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label" for="charter_type">Charter</label><select name="charter_type" id="charter_type" class="form-select"><option value="Voyage Charter">Voyage Charter</option><option value="Time Charter">Time Charter</option></select></div>
                    <div class="col-md-4"><label class="form-label" for="vessel_status">Status</label><select name="vessel_status" id="vessel_status" class="form-select"><option value="Operational">Operational</option><option value="Non-Operational">Non-Operational</option><option value="Sold">Sold</option><option value="Decommissioned">Decommissioned</option><option value="Dry Docking">Dry Docking</option></select></div>
                </div>
            </div>

            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Vessel</button></div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($canManageVessels)
<script>
document.querySelectorAll('.editBtn').forEach((button) => {
    button.addEventListener('click', () => {
        const fields = {
            vessel_name: 'name', captain_id: 'captain', imo_number: 'imo', call_sign: 'call', vessel_type: 'type',
            dwt: 'dwt', fuel_type: 'fuel', service_speed: 'speed', charter_type: 'charter', vessel_status: 'status'
        };
        document.getElementById('editForm').action = @js(url('/shipping/vessels')).replace(/\/$/, '') + '/' + button.dataset.id;
        Object.entries(fields).forEach(([field, dataKey]) => {
            document.getElementById(field).value = button.dataset[dataKey] ?? '';
        });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).show();
    });
});
</script>
@endif
@endpush

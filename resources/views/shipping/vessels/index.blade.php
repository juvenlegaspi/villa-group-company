@extends('layouts.app')

@section('content')

<div class="container">

    <h3 class="mb-4">Vessel List</h3>

    @if(auth()->user()->is_admin)
        <a href="/shipping/vessels/create" class="btn btn-primary mb-3">
            + Add Vessel
        </a>
    @endif
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Vessel Name</th>
                        <th>Captain</th>
                        <th>IMO</th>
                        <th>Type</th>
                        <th>DWT</th>
                        <th>Status</th>
                        <th width="120">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vessels as $vessel)
                    <tr>
                        <td>
                            @php
                                $code = 'VN-' . str_pad($vessel->id, 3, '0', STR_PAD_LEFT);
                            @endphp
                            <a href="/shipping/vessels/{{ $vessel->id }}" class="fw-bold text-primary text-decoration-none">
                                {{ $code }}
                            </a>
                        </td>
                        <td>{{ $vessel->vessel_name }}</td>
                        <td>{{ $vessel->captain->name ?? '-' }} {{ $vessel->captain->lastname ?? '' }}</td>
                        <td>{{ $vessel->imo_number }}</td>
                        <td>{{ $vessel->vessel_type }}</td>
                        <td>{{ $vessel->dwt }}</td>
                        <td>{{ $vessel->vessel_status }}</td>
                        <td>
                            <button 
                                class="btn btn-sm btn-warning editBtn"
                                data-id="{{ $vessel->id }}"
                                data-name="{{ $vessel->vessel_name }}"
                                data-captain="{{ $vessel->captain_id }}"
                                data-imo="{{ $vessel->imo_number }}"
                                data-call="{{ $vessel->call_sign }}"
                                data-type="{{ $vessel->vessel_type }}"
                                data-dwt="{{ $vessel->dwt }}"
                                data-fuel="{{ $vessel->fuel_type }}"
                                data-speed="{{ $vessel->service_speed }}"
                                data-charter="{{ $vessel->charter_type }}"
                                data-status="{{ $vessel->vessel_status }}"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">No data</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-3">
                {{ $vessels->links() }}
            </div>
        </div>
    </div>

</div>

{{-- ================= MODAL ================= --}}
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="editForm">
            @csrf
            @method('PUT')

            <div class="modal-content">

                <div class="modal-header">
                    <h5>Edit Vessel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-3">

                    <div class="col-md-4">
                        <label>Vessel Name</label>
                        <input type="text" name="vessel_name" id="vessel_name" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>Captain</label>
                        <select name="captain_id" id="captain_id" class="form-control">
                            <option value="">Select Captain</option>
                            @foreach($captains as $captain)
                                <option value="{{ $captain->id }}">
                                    {{ $captain->name }} {{ $captain->lastname }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>IMO</label>
                        <input type="text" name="imo_number" id="imo_number" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>Call Sign</label>
                        <input type="text" name="call_sign" id="call_sign" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>Type</label>
                        <select name="vessel_type" id="vessel_type" class="form-control">
                            <option value="">Select</option>
                            <option value="Dry Cargo">Dry Cargo</option>
                            <option value="Tanker">Tanker</option>
                            <option value="RO-RO">RO-RO</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>DWT</label>
                        <input type="number" name="dwt" id="dwt" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>Fuel</label>
                        <select name="fuel_type" id="fuel_type" class="form-control">
                            <option value="Heavy Fuel Oil">Heavy Fuel Oil</option>
                            <option value="Marine Diesel Oil">Marine Diesel Oil</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>Speed</label>
                        <input type="text" name="service_speed" id="service_speed" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>Charter</label>
                        <select name="charter_type" id="charter_type" class="form-control">
                            <option value="Voyage Charter">Voyage Charter</option>
                            <option value="Time Charter">Time Charter</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>Status</label>
                        <select name="vessel_status" id="vessel_status" class="form-control">
                            <option value="Operational">Operational</option>
                            <option value="Non-Operational">Non-Operational</option>
                            <option value="Sold">Sold</option>
                            <option value="Decommissioned">Decommissioned</option>
                            <option value="Dry Docking">Dry Docking</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-success">Update</button>
                </div>

            </div>
        </form>
    </div>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
    <div id="successToast" class="toast align-items-center text-bg-success border-0">
        <div class="d-flex">
            <div class="toast-body">
                ✅ Vessel updated successfully!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
</div>

{{-- ================= SCRIPT ================= --}}
<script>
document.querySelectorAll('.editBtn').forEach(button => {
    button.addEventListener('click', function () {

        let id = this.dataset.id;

        // set form action
        document.getElementById('editForm').action = '/shipping/vessels/' + id;

        // populate fields
        document.getElementById('vessel_name').value = this.dataset.name;
        document.getElementById('captain_id').value = this.dataset.captain;
        document.getElementById('imo_number').value = this.dataset.imo;
        document.getElementById('call_sign').value = this.dataset.call;
        document.getElementById('vessel_type').value = this.dataset.type;
        document.getElementById('dwt').value = this.dataset.dwt;
        document.getElementById('fuel_type').value = this.dataset.fuel;
        document.getElementById('service_speed').value = this.dataset.speed;
        document.getElementById('charter_type').value = this.dataset.charter;
        document.getElementById('vessel_status').value = this.dataset.status;

        // show modal
        let modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    });
});
</script>

@endsection
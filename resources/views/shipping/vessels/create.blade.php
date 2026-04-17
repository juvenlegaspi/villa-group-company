@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">Add New Vessel</h3>
        <a href="{{ route('vessels.index') }}" class="btn btn-outline-secondary">
            Back to Vessel List
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ url('/shipping/vessels') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="fw-bold">Vessel Name</label>
                        <input type="text" name="vessel_name" class="form-control text-uppercase" required>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">IMO Number</label>
                        <input type="text" name="imo_number" class="form-control text-uppercase">
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Official No.</label>
                        <input type="text" name="call_sign" class="form-control text-uppercase">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="fw-bold">Vessel Type</label>
                        <select name="vessel_type" class="form-control">
                            <option value="">Select Type</option>
                            <option value="Dry Cargo">Dry Cargo</option>
                            <option value="Tanker">Tanker</option>
                            <option value="RO-RO">RO-RO</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">DWT</label>
                        <div class="input-group">
                            <input type="number" name="dwt" class="form-control" placeholder="Enter DWT">
                            <span class="input-group-text">MT</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Fuel Type</label>
                        <select name="fuel_type" class="form-control">
                            <option value="">Select Type</option>
                            <option value="Heavy Fuel Oil">Heavy Fuel Oil</option>
                            <option value="Marine Diesel Oil">Marine Diesel Oil</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="fw-bold">Service Speed</label>
                        <input type="text" name="service_speed" class="form-control text-uppercase">
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Charter Type</label>
                        <select name="charter_type" class="form-control">
                            <option value="">Select Type</option>
                            <option value="Voyage Charter">Voyage Charter</option>
                            <option value="Time Charter">Time Charter</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Captain In Charge</label>
                        <select name="captain_id" class="form-control" required>
                            <option value="">Select Captain</option>
                            @foreach($captains as $captain)
                                <option value="{{ $captain->id }}">
                                    {{ $captain->name }} {{ $captain->lastname }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="fw-bold">Vessel Status</label>
                        <select name="vessel_status" class="form-control">
                            <option value="">Select Status</option>
                            <option value="Operational">Operational</option>
                            <option value="Non-Operational">Non-Operational</option>
                            <option value="Sold">Sold</option>
                            <option value="Decommissioned">Decommissioned</option>
                            <option value="Dry Docking">Dry Docking</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button class="btn btn-primary px-4">Save Vessel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

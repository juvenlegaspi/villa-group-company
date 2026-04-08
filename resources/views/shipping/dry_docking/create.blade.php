@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card shadow-sm">
        
        <!-- HEADER -->
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">🚢 Add Dry Docking</h5>
        </div>

        <div class="card-body">

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ url('/shipping/dry-docking/store') }}">
                @csrf

                <div class="row">

                    <!-- LEFT SIDE -->
                    <div class="col-md-6">

                        <div class="mb-3">
                            <label class="form-label">Vessel</label>
                            <select name="vessel_id" class="form-control" required>
                                <option value="">Select Vessel</option>
                                @foreach($vessels as $v)
                                    <option value="{{ $v->id }}">{{ $v->vessel_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Arrival Date</label>
                            <input type="date" name="arrival_date" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label>Docking Date</label>
                            <input type="date" name="docking_date" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label>Laydays</label>
                            <input type="number" name="laydays" class="form-control" placeholder="Enter number of days">
                        </div>

                    </div>

                    <!-- RIGHT SIDE -->
                    <div class="col-md-6">

                        <div class="mb-3">
                            <label>Undocking Date</label>
                            <input type="date" name="undocking_date" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label>Vessel Manager</label>
                            <input type="text" name="vessel_manager" class="form-control" placeholder="Enter manager name">
                        </div>

                        <!-- CHECKBOX DESIGN -->
                        <div class="mb-3">
                            <label class="form-label d-block">Docking Type</label>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="is_shipyard" id="shipyard">
                                <label class="form-check-label" for="shipyard">Shipyard</label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="is_inhouse" id="inhouse">
                                <label class="form-check-label" for="inhouse">In-house</label>
                            </div>
                        </div>

                        
                        <div class="mb-3">
                            <div class="mb-3">
                                <label>Status</label>
                                    <select name="status" class="form-control" required>
                                        <option value="">Select Status</option>
                                        <option value="operational">Operational</option>
                                        <option value="non-operational">Non-Operational</option>
                                    </select>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- BUTTONS -->
                <div class="d-flex justify-content-between mt-4">
                    <a href="{{ url('/shipping/dry-docking') }}" class="btn btn-secondary">
                        ← Back
                    </a>

                    <button type="submit" class="btn btn-success">
                        💾 Save Dry Docking
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection
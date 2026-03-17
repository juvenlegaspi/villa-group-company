@extends('layouts.app')

@section('content')

<a href="{{ url()->previous() }}" class="btn btn-outline-secondary mb-3">
    ← Back
</a>
<form method="POST" action="{{ url('/shipping/voyage-logs/store') }}">
    @csrf
    <div class="row">
        <div class="col-md-6">
            <input type="hidden" name="vessel_id" value="{{ $vessel->id }}">
            <label>Voyage ID</label>
            <input type="text" class="form-control" value="VL-NEW" readonly>
        </div>
        <div class="col-md-6">
            <label>Cargo Type</label>
            <input type="text" name="cargo_type" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-6">
            <label>Cargo Volume</label>
            <input type="text" name="cargo_volume" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-6">
            <label>Crew on Board</label>
            <input type="number" name="crew_on_board" class="form-control">
        </div>
        <div class="col-md-6">
            <label>Port Location</label>
            <input type="text" name="port_location" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-6">
            <label>Voyage Number</label>
            <input type="text" name="voyage_no" class="form-control">
        </div>
        <div class="col-md-2">
            <label>Fuel ROB</label>
            <div class="input-group">
                <input type="number" name="fuel_rob" class="form-control">
                <span class="input-group-text">Liters</span>
            </div>
        </div>
    </div>
    <button class="btn btn-primary mt-3">
        SAVE
    </button>
</form>
@endsection
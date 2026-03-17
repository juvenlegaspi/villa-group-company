@extends('layouts.app')

@section('content')

<h3 class="mb-4">Add New Vessel</h3>

<a href="/shipping/vessels" class="btn btn-outline-secondary mb-3">
    ← Back to Vessel List
</a>

<form method="POST" action="/shipping/vessels">
    @csrf
    <div class="row">
        <div class="col-md-4">
            <label>Vessel Name</label>
            <input type="text" name="vessel_name" style="text-transform: uppercase;" class="form-control" required >
        </div>
        <div class="col-md-4">
            <label>IMO Number</label>
            <input type="text" name="imo_number" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-4">
            <label>Call Sign</label>
            <input type="text" name="call_sign" class="form-control" style="text-transform: uppercase;">
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <label>Vessel Type</label>
            <input type="text" name="vessel_type" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-4">
            <label>DWT</label>
            <input type="text" name="dwt" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-4">
            <label>Fuel Type</label>
            <input type="text" name="fuel_type" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-4 mt-2">
            <label>Service Speed</label>
            <input type="text" name="service_speed" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-4 mt-2">
            <label>Charter Type</label>
            <input type="text" name="charter_type" class="form-control" style="text-transform: uppercase;">
        </div>
        <div class="col-md-4 mt-2">
            <label>Vessel Status</label>
            <select name="vessel_status" class="form-control">
                <option value="">Select Status</option>
                <option value="Active">Operational</option>
                <option value="Inactive">Non operational</option>
            </select>
        </div>
    </div>

    <button class="btn btn-primary">Save Vessel</button>
</form>

@endsection
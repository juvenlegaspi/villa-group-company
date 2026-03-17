@extends('layouts.app')
@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Add Vessel Certificate</h4>
        <a href="{{ route('vessel-certificates.show',$vessel->id) }}" class="btn btn-secondary btn-sm">← Back</a>
    </div>
    <form action="{{ route('vessel-certificates.store') }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-md-4">
                <label>Vessel</label>
                <input type="text" class="form-control" value="{{ $vessel->vessel_name }}" readonly>
                <input type="hidden" name="vessel_id" value="{{ $vessel->id }}">
            </div>
            <div class="col-md-4">
                <label>Certificate Name</label>
                <input type="text" name="certificate_name" class="form-control">
            </div>
            <div class="col-md-4">
                <label>Issue Date</label>
                <input type="date" name="issue_date" class="form-control">
            </div>
            <div class="col-md-4 mt-2">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control">
            </div>
            <div class="col-md-8 mt-2">
                <label>Remarks</label>
                <input type="text" name="remarks" class="form-control">
            </div>
        </div>
        <br>
        <a href="{{ route('vessel-certificates.show', $vessel->id) }}" class="btn btn-secondary"> Back </a>
        <button class="btn btn-success">Save Certificate</button>
    </form>
</div>
@endsection
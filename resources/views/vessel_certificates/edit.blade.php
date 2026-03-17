@extends('layouts.app')
@section('content')
<div class="container">
    <div class="d-flex justify-content-between mb-3">
        <h4>Edit Certificate</h4>
        <a href="{{ route('vessel-certificates.show',$certificate->vessel_id) }}" class="btn btn-secondary btn-sm"> ← Back </a>
    </div>
    <form action="{{ route('vessel-certificates.update',$certificate->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-md-4">
                <label>Vessel</label>
                <input type="text" class="form-control" value="{{ $certificate->vessel->vessel_name }}" readonly>
                <input type="hidden" name="vessel_id" value="{{ $certificate->vessel_id }}">
            </div>
            <div class="col-md-4">
                <label>Certificate Name</label>
                <input type="text" name="certificate_name" class="form-control" value="{{ $certificate->certificate_name }}">
            </div>
            <div class="col-md-4">
                <label>Issue Date</label>
                <input type="date" name="issue_date" class="form-control" value="{{ $certificate->issue_date }}">
            </div>
            <div class="col-md-4 mt-3">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control" value="{{ $certificate->expiry_date }}">
            </div>
            <div class="col-md-8 mt-3">
                <label>Remarks</label>
                <input type="text" name="remarks" class="form-control" value="{{ $certificate->remarks }}">
            </div>
        </div>
        <button class="btn btn-success mt-3"> Update Certificate </button>
    </form>
</div
@endsection
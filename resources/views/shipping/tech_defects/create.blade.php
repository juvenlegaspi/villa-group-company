@extends('layouts.app')
@section('content')
<div class="container">
    <h4 class="mb-3">Add Tech & Defect Report</h4>
    <form method="POST" action="{{route('tech-defects.store')}}">
        @csrf
        <div class="mb-1 row">
            <div class="col-md-4 mb-3">
                <div class="col-md-3 mb-1">
                    <label class="form-label">Report ID</label>
                    <input type="text" class="form-control" value="NEW" readonly>
                </div>
            </div>
            <div class="col-md-3 mb-1">
                <label class="form-label">Date Issue Identified</label>
                <input type="date" name="date_identified"  class="form-control" required>
            </div>
            <div class="col-md-3 mb-1">
                <label class="form-label">Vessel</label>
                <select name="vessel_id" class="form-control">
                    @foreach($vessels as $v)
                        <option value="{{$v->id}}">{{$v->vessel_name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mb-1 row">
            <div class="col-md-3 mb-1">
                <label class="form-label">Port / Location</label>
                <input type="text" name="port_location" value="" class="form-control" style="text-transform: uppercase;">
            </div>
            <div class="col-md-3 mb-1">
                <label class="form-label">Reported By</label>
                <input type="text" name="reported_by" value="" class="form-control" style="text-transform: uppercase;">
            </div>
            <div class="col-md-3 mb-1">
                <label class="form-label">System Affected</label>
                <input type="text" name="system_affected" class="form-control" style="text-transform: uppercase;">
            </div>
        </div>
        <div class="mb-1 row">
            <div class="col-md-3 mb-1">
                <label class="form-label">Severity Level</label>
                    <select name="severity_level" class="form-control">
                        <option>Minor</option>
                        <option>Major</option>
                        <option>Critical</option>
                    </select>
            </div>
            <div class="col-md-3 mb-1">
                <label class="form-label">Operational Impact</label>
                <select name="operational_impact" class="form-control">
                    <option>None</option>
                    <option>Limited</option>
                    <option>Stopped</option>
                </select>
            </div>
            <div class="col-md-3 mb-1">
                <label class="form-label">Defect Description</label>
                <textarea name="defect_description" class="form-control" style="text-transform: uppercase;"></textarea>
            </div>
        </div>
        <div class="mb-1 row">
            <div class="col-md-3 mb-1">
                <label>Initial Cause</label>
                <textarea name="initial_cause" class="form-control"></textarea>
            </div>
            <div class="col-md-3 mb-1">
                <label class="form-label">Temporary Repair Done?</label>
                <select name="temporary_repair" class="form-control">
                    <option></option>
                    <option>Yes</option>
                    <option>No</option>
                </select>
            </div>
            <div class="col-md-3 mb-1">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" style="text-transform: uppercase;"></textarea>
            </div>
        </div>
        <div class="mb-1 row">
            
        </div>
        <button class="btn btn-success">Save Report</button>
            <a href="{{route('tech-defects.index')}}" class="btn btn-secondary"> Back </a>
    </form>
</div>
@endsection
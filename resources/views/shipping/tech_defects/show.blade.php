@extends('layouts.app')

@section('content')

<div class="container">
    <h3 class="mb-1">Tech & Defect Report Details</h3>
        <form method="POST" action="{{ route('tech-defects.update',$report->id) }}">
            @csrf
            @method('PUT')
            <div class="mb-3 row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Report ID</label>
                    <input type="text" class="form-control fw-bold text-primary" value="TD-{{ $report->id }}" readonly>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Date Issue Identified</label>
                    <input type="date" name="date_identified" value="{{ $report->date_identified }}" class="form-control" readonly>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Vessel</label>
                    <input type="text" value="{{ $report->vessel->vessel_name }}" class="form-control" readonly>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Port / Location</label>
                    <input type="text" class="form-control" value="{{ $report->port_location }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reported By</label>
                    <input type="text" class="form-control" value="{{ $report->reported_by }}" readonly> </div>
                <div class="col-md-4">
                    <label class="form-label">System Affected</label>
                    <input type="text" class="form-control" value="{{ $report->system_affected }}" readonly> 
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-1">
                    <label class="form-label">Severity Level</label>
                    <select name="severity_level" class="form-control">
                        <option value="Minor" {{$report->severity_level=='Minor'?'selected':''}}>Minor</option>
                        <option value="Major" {{$report->severity_level=='Major'?'selected':''}}>Major</option>
                        <option value="Critical" {{$report->severity_level=='Critical'?'selected':''}}>Critical</option>
                    </select>
                </div>
                <div class="col-md-4 mb-1">
                    <label class="form-label">Operational Impact</label>
                    <input type="text" name="operational_impact" value="{{$report->operational_impact}}" class="form-control">
                </div>
                <div class="col-md-4 mb-1">
                    <label class="form-label">Defect Description</label>
                    <textarea name="defect_description" class="form-control">{{$report->defect_description}}</textarea>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-1">
                    <label class="form-label">Initial Cause</label>
                    <textarea name="initial_cause" class="form-control">{{$report->initial_cause}}</textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Temporary Repair Done?</label>
                    <select name="temporary_repair" class="form-control">
                        <option value="Yes" {{$report->temporary_repair=='Yes'?'selected':''}}>Yes</option>
                        <option value="No" {{$report->temporary_repair=='No'?'selected':''}}>No</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control">{{$report->remarks}}</textarea>
                </div>
            </div>
            <div class="mb-1 row">
                
               <!-- <div class="col-md-3 form-check mb-1">
                    @if($report->status =='Ongoing')
                        <label class="form-label">Click the button if</label>
                        <button type="button" class="btn btn-warning form-control" data-bs-toggle="modal" data-bs-target="#thirdPartyModal">
                            Need 3rd Party Support
                        </button>
                    @endif -->
                </div>
            </div>
            @if($report->supports->count() > 0)
            <hr>
            <h5>3rd Party Support History</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Reason</th>
                        <th>Spares</th>
                        <th>Tools</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
            <tbody>
                @foreach($report->supports as $s)
                    <tr>
                        <td>{{$s->reason_for_support}}</td>
                        <td>{{$s->spares_required}}</td>
                        <td>{{$s->tools_required}}</td>
                        <td>{{$s->status}}</td>
                        <td>
                            @if($s->status != 'Done')
                                <button type="submit" name="action" value="done_{{ $s->id }}" class="btn btn-success btn-sm"> Done </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            </table>
        @endif
            <a href="{{route('tech-defects.index')}}" class="btn btn-secondary">Back</a>
            @if($report->status == 'Open')
                <button type="submit" name="action" value="start" class="btn btn-primary"> Start Repair </button>
            @endif
            @if($report->status == 'Ongoing')
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#thirdPartyModal"> Need 3rd Party Support </button>
                <button type="submit" name="action" value="complete" class="btn btn-success"> Complete </button>
            @endif
            @if($report->status == 'Waiting 3rd Party' && $allSupportDone)
                <button type="submit" name="action" value="complete" class="btn btn-success">Complete</button>
            @endif
        </form>

        
</div>
<!-- 3RD PARTY MODAL START -->
    <div class="modal fade" id="thirdPartyModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">3rd Party Support</h5>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('tech-defects.update',$report->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label>Reason for Support</label>
                            <textarea name="reason_for_support" class="form-control"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Spares Required</label>
                            <select name="spares_required" class="form-control">
                                <option></option>
                                <option>Yes</option>
                                <option>No</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Tools Required</label>
                            <input type="text" name="tools_required" class="form-control">
                        </div>
                        <button class="btn btn-success" name="action" value="add_support"> Add Support </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<!-- 3RD PARTY MODAL END -->
@endsection
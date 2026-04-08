@extends('layouts.app')

@section('content')
<div class="container">

    <!-- HEADER -->
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Tech & Defect Report Details</h4>

            <div class="d-flex gap-2">
                <a href="{{ route('tech-defects.index') }}" class="btn btn-secondary">Back</a>

                @if($report->status == 'Open')
                    <button type="submit" form="actionForm" name="action" value="start" class="btn btn-primary">
                        Start Repair
                    </button>
                @endif

                @if($report->status == 'Ongoing')
                    <button type="button" class="btn btn-warning"
                        data-bs-toggle="modal" data-bs-target="#thirdPartyModal">
                        Need 3rd Party
                    </button>

                    <button type="submit" form="actionForm" name="action" value="complete" class="btn btn-success">
                        Complete
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- MAIN DETAILS -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Report ID</label>
                    <input type="text" class="form-control fw-bold text-primary"
                        value="TD-{{ str_pad($report->id, 2, '0', STR_PAD_LEFT) }}" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Date Issue Identified</label>
                    <input type="text" class="form-control"
                        value="{{ \Carbon\Carbon::parse($report->date_identified)->format('m/d/Y') }}" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Vessel</label>
                    <input type="text" class="form-control"
                        value="{{ $report->vessel->vessel_name }}" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Port / Location</label>
                    <input type="text" class="form-control" value="{{ $report->port_location }}" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Reported By</label>
                    <input type="text" class="form-control" value="{{ $report->reported_by }}" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">System Affected</label>
                    <input type="text" class="form-control" value="{{ $report->system_affected }}" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Severity</label>
                    <input type="text" class="form-control" value="{{ $report->severity_level }}" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Operational Impact</label>
                    <input type="text" class="form-control" value="{{ $report->operational_impact }}" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Temporary Repair</label>
                    <input type="text" class="form-control" value="{{ $report->temporary_repair }}" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Defect Description</label>
                    <textarea class="form-control" rows="3" readonly>{{ $report->defect_description }}</textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Initial Cause</label>
                    <textarea class="form-control" rows="3" readonly>{{ $report->initial_cause }}</textarea>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" rows="2" readonly>{{ $report->remarks }}</textarea>
                </div>

            </div>

        </div>
    </div>

    <!-- 3RD PARTY SUPPORT -->
    @if($report->supports->count() > 0)
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <strong>3rd Party Support History</strong>
        </div>

        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reason</th>
                        <th>Spares</th>
                        <th>Tools</th>
                        <th>Status</th>
                        <th width="120">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report->supports as $s)
                    <tr>
                        <td>{{ $s->reason_for_support }}</td>
                        <td>{{ $s->spares_required }}</td>
                        <td>{{ $s->tools_required }}</td>
                        <td>
                            <span class="badge bg-{{ $s->status == 'Done' ? 'success' : 'warning' }}">
                                {{ $s->status }}
                            </span>
                        </td>
                        <td>
                            @if($s->status != 'Done')
                                <form method="POST" action="{{ route('tech-defects.update',$report->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <button class="btn btn-success btn-sm" name="action" value="done_{{ $s->id }}">
                                        Done
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

<!-- ACTION FORM -->
<form id="actionForm" method="POST" action="{{ route('tech-defects.update',$report->id) }}">
    @csrf
    @method('PUT')
</form>

<!-- MODAL -->
<div class="modal fade" id="thirdPartyModal">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">3rd Party Support</h5>
            </div>

            <form method="POST" action="{{ route('tech-defects.update',$report->id) }}">
                @csrf
                @method('PUT')

                <div class="modal-body">

                    <div class="mb-3">
                        <label>Reason</label>
                        <textarea name="reason_for_support" class="form-control" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label>Spares Required</label>
                        <select name="spares_required" class="form-control" required>
                            <option>--SELECT--</option>
                            <option>Yes</option>
                            <option>No</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Tools Required</label>
                        <input type="text" name="tools_required" class="form-control" required>
                    </div>

                </div>

                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button class="btn btn-success" name="action" value="add_support">
                        Add Support
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

@endsection
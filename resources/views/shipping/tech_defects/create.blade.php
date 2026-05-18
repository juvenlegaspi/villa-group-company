@extends('layouts.app')

@section('content')
<div class="container">

    <!-- HEADER -->
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Add Tech & Defect Report</h4>
            <a href="{{ route('tech-defects.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>

    <!-- FORM CARD -->
    <div class="card shadow-sm">
        <div class="card-body">

            <form method="POST" action="{{ route('tech-defects.store') }}">
                @csrf

                <div class="row g-3">

                    <!-- Report ID -->
                    <div class="col-md-4">
                        <label class="form-label">Report ID</label>
                        <input type="text" class="form-control" value="AUTO GENERATED" readonly>
                    </div>

                    <!-- Date -->
                    <div class="col-md-4">
                        <label class="form-label">Date Issue Identified</label>
                        <input type="date" name="date_identified" class="form-control" required>
                    </div>

                    <!-- Vessel -->
                    <div class="col-md-4">
                        <label class="form-label">Vessel</label>
                        <select name="vessel_id" class="form-select" required>
                            @foreach($vessels as $v)
                                <option value="{{ $v->id }}">{{ $v->vessel_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Port -->
                    <div class="col-md-4">
                        <label class="form-label">Port / Location</label>
                        <input type="text" name="port_location" class="form-control text-uppercase">
                    </div>

                    <!-- Reported -->
                    <div class="col-md-4">
                        <label class="form-label">Reported By</label>
                        <select name="reported_by" class="form-control">
                            <option value="">-- Select Personnel --</option>

                            @foreach($shippingUsers as $user)
                                <option value="{{ strtoupper($user->name) }}">
                                    {{ strtoupper($user->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- System -->
                    <div class="col-md-4">
                        <label class="form-label">System Affected</label>
                        <select name="system_affected" class="form-select">
                            <option value="">-- Select System --</option>
                            <option value="Deck">Deck</option>
                            <option value="Main Engine">Main Engine</option>
                            <option value="Auxiliary Engine">Auxiliary Engine</option>
                            <option value="Crane">Crane</option>
                            <option value="Safety Equipment">Safety Equipment</option>
                            <option value="Cargo Handling">Cargo Handling</option>
                            <option value="Accommodation">Accommodation</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Severity -->
                    <div class="col-md-4">
                        <label class="form-label">Severity Level</label>
                        <select name="severity_level" class="form-select">
                            <option value="">-- Select Severity --</option>
                            <option>Minor</option>
                            <option>Major</option>
                            <option>Critical</option>
                        </select>
                    </div>

                    <!-- Impact -->
                    <div class="col-md-4">
                        <label class="form-label">Operational Impact</label>
                        <select name="operational_impact" class="form-select">
                            <option value="">-- Select Impact --</option>
                            <option>None</option>
                            <option>Limited</option>
                            <option>Stopped</option>
                        </select>
                    </div>

                    <!-- Temporary -->
                    <div class="col-md-4">
                        <label class="form-label">Temporary Repair Done?</label>
                        <select name="temporary_repair" class="form-select">
                            <option>Yes</option>
                            <option>No</option>
                        </select>
                    </div>

                    <!-- Description -->
                    <div class="col-md-6">
                        <label class="form-label">Defect Description</label>
                        <textarea name="defect_description" class="form-control text-uppercase" rows="3" required></textarea>
                    </div>

                    <!-- Initial Cause -->
                    <div class="col-md-6">
                        <label class="form-label">Initial Cause</label>
                        <textarea name="initial_cause" class="form-control" rows="3"></textarea>
                    </div>

                    <!-- Remarks -->
                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control text-uppercase" rows="2"></textarea>
                    </div>

                </div>

                <!-- BUTTONS -->
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-success px-4">
                        Save Report
                    </button>

                    <a href="{{ route('tech-defects.index') }}" class="btn btn-secondary px-4">
                        Cancel
                    </a>
                </div>

            </form>

        </div>
    </div>

</div>
<script>
document.querySelectorAll('.text-uppercase').forEach(el => {
    el.addEventListener('input', () => {
        el.value = el.value.toUpperCase();
    });
});
</script>
@endsection
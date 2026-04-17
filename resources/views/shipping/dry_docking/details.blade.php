@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Dry Docking Overview</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="fw-bold text-muted">Vessel Name</label>
                    <div class="fs-6 fw-semibold">{{ $header->vessel->vessel_name ?? 'N/A' }}</div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold text-muted">Dry Dock Code</label>
                    <div class="fs-6 fw-semibold text-primary">
                        DD-{{ str_pad($header->id, 4, '0', STR_PAD_LEFT) }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold text-muted">Arrival Date</label>
                    <div class="fs-6">{{ optional($header->arrival_date)->format('M d, Y') ?? 'N/A' }}</div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold text-muted">Status</label>
                    <div>
                        <span class="badge {{ $header->status === 'operational' ? 'bg-success' : 'bg-danger' }}">
                            {{ ucfirst($header->status ?? 'N/A') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ url('/shipping/dry-docking/' . $header->id . '/details/store') }}">
        @csrf
        <input type="hidden" name="vessel_id" value="{{ $header->vessel_id }}">

        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Scope of Work Details</h6>
                <button type="button" class="btn btn-sm btn-light addRow">Add Row</button>
            </div>

            <div class="card-body p-0">
                <table class="table table-bordered mb-0" id="itemsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Scope of Work</th>
                            <th>Plan</th>
                            <th>Actual</th>
                            <th>Status</th>
                            <th>Daily Status</th>
                            <th>Weight %</th>
                            <th>Progress %</th>
                            <th>Activity</th>
                            <th>Remarks</th>
                            <th width="90">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input name="items[0][scope_of_work]" class="form-control"></td>
                            <td><input type="number" name="items[0][plan_duration]" class="form-control"></td>
                            <td><input type="number" name="items[0][actual_duration]" class="form-control"></td>
                            <td>
                                <select name="items[0][status]" class="form-control">
                                    <option value="not started">Not Started</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="near completion">Near Completion</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </td>
                            <td><input name="items[0][daily_status]" class="form-control"></td>
                            <td><input type="number" name="items[0][weight]" class="form-control"></td>
                            <td><input type="number" name="items[0][actual_progress]" class="form-control"></td>
                            <td><input name="items[0][activity]" class="form-control"></td>
                            <td><input name="items[0][remarks]" class="form-control"></td>
                            <td>
                                <button type="button" class="btn btn-danger removeRow">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer text-end">
                <button class="btn btn-success">Save Details</button>
            </div>
        </div>
    </form>
</div>

<script>
let index = 1;

document.addEventListener('click', function (event) {
    if (event.target.classList.contains('addRow')) {
        const table = document.querySelector('#itemsTable tbody');
        const row = `
            <tr>
                <td><input name="items[${index}][scope_of_work]" class="form-control"></td>
                <td><input type="number" name="items[${index}][plan_duration]" class="form-control"></td>
                <td><input type="number" name="items[${index}][actual_duration]" class="form-control"></td>
                <td>
                    <select name="items[${index}][status]" class="form-control">
                        <option value="not started">Not Started</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="near completion">Near Completion</option>
                        <option value="completed">Completed</option>
                    </select>
                </td>
                <td><input name="items[${index}][daily_status]" class="form-control"></td>
                <td><input name="items[${index}][weight]" class="form-control"></td>
                <td><input name="items[${index}][actual_progress]" class="form-control"></td>
                <td><input name="items[${index}][activity]" class="form-control"></td>
                <td><input name="items[${index}][remarks]" class="form-control"></td>
                <td><button type="button" class="btn btn-danger removeRow">Remove</button></td>
            </tr>
        `;

        table.insertAdjacentHTML('beforeend', row);
        index++;
    }

    if (event.target.classList.contains('removeRow')) {
        event.target.closest('tr').remove();
    }
});
</script>
@endsection

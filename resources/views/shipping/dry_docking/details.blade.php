@extends('layouts.app')
@section('content')
<div class="container">
    <!--  HEADER INFO -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">🚢 Dry Docking Overview</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="fw-bold text-muted">Vessel Name</label>
                    <div class="fs-6 fw-semibold">
                        {{ $header->vessel->vessel_name ?? 'N/A' }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold text-muted">Dry Dock Code</label>
                    <div class="fs-6 fw-semibold text-primary">
                        DD-{{ str_pad($header->id, 4, '0', STR_PAD_LEFT) }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold text-muted">Start Date</label>
                    <div class="fs-6">
                        {{ $header->start_date ?? 'N/A' }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold text-muted">Status</label>
                    <div>
                        <span class="badge 
                            {{ $header->status == 'completed' ? 'bg-success' : 
                               ($header->status == 'ongoing' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                            {{ $header->status ?? 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- 🟢 ADD DETAILS TABLE -->
    <form method="POST" action="{{ url('/shipping/dry-docking/'.$header->id.'/details/store') }}">
        @csrf
        <input type="hidden" name="vessel_id" value="{{ $header->vessel_id }}">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <h6 class="mb-0">⚙️ Scope of Work Details</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
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
                            <td><input type="number" name="items[0][progress]" class="form-control"></td>
                            <td><input name="items[0][activity]" class="form-control"></td>
                            <td><input name="items[0][remarks]" class="form-control"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-success">
                    💾 Save Details
                </button>
            </div>
        </div>
    </form>
</div>
<script>
let index = 1;
document.addEventListener('click', function(e){
    if(e.target.classList.contains('addRow')){
        let table = document.querySelector('#itemsTable tbody');
        let row = `
        <tr>
            <td><input name="items[${index}][scope_of_work]" class="form-control"></td>
            <td><input type="number" name="items[${index}][plan_duration]" class="form-control"></td>
            <td><input type="number" name="items[${index}][actual_duration]" class="form-control"></td>
            <td>
                <select name="items[${index}][status]" class="form-control">
                    <option>not started</option>
                    <option>ongoing</option>
                    <option>near completion</option>
                    <option>completed</option>
                </select>
            </td>
            <td>
                <select name="items[${index}][daily_status]" class="form-control">
                    <option>not started</option>
                    <option>ahead of schedule</option>
                    <option>delayed</option>
                </select>
            </td>
            <td><input name="items[${index}][weight]" class="form-control"></td>
            <td><input name="items[${index}][actual_progress]" class="form-control"></td>
            <td><input name="items[${index}][activity]" class="form-control"></td>
            <td><input name="items[${index}][remarks]" class="form-control"></td>
            <td>
                <button type="button" class="btn btn-danger removeRow">-</button>
            </td>
        </tr>
        `;
        table.insertAdjacentHTML('beforeend', row);
        index++;
    }
    if(e.target.classList.contains('removeRow')){
        e.target.closest('tr').remove();
    }
});
</script>
@endsection
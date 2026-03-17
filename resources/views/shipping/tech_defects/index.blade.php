@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between mb-3">

        <h4>Tech & Defect Reports</h4>

        <a href="{{route('tech-defects.create')}}" class="btn btn-primary">
            + Add Report
        </a>
    </div>
    <form method="GET" class="mb-3">
        <select name="status" class="form-control w-25" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="OPEN">Open</option>
            <option value="ONGOING">On Going</option>
            <option value="COMPLETED">Completed</option>
        </select>
    </form>
    <form method="GET" action="{{ route('tech-defects.index') }}" class="mb-3">
        <div class="row">
            <div class="col-md-4">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search ID or Vessel">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary"> Search </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('tech-defects.index') }}" class="btn btn-secondary"> Reset </a>
            </div>
        </div>
    </form>
    <table class="table table-bordered" id="reportTable">
    <thead class="table-light">
        <tr>
            <th>ID</th>
            <th>Status</th>
            <th>3rd Party</th>
            <th>Date Created</th>
            <th>Date Identified</th>
            <th>Date Completed</th>
            <th>Vessel</th>
            <th>Defect Description</th>
            <th>Severity</th>
        </tr>
    </thead>
        <tbody>
            @foreach($reports as $r)
                <tr>
                    <td>
                        <a href="{{route('tech-defects.show',$r->id)}}"> TDR-{{ $r->created_at->format('Y') }}-{{ str_pad($r->id,4,'0',STR_PAD_LEFT) }} </a>
                    </td>
                    <td>
                        @if($r->status == 'Open')
                            <span class="badge bg-danger">Open</span>
                        @elseif($r->status == 'Ongoing')
                            <span class="badge bg-primary">Ongoing</span>
                        @elseif($r->status == 'Waiting 3rd Party')
                            <span class="badge bg-warning text-dark">Waiting 3rd Party</span>
                        @elseif($r->status == 'Done')
                            <span class="badge bg-secondary">Done</span>
                        @elseif($r->status == 'Completed')
                            <span class="badge bg-success">Complete</span>
                        @endif
                    </td>
                    <td>
                        @if($r->supports->count() == 0)
                            <span class="badge bg-secondary"> N/A </span>
                        @else
                            @php
                                $pending = $r->supports->where('status','Pending')->count();
                            @endphp
                            @if($pending > 0)
                                <span class="badge bg-warning"> Ongoing </span>
                            @else
                                <span class="badge bg-success"> Done </span>
                            @endif
                        @endif
                    </td>
                    <td>{{ $r->created_at->format('Y-m-d') }}</td>
                    <td>{{ $r->date_identified }}</td>
                    <td>
                        @if($r->date_completed)
                            {{ \Carbon\Carbon::parse($r->date_completed)->format('Y-m-d') }}
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $r->vessel->vessel_name ?? '' }}</td>
                    <td>{{ Str::limit($r->defect_description,40) }}</td>
                    <td>{{ $r->severity_level }}</td>
                </tr>
            @endforeach

        </tbody>
    </table>
    <div class="mt-3">
        {{ $reports->links() }}
    </div>
</div>
<script>
    $(document).ready(function(){
    $('#reportTable').DataTable({ pageLength:10 });
    });
</script>
@endsection
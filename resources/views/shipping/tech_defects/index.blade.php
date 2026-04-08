@extends('layouts.app')

@section('content')
<div class="container-fluid">

    {{-- HEADER --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-0 text-primary">🛠 Tech & Defect Reports</h4>
                <small class="text-muted">Manage and monitor vessel defects</small>
            </div>

            <a href="{{ route('tech-defects.create') }}" class="btn btn-primary shadow-sm">
                + Add Report
            </a>
        </div>
    </div>

    {{-- FILTERS --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('tech-defects.index') }}">
                <div class="row g-2 align-items-end">

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="Open" {{ request('status')=='Open'?'selected':'' }}>Open</option>
                            <option value="Ongoing" {{ request('status')=='Ongoing'?'selected':'' }}>Ongoing</option>
                            <option value="Completed" {{ request('status')=='Completed'?'selected':'' }}>Completed</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-control"
                               placeholder="Search ID or Vessel">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Search</button>
                    </div>

                    <div class="col-md-2">
                        <a href="{{ route('tech-defects.index') }}" class="btn btn-secondary w-100">Reset</a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>3rd Party</th>
                            <th>Date Created</th>
                            <th>Date Identified</th>
                            <th>Date Completed</th>
                            <th>Vessel</th>
                            <th>Description</th>
                            <th>Severity</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($reports as $r)
                        <tr>

                            {{-- ID --}}
                            <td>
                                <a href="{{ route('tech-defects.show',$r->id) }}" class="fw-bold text-primary">
                                    TDR-{{ $r->created_at->format('Y') }}-{{ str_pad($r->id,4,'0',STR_PAD_LEFT) }}
                                </a>
                            </td>

                            {{-- STATUS --}}
                            <td>
                                @if($r->status == 'Open')
                                    <span class="badge bg-danger">Open</span>
                                @elseif($r->status == 'Ongoing')
                                    <span class="badge bg-primary">Ongoing</span>
                                @elseif($r->status == 'Waiting 3rd Party')
                                    <span class="badge bg-warning text-dark">3rd Party</span>
                                @elseif($r->status == 'Completed')
                                    <span class="badge bg-success">Completed</span>
                                @else
                                    <span class="badge bg-secondary">{{ $r->status }}</span>
                                @endif
                            </td>

                            {{-- 3RD PARTY --}}
                            <td>
                                @if($r->supports->count() == 0)
                                    <span class="badge bg-secondary">N/A</span>
                                @else
                                    @php
                                        $pending = $r->supports->where('status','Pending')->count();
                                    @endphp

                                    @if($pending > 0)
                                        <span class="badge bg-warning">Ongoing</span>
                                    @else
                                        <span class="badge bg-success">Done</span>
                                    @endif
                                @endif
                            </td>

                            {{-- DATES --}}
                            <td>{{ $r->created_at->format('M d, Y') }}</td>
                            <td>{{ $r->date_identified }}</td>

                            <td>
                                @if($r->date_completed)
                                    {{ \Carbon\Carbon::parse($r->date_completed)->format('M d, Y') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            {{-- VESSEL --}}
                            <td>
                                <span class="fw-semibold">
                                    {{ $r->vessel->vessel_name ?? '-' }}
                                </span>
                            </td>

                            {{-- DESCRIPTION --}}
                            <td>
                                {{ \Illuminate\Support\Str::limit($r->defect_description, 40) }}
                            </td>

                            {{-- SEVERITY --}}
                            <td>
                                @if($r->severity_level == 'Critical')
                                    <span class="badge bg-danger">Critical</span>
                                @elseif($r->severity_level == 'Major')
                                    <span class="badge bg-warning text-dark">Major</span>
                                @else
                                    <span class="badge bg-info text-dark">Minor</span>
                                @endif
                            </td>

                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center p-4 text-muted">
                                No reports found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    {{-- PAGINATION --}}
    <div class="mt-3">
        {{ $reports->links() }}
    </div>

</div>
@endsection
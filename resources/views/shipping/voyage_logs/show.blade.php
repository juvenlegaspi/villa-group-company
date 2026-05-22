@extends('layouts.app')

@section('content')
@if($voyage->status === 'COMPLETED')
    <div class="alert alert-success">
        Voyage completed successfully.
    </div>
@endif

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('vessels.index') }}">Vessels</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ url('/shipping/vessels/' . $voyage->vessel_id) }}">{{ $voyage->vessel->vessel_name }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $voyage->voyage_code }}</li>
            </ol>
        </nav>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Voyage Information</h4>
                @if($voyage->status != 'COMPLETED' && $voyage->details->count() > 0 && $voyage->details->where('main_status', '!=', 'COMPLETED')->count() == 0 )
                    <form method="POST" action="{{ url('/shipping/voyage-logs/' . $voyage->voyage_id . '/complete-voyage') }}">
                        @csrf
                        <button class="btn btn-danger btn-sm">Complete Voyage</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <b>Date Created</b><br>
                    {{ optional($voyage->date_created)->format('M d, Y') }}
                </div>
                <div class="col-md-2">
                    <b>Voyage ID</b><br>
                    {{ $voyage->voyage_id }}
                </div>
                <div class="col-md-2">
                    <b>Port Origin</b><br>
                    {{ $voyage->port_location }}
                </div>

                <div class="col-md-3">
                    <b>Port Destination</b><br>
                    {{ $voyage->port_destination }}
                </div>
                <div class="col-md-2">
                    <b>Voyage Number</b><br>
                    {{ $voyage->voyage_no }}
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <b>Fuel ROB</b>
                        @if($voyage->status != 'COMPLETED')
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#updateFuelModal">
                                Update Fuel
                            </button>
                        @endif
                    </div>
                    {{ $voyage->fuel_rob }}
                </div>
                <div class="col-md-2">
                    <b>Cargo Type</b><br>
                    {{ $voyage->cargo_type }}
                </div>
                <div class="col-md-2">
                    <b>Cargo Volume</b><br>
                    {{ $voyage->cargo_volume }}
                </div>
                <div class="col-md-3">
                    <b>Crew on Board</b><br>
                    {{ $voyage->crew_on_board }}
                </div>
                <div class="col-md-2">
                    <b>ETA Next Port</b><br>
                    {{ $voyage->arrival_date ? $voyage->arrival_date->format('M d, Y') : '-' }}
                </div>
            </div>
        </div>
    </div>
    </div>
        <div class="card shadow-sm">
            <div class="card-header">
                @php
                    $details = $voyage->details;
                @endphp
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Tracking Timeline</h5>
                <div class="d-flex gap-2">
                    @if($voyage->status != 'COMPLETED' && ($voyage->details->count() == 0 || $voyage->details->where('main_status', '!=', 'COMPLETED')->count() == 0))
                        <button class="btn btn-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#addStatusModal">
                            Add Status
                        </button>
                    @endif
                    <a href="{{ route('voyage.pdf', $voyage->voyage_id) }}"
                    class="btn btn-danger btn-sm">
                        Download PDF
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body custom-scroll">
            @if($voyage->details->count() == 0 && $voyage->status !== 'COMPLETED')
                <div class="text-center p-3">
                    <p class="text-muted">No status yet.</p>
                    <!-- <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addStatusModal">Add First Status</button> -->
                </div>
            @endif
            <div class="timeline">
                @foreach($voyage->details as $detail)
                    @php
                        $statusName = optional(
                            \App\Models\ActivityStatusVoyage::find($detail->status)
                        )->name;
                        $totalAll = 0;
                        $isLastStatus = $loop->last;
                        $hasRunningActivity = $detail->activities->contains(function ($item) {
                            return is_null($item->end_date_time);
                        });
                        $isCompleted = $detail->main_status === 'COMPLETED';
                    @endphp
                    <div class="activity-table-container border-top pt-4 mt-4 bg-light rounded p-3">
                        {{-- HEADER --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-1">Status: {{ $statusName }}</h6>
                            @php
                                $hasRunning = $detail->activities->whereNull('end_date_time')->count();
                            @endphp
                            @if($loop->last && $hasRunning == 0 && $detail->main_status != 'COMPLETED')
                                <button class="btn btn-success btn-sm py-1 px-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#addActivityModal{{ $detail->dtl_id }}">
                                    Start Activity
                                </button>
                            @endif
                        </div>
                        {{-- TABLE --}}
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Remarks</th>
                                    <th>Edit Info</th>
                                    <th>Total Hours</th>
                                    <th>Time stamp</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $lastActivity = $detail->activities->last();
                                @endphp
                               @foreach($detail->activities as $act)
                                    @php
                                        $totalAll += $act->total_hours ?? 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $act->activity->name ?? '-' }}</td>
                                        <td>
                                            {{ $act->start_date_time 
                                                ? $act->start_date_time->format('M d, Y h:i A') 
                                                : '--' }}
                                        </td>
                                        <td>
                                            {{ $act->end_date_time 
                                                ? \Carbon\Carbon::parse($act->end_date_time)->format('M d, Y h:i A') 
                                                : '--' }}
                                        </td>
                                        <td>{{ $act->remarks ?? '-' }}</td>
                                        <td style="min-width:190px; font-size:10px; line-height:1;">
                                            @if($act->edit_reason)
                                                <div class="mb-1 small">
                                                    <strong>Reason:</strong><br>
                                                    {{ $act->edit_reason }}
                                                </div>
                                                <div class="mb-1 small">
                                                    <strong>Edited At:</strong><br>
                                                    {{ \Carbon\Carbon::parse($act->edited_at)->format('M d, Y h:i A') }}
                                                </div>
                                                @if($act->edit_attachment)
                                                    <a href="{{ asset('storage/' . $act->edit_attachment) }}"
                                                    target="_blank"
                                                    class="btn btn-info btn-sm py-0 px-2"
                                                    style="font-size:11px;">
                                                        Attachment
                                                    </a>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $act->total_hours ?? 0 }}</td>
                                        <td>
                                            {{ $act->updated_at 
                                                ? $act->updated_at->format('M d, Y h:i A') 
                                                : '--' }}
                                        </td>
                                        <td>
                                            @if(!$act->end_date_time)
                                                <button
                                                    class="btn btn-danger btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#endActivityModal{{ $act->activity_id }}"
                                                >
                                                    End
                                                </button>
                                                @php
                                                    $showFuelButton =
                                                        ($detail->status == 1 && $act->status_activity_id == 11) ||
                                                        ($detail->status == 9 && $act->status_activity_id == 63) ||
                                                        ($detail->status == 5 && $act->status_activity_id == 44) ||
                                                        ($detail->status == 4 && $act->status_activity_id == 32);
                                                @endphp
                                                @if($showFuelButton)
                                                    <button
                                                        class="btn btn-info btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#fuelModal{{ $act->activity_id }}"
                                                    >
                                                        Fuel
                                                    </button>
                                                @endif
                                            @else
                                                @if($lastActivity && $act->activity_id == $lastActivity->activity_id && !$act->edited_at && $detail->main_status != 'COMPLETED')
                                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editActivityModal{{ $act->activity_id }}">
                                                        Edit
                                                    </button>
                                                @endif
                                            @endif
                                        </td>
                                        
                                    </tr>
                                    <div class="modal fade" id="fuelModal{{ $act->activity_id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Fuel Bunkering</h5>
                                                    <button
                                                        type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal">
                                                    </button>
                                                </div>
                                                <form method="POST" action="{{ route('fuel.bunkering.store') }}">
                                                    @csrf
                                                    <input type="hidden" name="voyage_id" value="{{ $voyage->voyage_id }}">
                                                    <input type="hidden" name="voyage_detail_id" value="{{ $detail->dtl_id }}">
                                                    <input type="hidden" name="vessel_id" value="{{ $voyage->vessel_id }}">
                                                    <input type="hidden" name="activity_id" value="{{ $act->activity_id }}">
                                                    <div class="modal-body">
                                                        {{-- Fuel Balance --}}
                                                        <div class="mb-3">
                                                            <label>Fuel Balance</label>
                                                            <input
                                                                type="text"
                                                                name="beginning_fuel"
                                                                class="form-control"
                                                                value="{{ preg_replace('/[^0-9.]/', '', $voyage->fuel_rob) }}"
                                                                readonly>
                                                        </div>
                                                        {{-- Received Fuel --}}
                                                        <div class="mb-3">
                                                            <label>Received (Bunkering)</label>
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                name="received_fuel"
                                                                class="form-control receivedFuel"
                                                                placeholder="Enter received fuel"
                                                                required>
                                                        </div>
                                                        {{-- Total Fuel --}}
                                                        <div class="mb-3">
                                                            <label>Total Fuel</label>
                                                            <input
                                                                type="text"
                                                                name="total_fuel"
                                                                class="form-control totalFuel"
                                                                readonly>
                                                        </div>
                                                        {{-- Remarks --}}
                                                        <div class="mb-3">
                                                            <label>Remarks</label>
                                                            <textarea
                                                                name="remarks"
                                                                class="form-control"
                                                                rows="3"
                                                                placeholder="Enter remarks"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button class="btn btn-primary">
                                                            Save Fuel
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal fade" id="endActivityModal{{ $act->activity_id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">End Activity</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="{{ route('voyage.activity.end', $act->activity_id) }}">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label>End Date</label>
                                                            <input type="date"
                                                                name="end_date"
                                                                class="form-control"
                                                                required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>End Time</label>
                                                            <input type="time"
                                                                name="end_time"
                                                                class="form-control"
                                                                required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button class="btn btn-danger">
                                                            Confirm End
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal fade"
                                        id="confirmEndModal{{ $act->activity_id }}"
                                        tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        Activity Ended
                                                    </h5>

                                                    <button type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal">
                                                    </button>
                                                </div>

                                                <div class="modal-body text-center">
                                                    <h2>
                                                        What do you want to do next?
                                                    </h2>
                                                    <div class="d-flex justify-content-center gap-3 mt-3">

                                                        <button
                                                            type="button"
                                                            class="btn btn-primary btn-lg"
                                                            data-bs-dismiss="modal"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#addActivityModal{{ $detail->dtl_id }}">
                                                            Add Activity
                                                        </button>

                                                        <form method="POST"
                                                            action="{{ route('voyage.status.complete', $detail->dtl_id) }}">
                                                            @csrf

                                                            <button class="btn btn-success btn-lg">
                                                                Complete Status
                                                            </button>
                                                        </form>

                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal fade"
                                        id="editActivityModal{{ $act->activity_id }}"
                                        tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Activity End</h5>
                                                    <button type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal">
                                                    </button>
                                                </div>
                                                <form method="POST"
                                                    action="{{ route('voyage.activity.update', $act->activity_id) }}"
                                                    enctype="multipart/form-data">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label>Reason</label>
                                                            <textarea
                                                                name="edit_reason"
                                                                class="form-control"
                                                                required></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Attachment / Proof</label>

                                                            <input
                                                                type="file"
                                                                name="edit_attachment"
                                                                class="form-control">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>End Date</label>

                                                            <input type="date"
                                                                name="end_date"
                                                                class="form-control"
                                                                value="{{ \Carbon\Carbon::parse($act->end_date_time)->format('Y-m-d') }}"
                                                                required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>End Time</label>
                                                            <input type="time"
                                                                name="end_time"
                                                                class="form-control"
                                                                value="{{ \Carbon\Carbon::parse($act->end_date_time)->format('H:i') }}"
                                                                required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button class="btn btn-primary">
                                                            Update
                                                        </button>
                                                    </div>

                                                </form>

                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </tbody>
                            {{-- TOTAL --}}
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-end">Total All</th>
                                    <th>{{ number_format($totalAll, 2) }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>  
                        <br>  
                        {{-- COMPLETE BUTTON --}}
                        @php
                            $hasRunning = $detail->activities
                                ->whereNull('end_date_time')
                                ->count();
                        @endphp
                        {{-- UPDATE STATUS BUTTON --}}
                        @if(
                            $isLastStatus &&
                            !$isCompleted &&
                            $detail->activities->count() == 0
                        )
                        <button
                            class="btn btn-warning btn-sm me-1"
                            data-bs-toggle="modal"
                            data-bs-target="#updateStatusModal{{ $detail->dtl_id }}"
                        >
                            Update Status
                        </button>
                        @endif
                        @if($isLastStatus && !$isCompleted && $hasRunning == 0 && $detail->activities->count() > 0)
                            <form method="POST" action="{{ route('voyage.status.complete', $detail->dtl_id) }}">
                                @csrf
                                <button class="btn btn-primary btn-sm">
                                    Complete Status
                                </button>
                            </form>
                        @endif
                    </div>
                    <div class="modal fade"
                        id="updateStatusModal{{ $detail->dtl_id }}"
                        tabindex="-1">

                        <div class="modal-dialog">
                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title">Update Status</h5>

                                    <button type="button"
                                            class="btn-close"
                                            data-bs-dismiss="modal">
                                    </button>
                                </div>

                                <form method="POST"
                                    action="{{ route('voyage.status.update', $detail->dtl_id) }}">

                                    @csrf

                                    <div class="modal-body">

                                        <div class="mb-3">
                                            <label>Status</label>

                                            <select name="status_id"
                                                    class="form-control"
                                                    required>

                                                <option value="">
                                                    -- SELECT STATUS --
                                                </option>

                                                @foreach($statuses as $status)
                                                    <option value="{{ $status->id }}">
                                                        {{ $status->name }}
                                                    </option>
                                                @endforeach

                                            </select>
                                        </div>

                                    </div>

                                    <div class="modal-footer">
                                        <button class="btn btn-primary">
                                            Update Status
                                        </button>
                                    </div>

                                </form>

                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="addActivityModal{{ $detail->dtl_id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add Activity</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" action="{{ route('voyage.addActivity', $detail->dtl_id) }}">
                                        @csrf
                                        {{-- ACTIVITY DROPDOWN --}}
                                        <div class="mb-3">
                                            <label>Activity</label>
                                            <select name="activity_id" class="form-control" required>
                                                <option value="">-- SELECT ACTIVITY --</option>
                                                    @foreach($activities->where('activity_status_voyage_id', $detail->status) as $act)
                                                        <option value="{{ $act->id }}">
                                                            {{ $act->name }}
                                                        </option>
                                                    @endforeach
                                            </select>
                                        </div>
                                        {{-- LOCATION --}}
                                        {{--<div class="mb-3">
                                            <label>Port Location</label>
                                            <select name="port_location" class="form-control" required>
                                                <option value="">-- SELECT PORT --</option>
                                                @foreach($ports as $port)
                                                    <option value="{{ $port->port_name }}">
                                                        {{ $port->port_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>--}}
                                        <div class="mb-3">
                                            <label>Remarks</label>
                                            <textarea
                                                name="remarks"
                                                class="form-control"
                                                rows="3"
                                                placeholder="Enter remarks"></textarea>
                                        </div>
                                        @if($detail->status == 5)
                                            <div class="mb-3 cargo-load-section">
                                                <label>Running Load</label>
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <input type="number"
                                                            step="0.01"
                                                            name="running_load"
                                                            class="form-control"
                                                            placeholder="Enter running load">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <select name="load_unit" class="form-control">
                                                            <option value="">-- SELECT UNIT --</option>
                                                            <option value="Crates">Crates</option>
                                                            <option value="MT">MT</option>
                                                            <option value="LB">LB</option>
                                                            <option value="CBM">CBM</option>
                                                            <option value="L">L</option>
                                                            <option value="BBL">BBL</option>
                                                            <option value="Bushel">Bushel</option>
                                                            <option value="Bag/Sacks">Bag/Sacks</option>
                                                            <option value="Piece/Unit">Piece/Unit</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        <button class="btn btn-primary">Start</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="modal fade" id="addStatusModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('voyage.addDetail', $voyage->voyage_id) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <select name="status_id" id="status" class="form-control">
                                    <option value="">-- SELECT ONE --</option>
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)">
                            </div>
                        </div>
                        <br>
                        <button class="btn btn-primary">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="updateFuelModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Update Fuel ROB
                </h5>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>
            </div>
            <form method="POST"
                  action="{{ route('fuel.rob.store') }}">
                @csrf
                <input type="hidden"
                       name="voyage_id"
                       value="{{ $voyage->voyage_id }}">
                <input type="hidden"
                       name="vessel_id"
                       value="{{ $voyage->vessel_id }}">
                <input type="hidden"
                       name="beginning_fuel"
                       value="{{ preg_replace('/[^0-9.]/', '', $voyage->fuel_rob) }}">

                <div class="modal-body">
                    {{-- Beginning Fuel --}}
                    <div class="mb-3">
                        <label>Beginning Fuel</label>
                        <input type="text"
                               class="form-control"
                               value="{{ $voyage->fuel_rob }}"
                               readonly>
                    </div>
                    <div class="row">
                        {{-- Main Engine --}}
                        <div class="col-md-4 mb-3">
                            <label>Main Engine</label>
                            <input type="number"
                                   step="0.01"
                                   name="main_engine"
                                   id="main_engine"
                                   class="form-control fuel-input"
                                   value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Boiler</label>
                            <input type="number"
                                step="0.01"
                                name="boiler"
                                id="boiler"
                                class="form-control fuel-input"
                                value="0">
                        </div>
                        {{-- Auxiliary --}}
                        <div class="col-md-4 mb-3">
                            <label>Auxiliary Engine</label>
                            <input type="number"
                                   step="0.01"
                                   name="auxiliary_engine"
                                   id="auxiliary_engine"
                                   class="form-control fuel-input"
                                   value="0">
                        </div>
                        {{-- Others --}}
                        <div class="col-md-4 mb-3">
                            <label>Others</label>
                            <input type="number"
                                   step="0.01"
                                   name="others"
                                   id="others"
                                   class="form-control fuel-input"
                                   value="0">
                        </div>
                    </div>
                    {{-- Total Consumed --}}
                    <div class="mb-3">
                        <label>Total Consumed</label>
                        <input type="text"
                               name="total_consumed"
                               id="total_consumed"
                               class="form-control"
                               readonly>
                    </div>
                    {{-- Remaining --}}
                    <div class="mb-3">
                        <label>Remaining Fuel</label>
                        <input type="text"
                               name="remaining_fuel"
                               id="remaining_fuel"
                               class="form-control"
                               readonly>
                        <div id="fuelWarning"
                            class="text-danger fw-bold mt-2 d-none">
                            Insufficient fuel balance.
                        </div>
                    </div>
                    {{-- Remarks --}}
                    <div class="mb-3">
                        <label>Remarks</label>
                        <textarea name="remarks"
                                  class="form-control"
                                  rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" id="saveFuelBtn">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.fuel-input');
    const totalConsumed = document.getElementById('total_consumed');
    const remainingFuel = document.getElementById('remaining_fuel');
    const beginningFuel =
        parseFloat(
            "{{ preg_replace('/[^0-9.]/', '', $voyage->fuel_rob) }}"
        ) || 0;
    function computeFuel() {
        let main = parseFloat(document.getElementById('main_engine').value) || 0;
        let auxiliary = parseFloat(document.getElementById('auxiliary_engine').value) || 0;
        let boiler = parseFloat(document.getElementById('boiler').value) || 0;
        let others = parseFloat(document.getElementById('others').value) || 0;
        let total = main + auxiliary + boiler + others;
        let remaining = beginningFuel - total;
        totalConsumed.value = total.toFixed(2);
        remainingFuel.value = remaining.toFixed(2);
        const warning = document.getElementById('fuelWarning');
        const saveBtn = document.getElementById('saveFuelBtn');
        if (remaining <= 0) {
            remainingFuel.classList.add('is-invalid');
            warning.classList.remove('d-none');
            saveBtn.disabled = true;
        } else {
            remainingFuel.classList.remove('is-invalid');
            warning.classList.add('d-none');
            saveBtn.disabled = false;
        }
    }
    inputs.forEach(input => {
        input.addEventListener('input', computeFuel);
    });
    computeFuel();
});

</script>

@if(session('activityEnded'))

<script>
document.addEventListener('DOMContentLoaded', function () {

    let modal = new bootstrap.Modal(
        document.getElementById(
            'confirmEndModal{{ session("ended_activity_id") }}'
        )
    );

    modal.show();

});
</script>

@endif

@if(session('openAddStatus'))

<script>
document.addEventListener('DOMContentLoaded', function () {

    let modal = new bootstrap.Modal(
        document.getElementById('addStatusModal')
    );

    modal.show();

});
</script>

@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(modal => {
        let receivedInput = modal.querySelector('.receivedFuel');
        let totalInput = modal.querySelector('.totalFuel');
        if (receivedInput && totalInput) {
            let fuelBalance = parseFloat(
                modal.querySelector('input[readonly]').value
            ) || 0;
            function computeFuel() {
                let received = parseFloat(receivedInput.value) || 0;
                let total = fuelBalance + received;
                totalInput.value = total.toFixed(2) + ' Liters';
            }
            receivedInput.addEventListener('input', computeFuel);
            computeFuel();
        }
    });
});
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    width: 3px;
    height: 100%;
    background: #0d6efd;
}

.timeline-row {
    position: relative;
    margin-bottom: 30px;
}

.timeline-dot {
    position: absolute;
    left: -3px;
    top: 6px;
    width: 16px;
    height: 16px;
    background: #0d6efd;
    border-radius: 50%;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #e5e5e5;
}
.activity-table-container {
    max-height: 180px;   /* pwede nimo usbon */
    overflow-y: auto;
    font-size: 12px;
}

.activity-table-container table th,
.activity-table-container table td {
    padding: 4px 8px;
    vertical-align: middle;
}

.activity-table-container table th {
    font-size: 12px;
    background: #f8f9fa;
}

.activity-table-container table td {
    font-size: 12px;
}
.custom-scroll{
    max-height:300px;
    overflow-y:auto;
    padding-right:5px;
}

.custom-scroll::-webkit-scrollbar{
    width:8px;
}

.custom-scroll::-webkit-scrollbar-thumb{
    background:#bcbcbc;
    border-radius:10px;
}

.custom-scroll::-webkit-scrollbar-track{
    background:#f1f1f1;
}
</style>
@endsection

@extends('layouts.app')
@section('content')
@if($voyage->status == 'COMPLETED')
    <div class="alert alert-success">
        ✔ Voyage Completed
    </div>
@endif
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="/shipping/vessels">Vessels</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="/shipping/vessels/{{ $voyage->vessel_id }}"> {{ $voyage->vessel->vessel_name }} </a>
                </li>
                <li class="breadcrumb-item active"> 
                    {{ $voyage->voyage_code }}
                </li>
            </ol>
        </nav>
    </div>
    {{-- VOYAGE INFORMATION --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Voyage Information</h4>
                @if($voyage->status != 'COMPLETED' && $voyage->details->count() > 0 && $voyage->details->where('status','ACTIVE')->count() == 0)
                    <form method="POST" action="/shipping/voyage-logs/{{ $voyage->voyage_id }}/complete-voyage">
                        @csrf
                        <button class="btn btn-danger btn-sm"> COMPLETE VOYAGE </button>
                    </form>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <b>Date Created</b><br>
                    {{ $voyage->date_created }}
                </div>
            <div class="col-md-3">
                <b>Voyage ID</b><br>
                {{ $voyage->voyage_id }}
            </div>
            <div class="col-md-3">
                <b>Port Location</b><br>
                {{ $voyage->port_location }}
            </div>
            <div class="col-md-3">
                <b>Voyage Number</b><br>
                {{ $voyage->voyage_no }}
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-3">
                <b>Fuel ROB</b><br>
                {{ $voyage->fuel_rob }}
            </div>
            <div class="col-md-3">
                <b>Cargo Type</b><br>
                {{ $voyage->cargo_type }}
            </div>
            <div class="col-md-3">
                <b>Cargo Volume</b><br>
                {{ $voyage->cargo_volume }}
            </div>
            <div class="col-md-3">
                <b>Crew on Board</b><br>
                {{ $voyage->crew_on_board }}
            </div>
            <br><br>
        </div>

    </div>
</div>
{{-- TIMELINE --}}
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Tracking Timeline</h5>
    </div>
    <div class="card-body">
        @php
            $activeTrail = $voyage->details->where('date_time_ended', null)->first();
        @endphp
        @if($voyage->details->count() == 0 && $voyage->status != 'COMPLETED')
            <div class="text-center p-4">
                <p class="text-muted">No activities yet.</p>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addTrailModal"> ADD FIRST ACTIVITY </button>
            </div>
        @endif
        <div class="timeline">
            @foreach($voyage->details as $detail)
            <div class="timeline-row">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <div class="row">
                        <div class="col-md-3">
                            <b>Status:</b> {{ $detail->voyage_status }}
                        </div>
                        <div class="col-md-3">
                            <b>Activity:</b> {{ $detail->activity }}
                        </div>
                        <div class="col-md-3">
                            <b>Remarks:</b> {{ $detail->remarks ?? '-' }}
                        </div>
                        <div class="col-md-3">
                            <b>Total hours:</b>
                            @if($detail->date_time_ended)
                                {{ $detail->total_hours }}
                            @else
                                <span class="text-muted">--:--</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-muted mt-2">
                        Start: {{ \Carbon\Carbon::parse($detail->date_time_started)->format('M d, Y h:i A') }}
                    </div>
                    @if($detail->date_time_ended)
                        <div class="text-muted">
                            End: {{ \Carbon\Carbon::parse($detail->date_time_ended)->format('M d, Y h:i A') }}
                        </div>
                    @endif
                <br>
                    {{-- STATUS BADGE --}}
                    <div class="mb-2">
                        @if($detail->is_paused)
                            <span class="badge bg-warning">PAUSED</span>
                        @elseif(!$detail->date_time_ended)
                            <span class="badge bg-success">RUNNING</span>
                        @else
                            <span class="badge bg-secondary">ENDED</span>
                        @endif
                    </div>
                    {{-- BUTTONS --}}
                    @if(!$detail->date_time_ended && $loop->last)
                        <div class="d-flex gap-2">
                            {{-- PAUSE / RESUME --}}
                             @if(!$detail->is_paused)
                                <form method="POST" action="{{ url('/shipping/voyage-logs/'.$detail->dtl_id.'/pause') }}">
                                    @csrf
                                    <button class="btn btn-warning btn-sm">PAUSE</button>
                                </form>
                            @else
                                <form method="POST" action="{{ url('/shipping/voyage-logs/'.$detail->dtl_id.'/resume') }}">
                                    @csrf
                                    <button class="btn btn-success btn-sm">RESUME</button>
                                </form>
                            @endif
                            {{-- END BUTTON (only if not paused) --}}
                            @if(!$detail->is_paused)
                                <form method="POST" action="{{ url('/shipping/voyage-logs/'.$detail->dtl_id.'/end') }}">
                                    @csrf
                                    <button class="btn btn-danger btn-sm">END</button>
                                </form>
                            @endif
                        </div>
                    @endif
                    {{-- ENDED BUT NOT COMPLETED --}}
                    @if($detail->date_time_ended && $detail->status != 'COMPLETED' && $loop->last)
                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editTrail{{ $detail->dtl_id }}"> UPDATE </button>
                            <form method="POST" action="/shipping/voyage-logs/{{ $detail->dtl_id }}/complete">
                                @csrf
                                <button class="btn btn-success btn-sm"> COMPLETE </button>
                            </form>
                        </div>
                    @endif
                    {{-- COMPLETED --}}
                    @if($detail->status == 'COMPLETED' && $loop->last && $voyage->status != 'COMPLETED')
                        <div class="mt-3">
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#addTrailModal"> ADD </button>
                        </div>
                    @endif
                        @if($loop->last)
                            <a href="/shipping/voyage-logs/{{ $voyage->voyage_id }}/pdf" class="btn btn-dark btn-sm mt-2"> DOWNLOAD PDF </a>
                        @endif
                </div>
            </div>
            {{-- UPDATE MODAL --}}
            <div class="modal fade" id="editTrail{{ $detail->dtl_id }}" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update Trail</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    <div class="modal-body">
                        <form method="POST" action="{{ route('voyage-logs.update-trail', $detail->dtl_id) }}">
                            @csrf
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <select name="voyage_status" class="form-control">
                                        <option  {{ $detail->voyage_status=='AT BERTH'?'selected':'' }}> AT BERTH </option>
                                        <option  {{ $detail->voyage_status=='SAILING'?'selected':'' }}> SAILING </option>
                                        <option  {{ $detail->voyage_status=='ANCHORED'?'selected':'' }}> ANCHORED </option>
                                        <option  {{ $detail->voyage_status=='UNDER REPAIR'?'selected':'' }}> UNDER REPAIR </option>
                                        <option  {{ $detail->voyage_status=='DELAYED'?'selected':'' }}> DELAYED </option>
                                        <option  {{ $detail->voyage_status=='DRYDOCK'?'selected':'' }}> DRYDOCK </option>
                                        <option  {{ $detail->voyage_status=='SHIPSIDE'?'selected':'' }}> SHIPSIDE </option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="activity" value="{{ $detail->activity }}" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="remarks" value="{{ $detail->remarks }}" class="form-control">
                                </div>
                            </div>
                            <br>
                            <button class="btn btn-primary"> UPDATE </button>
                        </form>
                    </div>
                </div>
            </div>
            </div>
             @endforeach
        </div>
    </div>
</div>
{{-- ADD MODAL --}}
    <div class="modal fade" id="addTrailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            <div class="modal-body">
                <form method="POST" action="/shipping/voyage-logs/{{ $voyage->voyage_id }}/start">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <select name="voyage_status" class="form-control">
                                <option>--SELECT ONE--</option>
                                <option>AT BERTH</option>
                                <option>SAILING</option>
                                <option>ANCHORED</option>
                                <option>UNDER REPAIR</option>
                                <option>DELAYED</option>
                                <option>DRYDOCK</option>
                                <option>SHIPSIDE</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="activity" class="form-control" placeholder="Activity" style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="remarks" class="form-control" placeholder="Remarks" style="text-transform: uppercase;">
                        </div>
                    </div>
                    <br>
                    <button class="btn btn-primary"> START </button>
                </form>
            </div>
        </div>
    </div>
</div>
<style>
    .timeline{
        position:relative;
        padding-left:30px;
    }
    .timeline:before{
        content:'';
        position:absolute;
        left:10px;
        top:0;
        width:3px;
        height:100%;
        background:#0d6efd;
    }
    .timeline-row{
        position:relative;
        margin-bottom:30px;
    }
    .timeline-dot{
        position:absolute;
        left:-3px;
        top:6px;
        width:16px;
        height:16px;
        background:#0d6efd;
        border-radius:50%;
    }
    .timeline-content{
        background:#f8f9fa;
        padding:15px;
        border-radius:6px;
        border:1px solid #e5e5e5;
    }
</style>
<script>
    document.querySelectorAll('[id^="timer"]').forEach(function(timer){
    let start = timer.dataset.start;
    let ended = timer.dataset.ended;
    if(!ended){
        let startTime = new Date(start);
        setInterval(function(){
            let now = new Date();
            let diff = (now - startTime) / 1000;
            let hours = diff / 3600;
            timer.innerHTML = hours.toFixed(2);
        },1000);
    }
    });
</script>
@endsection

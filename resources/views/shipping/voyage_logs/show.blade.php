@extends('layouts.app')

@section('title', $voyage->voyage_code.' | '.$voyage->vessel->vessel_name)

@section('content')
@php
    $details = $voyage->details;
    $voyageCompleted = strtoupper((string) $voyage->status) === 'COMPLETED';
@endphp

<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <div class="mx-auto max-w-7xl">
        @if($errors->any())
            <div class="alert alert-danger mb-5" role="alert">
                <div class="flex items-start gap-3"><i class="bi bi-compass mt-0.5 shrink-0" aria-hidden="true"></i><div><p class="mb-1 font-extrabold">Unable to save the activity</p><ul class="m-0 list-disc space-y-1 pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
            </div>
        @endif
        @if(session('success'))
            <div class="alert alert-success mb-5" role="status"><i class="bi bi-check2-circle mr-2" aria-hidden="true"></i>{{ session('success') }}</div>
        @endif
        @if($voyageCompleted)
            <div class="alert alert-success mb-5" role="status"><i class="bi bi-check2-circle mr-2" aria-hidden="true"></i>Voyage completed successfully.</div>
        @endif

        <header class="mb-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <nav class="flex min-w-0 items-center gap-2" aria-label="Voyage navigation">
                <a class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 text-sm font-extrabold text-slate-700 no-underline shadow-sm hover:border-villa-500 hover:bg-villa-50 hover:text-villa-800" href="{{ route('vessels.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="hidden sm:inline">Vessels</span></a>
                <svg class="h-4 w-4 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                <a class="min-w-0 truncate rounded-lg px-2 py-1 text-sm font-bold text-villa-700 no-underline hover:bg-villa-50 hover:text-villa-900" href="{{ route('vessels.show', $voyage->vessel_id) }}">{{ $voyage->vessel->vessel_name }}</a>
                <svg class="h-4 w-4 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                <span class="shrink-0 rounded-lg bg-villa-700 px-3 py-1.5 text-xs font-extrabold text-white shadow-sm">{{ $voyage->voyage_code }}</span>
            </nav>
            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-extrabold uppercase tracking-wider ring-1 ring-inset {{ $voyageCompleted ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-blue-100 text-blue-800 ring-blue-600/20' }}">{{ $voyage->status ?: 'OPEN' }}</span>
        </header>

        <article class="relative mb-5 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-villa-gold via-villa-600 to-villa-900"></div>
            <header class="flex flex-col justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
                <div class="flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-2xl bg-villa-50 text-xl text-villa-700"><i class="bi bi-compass"></i></span><div><p class="mb-0.5 text-xs font-extrabold uppercase tracking-wider text-villa-600">{{ $voyage->voyage_code }}</p><h1 class="text-xl font-extrabold text-slate-950">Voyage Information</h1></div></div>
                @if(!$voyageCompleted && $details->count() > 0 && $details->where('main_status', '!=', 'COMPLETED')->count() == 0)
                    <form method="POST" action="{{ url('/shipping/voyage-logs/' . $voyage->voyage_id . '/complete-voyage') }}">@csrf<button class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 text-sm font-extrabold text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-100"><i class="bi bi-check2-circle"></i>Complete Voyage</button></form>
                @endif
            </header>

            <dl class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 sm:p-6 lg:grid-cols-4 xl:grid-cols-6">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Date Created</dt><dd class="mt-1 text-sm font-bold text-slate-800">{{ optional($voyage->date_created)->format('M d, Y') ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Voyage ID</dt><dd class="mt-1 text-sm font-bold text-slate-800">{{ $voyage->voyage_id }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Port Origin</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $voyage->port_location ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Port Destination</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $voyage->port_destination ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Current Location</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $voyage->current_location ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Voyage Number</dt><dd class="mt-1 text-sm font-bold text-slate-800">{{ $voyage->voyage_no ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-3.5"><div class="flex items-start justify-between gap-2"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-amber-700">Fuel ROB</dt>@if(!$voyageCompleted)<button class="rounded-lg bg-amber-500 px-2 py-1 text-[.62rem] font-extrabold text-slate-950 shadow-sm hover:bg-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100" data-bs-toggle="modal" data-bs-target="#updateFuelModal">Update</button>@endif</div><dd class="mt-1 text-sm font-extrabold text-slate-900">{{ $voyage->fuel_rob ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Cargo Type</dt><dd class="mt-1 break-words text-sm font-bold text-slate-800">{{ $voyage->cargo_type ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Cargo Volume</dt><dd class="mt-1 text-sm font-bold text-slate-800">{{ $voyage->cargo_volume ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">Crew on Board</dt><dd class="mt-1 text-sm font-bold text-slate-800">{{ $voyage->crew_on_board ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5"><dt class="text-[.66rem] font-extrabold uppercase tracking-wider text-slate-400">ETA Next Port</dt><dd class="mt-1 text-sm font-bold text-slate-800">{{ $voyage->arrival_date ? $voyage->arrival_date->format('M d, Y') : '-' }}</dd></div>
            </dl>
        </article>

        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <header class="flex flex-col justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
                <div><p class="mb-0.5 text-xs font-extrabold uppercase tracking-wider text-villa-600">Operational progress</p><h2 class="text-lg font-extrabold text-slate-950">Tracking Timeline</h2></div>
                <div class="flex flex-wrap gap-2">
                    @if(!$voyageCompleted && ($details->count() == 0 || $details->where('main_status', '!=', 'COMPLETED')->count() == 0))
                        <button class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-villa-700 px-4 text-sm font-extrabold text-white shadow-sm hover:bg-villa-900 focus:outline-none focus:ring-4 focus:ring-villa-100" data-bs-toggle="modal" data-bs-target="#addStatusModal"><i class="bi bi-plus"></i>Add Status</button>
                    @endif
                    <a href="{{ route('voyage.pdf', $voyage->voyage_id) }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 text-sm font-extrabold text-rose-700 no-underline shadow-sm hover:border-rose-300 hover:bg-rose-100 hover:text-rose-800 focus:outline-none focus:ring-4 focus:ring-rose-100"><i class="bi bi-file-earmark-text"></i>Download PDF</a>
                </div>
            </header>
            <div class="custom-scroll p-5 sm:p-6">
                @if($details->count() == 0)
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center"><span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-white text-xl text-slate-400 shadow-sm"><i class="bi bi-compass"></i></span><p class="font-extrabold text-slate-700">No tracking status yet</p><p class="mt-1 text-xs text-slate-500">The vessel's operational timeline will appear here.</p></div>
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
                    <div class="activity-table-container timeline-entry">
                        {{-- HEADER --}}
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div><span class="mb-1 block text-[.65rem] font-extrabold uppercase tracking-wider text-villa-600">Voyage status</span><h3 class="text-base font-extrabold text-slate-900">{{ $statusName ?: 'Unspecified Status' }}</h3></div>
                            @php
                                $hasRunning = $detail->activities->whereNull('end_date_time')->count();
                            @endphp
                            @if($loop->last && $hasRunning == 0 && $detail->main_status != 'COMPLETED')
                                <button class="btn btn-success btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#addActivityModal{{ $detail->dtl_id }}">
                                    Start Activity
                                </button>
                            @endif
                        </div>
                        {{-- TABLE --}}
                        <table class="table table-sm table-hover mb-0 min-w-[1000px]">
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
                                                    <a href="{{ route('voyage.activity.attachment', $act->activity_id) }}"
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
                                                            <input type="text"
                                                                name="end_time"
                                                                class="form-control"
                                                                placeholder="HH:mm"
                                                                maxlength="5"
                                                                required>
                                                            <small class="text-muted">24-hour format only. Example: 14:30</small>
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
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div><p class="mb-1 text-[.65rem] font-extrabold uppercase tracking-wider text-villa-600">{{ $statusName ?: 'Voyage status' }}</p><h5 class="modal-title">Start Activity</h5></div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" action="{{ route('voyage.addActivity', $detail->dtl_id) }}">
                                        @csrf
                                        <input type="hidden" name="voyage_detail_id" value="{{ $detail->dtl_id }}">
                                        {{-- ACTIVITY DROPDOWN --}}
                                        <div class="mb-3">
                                            <label for="activity-{{ $detail->dtl_id }}">Activity</label>
                                            <select id="activity-{{ $detail->dtl_id }}" name="activity_id" class="form-select activity-select" required>
                                                <option value="">-- SELECT ACTIVITY --</option>
                                                    @foreach($activities->where('activity_status_voyage_id', $detail->status) as $act)
                                                        <option value="{{ $act->id }}" data-cargo-movement="{{ $act->cargoMovementType() }}" @selected(old('activity_id') == $act->id)>
                                                            {{ $act->name }}
                                                        </option>
                                                    @endforeach
                                            </select>
                                        </div>
                                        {{-- LOCATION --}}
                                        <div class="mb-3">
                                            <label for="port-{{ $detail->dtl_id }}">Current Port Location</label>
                                            <select id="port-{{ $detail->dtl_id }}" name="port_location_id" class="form-select" required>
                                                <option value="">-- SELECT PORT --</option>

                                                @foreach($ports as $port)
                                                    <option value="{{ $port->id }}" @selected(old('port_location_id') == $port->id)>
                                                        {{ $port->port_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="remarks-{{ $detail->dtl_id }}">Remarks</label>
                                            <textarea
                                                id="remarks-{{ $detail->dtl_id }}"
                                                name="remarks"
                                                class="form-control"
                                                rows="3"
                                                placeholder="Enter remarks">{{ old('remarks') }}</textarea>
                                        </div>
                                        <div class="cargo-load-section d-none mb-4 rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                                            <div>
                                                <div class="mb-3"><p class="mb-1 text-sm font-extrabold text-amber-900">Cargo Operation</p><p class="m-0 text-xs leading-5 text-amber-800">Enter the running quantity and its measurement unit.</p></div>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_140px]">
                                                    <div>
                                                        <label for="running-load-{{ $detail->dtl_id }}">Running Total Load</label>
                                                        <input type="number"
                                                            id="running-load-{{ $detail->dtl_id }}"
                                                            step="0.01"
                                                            name="running_load"
                                                            class="form-control"
                                                            value="{{ old('running_load') }}"
                                                            placeholder="Enter running load">
                                                    </div>
                                                    <div>
                                                        <label for="load-unit-{{ $detail->dtl_id }}">Unit</label>
                                                        <select id="load-unit-{{ $detail->dtl_id }}" name="load_unit" class="form-select">
                                                            <option value="">-- SELECT UNIT --</option>
                                                            @foreach(['Crates', 'MT', 'LB', 'CBM', 'L', 'BBL', 'Bushel', 'Bag/Sacks', 'Piece/Unit'] as $unit)
                                                                <option value="{{ $unit }}" @selected(old('load_unit') === $unit)>{{ $unit }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label for="cargo-type-{{ $detail->dtl_id }}">Cargo Type</label>
                                                        <input type="text"
                                                            id="cargo-type-{{ $detail->dtl_id }}"
                                                            name="cargo_type"
                                                            class="form-control"
                                                            value="{{ old('cargo_type') }}"
                                                            placeholder="Enter cargo type">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-plus"></i>Start Activity</button></div>
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
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div><p class="mb-1 text-[.65rem] font-extrabold uppercase tracking-wider text-villa-600">Tracking Timeline</p><h5 class="modal-title">Add Voyage Status</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('voyage.addDetail', $voyage->voyage_id) }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="status">Status</label>
                                <select name="status_id" id="status" class="form-select" required>
                                    <option value="">-- SELECT ONE --</option>
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="status-remarks">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                                <textarea id="status-remarks" name="remarks" class="form-control" rows="3" placeholder="Enter status remarks"></textarea>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-plus"></i>Add Status</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</section>
<div class="modal fade" id="updateFuelModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
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
document.querySelectorAll('input[name="end_time"]').forEach(function (input) {
    input.addEventListener('input', function () {
        let value = this.value.replace(/[^\d:]/g, '');

        // Insert the separator after the user enters the first two digits.
        if (!value.includes(':') && value.length >= 2) {
            value = value.substring(0, 2) + ':' + value.substring(2);
        }

        // Limit to HH:mm format length
        if (value.length > 5) {
            value = value.substring(0, 5);
        }

        this.value = value;
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.activity-select').forEach(function(select) {
        select.addEventListener('change', function() {
            let modal = this.closest('.modal');
            let cargoSection = modal.querySelector('.cargo-load-section');
            const selectedOption = this.options[this.selectedIndex];
            const tracksCargo = Boolean(selectedOption?.dataset.cargoMovement);
            if (tracksCargo) {
                cargoSection.classList.remove('d-none');
            } else {
                cargoSection.classList.add('d-none');
            }
        });
        select.dispatchEvent(new Event('change'));
    });

    const failedDetailId = @js(old('voyage_detail_id'));
    if (failedDetailId) {
        const failedModal = document.getElementById(`addActivityModal${failedDetailId}`);
        if (failedModal) bootstrap.Modal.getOrCreateInstance(failedModal).show();
    }
});
</script>

@if(session('invalidEndTime'))

<script>
document.addEventListener('DOMContentLoaded', () => {
    VillaDialog.alert({
        title: 'Invalid Date Time',
        text: 'End datetime must be ahead of start datetime.',
        tone: 'danger'
    });
});
</script>

@endif

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
    padding-left: 34px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 9px;
    top: 20px;
    bottom: 20px;
    width: 2px;
    border-radius: 999px;
    background: linear-gradient(#285794, #a9bdd8);
}

.timeline-entry {
    position: relative;
    margin: 0 0 18px;
    padding: 18px;
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(30, 55, 90, .06);
}

.timeline-entry::before {
    content: '';
    position: absolute;
    left: -33px;
    top: 24px;
    width: 17px;
    height: 17px;
    border: 4px solid #dbeafe;
    border-radius: 999px;
    background: #285794;
    box-shadow: 0 0 0 4px #fff;
}

.activity-table-container table th,
.activity-table-container table td {
    padding: 10px 12px;
    vertical-align: middle;
}

.activity-table-container table th {
    border-color: #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.activity-table-container table td {
    font-size: 12px;
    border-color: #edf2f7;
    color: #334155;
}

.custom-scroll {
    max-height: 680px;
    overflow-y: auto;
    scrollbar-gutter: stable;
}

.custom-scroll::-webkit-scrollbar {
    width: 8px;
}

.custom-scroll::-webkit-scrollbar-thumb {
    border: 2px solid #fff;
    border-radius: 999px;
    background: #b6c4d7;
}

.custom-scroll::-webkit-scrollbar-track {
    background: transparent;
}

@media (max-width: 640px) {
    .timeline { padding-left: 24px; }
    .timeline::before { left: 5px; }
    .timeline-entry { padding: 14px; border-radius: 15px; }
    .timeline-entry::before { left: -25px; width: 14px; height: 14px; border-width: 3px; }
}
</style>
@endsection

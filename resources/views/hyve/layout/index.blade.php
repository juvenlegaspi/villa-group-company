@extends('layouts.app')

@section('content')
@php
    $featuredRooms = collect(array_merge($leftRooms ? [$leftRooms[0] ?? null] : [], $rightRooms))->filter()->values();
    $privateRooms = collect($leftRooms)->slice(1)->values();
@endphp

<style>
.hyve-admin-shell { position: relative; overflow-x: hidden; padding-bottom: 2rem; }
.hyve-admin-shell::before { content: ''; position: absolute; inset: 0 0 auto; height: 22rem; background: radial-gradient(circle at top, rgba(196, 156, 91, 0.22), transparent 55%); pointer-events: none; }
.hyve-admin-content { position: relative; z-index: 1; }
.hyve-card { border: 1px solid rgba(22, 49, 41, 0.1); background: rgba(255, 255, 255, 0.92); border-radius: 2rem; box-shadow: 0 28px 80px rgba(18, 24, 21, 0.08); backdrop-filter: blur(10px); }
.hyve-pill { display: inline-flex; align-items: center; gap: 0.55rem; border-radius: 999px; padding: 0.8rem 1.15rem; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; }
.hyve-pill-dot { width: 0.65rem; height: 0.65rem; border-radius: 999px; }
.hyve-pill--available { background: rgba(16, 185, 129, 0.1); color: #0f8b63; border: 1px solid rgba(16, 185, 129, 0.22); }
.hyve-pill--booked { background: rgba(245, 158, 11, 0.12); color: #c77700; border: 1px solid rgba(245, 158, 11, 0.22); }
.hyve-pill--occupied { background: rgba(239, 68, 68, 0.1); color: #d33b3b; border: 1px solid rgba(239, 68, 68, 0.22); }
.hyve-layout-hero { padding: 2rem; }
.hyve-section-label { font-size: 0.78rem; font-weight: 700; letter-spacing: 0.32em; text-transform: uppercase; color: #8c692c; }
.hyve-title { font-family: Georgia, 'Times New Roman', serif; font-size: clamp(2rem, 3vw, 3rem); line-height: 0.98; letter-spacing: -0.04em; color: #18130f; }
.hyve-copy { color: #5f5449; line-height: 1.85; }
.hyve-date-box { min-width: 17rem; }
.hyve-input { border: 1px solid rgba(22, 49, 41, 0.12); border-radius: 1rem; background: #fff; padding: 0.9rem 1rem; color: #18130f; }
.hyve-input:focus { outline: none; border-color: #c49c5b; box-shadow: 0 0 0 0.2rem rgba(196, 156, 91, 0.12); }
.hyve-btn-dark { border: none; border-radius: 999px; background: #163129; color: #fff; padding: 0.9rem 1.45rem; font-size: 0.82rem; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; }
.hyve-btn-dark:hover { background: #10241f; color: #fff; }
.hyve-feature-grid { display: grid; gap: 0.9rem; }
.hyve-feature-grid--top { grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr); }
.hyve-feature-grid--private { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.hyve-room-card, .hyve-seat-card { width: 100%; border: 1px solid rgba(22, 49, 41, 0.12); background: #f8f3ec; border-radius: 1.5rem; padding: 1rem; text-align: left; transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease; }
.hyve-room-card:hover, .hyve-seat-card:hover { transform: translateY(-2px); border-color: #c49c5b; box-shadow: 0 18px 35px rgba(18, 24, 21, 0.09); }
.hyve-room-card--featured { min-height: 8.25rem; }
.hyve-room-card--conference { min-height: 9.5rem; }
.hyve-room-meta { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: #8c692c; }
.hyve-room-name { margin-top: 0.65rem; color: #163129; font-weight: 700; line-height: 1.02; }
.hyve-room-name--featured { font-size: clamp(2rem, 2.7vw, 3rem); letter-spacing: -0.05em; }
.hyve-room-name--private { font-size: 1.15rem; }
.hyve-room-copy { margin-top: 0.7rem; font-size: 0.92rem; line-height: 1.65; color: #5f5449; }
.hyve-room-state { margin-top: 0.9rem; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: rgba(22, 49, 41, 0.72); }
.hyve-table-zone { border: 1px solid rgba(22, 49, 41, 0.1); background: #f8f3ec; border-radius: 1.5rem; padding: 1.1rem; }
.hyve-table-zone-grid { display: grid; gap: 0.9rem; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.hyve-table-card { min-width: 0; border: 1px solid rgba(22, 49, 41, 0.1); background: #fcfaf7; border-radius: 1.35rem; padding: 1rem; }
.hyve-table-head { display: flex; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.9rem; }
.hyve-table-name { font-size: 2rem; font-weight: 700; line-height: 1; letter-spacing: -0.04em; color: #163129; }
.hyve-table-seats { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.18em; color: #5f5449; }
.hyve-seat-grid { display: grid; gap: 0.65rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.hyve-seat-grid.hyve-seat-grid--wide { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.hyve-seat-card { background: #fff; border-radius: 1rem; padding: 0.9rem 0.7rem; text-align: center; }
.hyve-seat-label { font-size: 1rem; font-weight: 700; line-height: 1; color: #163129; }
.hyve-seat-state { margin-top: 0.65rem; font-size: 0.64rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(22, 49, 41, 0.72); }
.hyve-layout-note { border: 1px solid rgba(22, 49, 41, 0.1); background: #f8f3ec; border-radius: 1.5rem; padding: 1.2rem 1.25rem; }
.available { background: #dff5ec !important; }
.occupied { background: #fde7e7 !important; }
.reserved { background: #fff2d9 !important; }
.hyve-room-modal.hidden { display: none !important; }
.hyve-room-modal { position: fixed; inset: 0; z-index: 1080; display: flex; align-items: center; justify-content: center; background: rgba(24, 19, 15, 0.65); padding: 1.5rem 1rem; backdrop-filter: blur(10px); }
.hyve-room-modal__panel { display: flex; flex-direction: column; width: 100%; max-width: 64rem; height: calc(100vh - 3rem); overflow: hidden; border-radius: 2rem; border: 1px solid rgba(255,255,255,0.1); background: #163129; color: #fff; box-shadow: 0 35px 120px rgba(12,18,16,0.42); }
.hyve-room-modal__hero { position: relative; overflow: hidden; border-bottom: 1px solid rgba(255,255,255,0.08); background: radial-gradient(circle at top left, rgba(196,156,91,0.25), transparent 45%), linear-gradient(135deg, rgba(255,255,255,0.04), rgba(255,255,255,0)); padding: 1.5rem 1.5rem; }
.hyve-room-modal__body { min-height: 0; flex: 1; overflow-y: auto; }
.hyve-room-chip { border-radius: 999px; border: 1px solid rgba(255,255,255,0.12); padding: 0.6rem 0.95rem; font-size: 0.76rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; }
.hyve-room-chip--available { border-color: rgba(16,185,129,0.45); background: rgba(16,185,129,0.12); color: #8ff0cd; }
.hyve-room-chip--booked { border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.12); color: #ffd082; }
.hyve-room-card-dark { border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.07); border-radius: 1.5rem; padding: 1.25rem; }
.hyve-room-close { display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 999px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.08); color: #fff; font-size: 1.6rem; line-height: 1; }
.hyve-room-close:hover { background: rgba(255,255,255,0.14); color: #fff; }
body.hyve-room-modal-open { overflow: hidden; }
@media (max-width: 1399px) { .hyve-table-zone-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 1199px) { .hyve-feature-grid--top, .hyve-feature-grid--private, .hyve-table-zone-grid { grid-template-columns: 1fr; } }
@media (max-width: 767px) { .hyve-layout-hero { padding: 1.2rem; } .hyve-card { border-radius: 1.4rem; } .hyve-table-zone, .hyve-layout-note, .hyve-room-card, .hyve-seat-card { border-radius: 1.15rem; } .hyve-room-modal__panel { height: calc(100vh - 1.5rem); border-radius: 1.25rem; } }
</style>

<div class="hyve-admin-shell">
    <div class="container-fluid hyve-admin-content">
        <div class="hyve-card hyve-layout-hero mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-4">
                <div>
                    <p class="hyve-section-label mb-3">Live Room Layout</p>
                    <h1 class="hyve-title mb-3">Check exact room availability.</h1>
                    <p class="hyve-copy mb-0" style="max-width: 760px;">
                        Pick a date, review the legend, then click any room or table to open a schedule popup for that day. The popup now follows the same available and booked slot view used on the HYVE website.
                    </p>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-3 align-items-stretch">
                    <form method="GET" action="{{ url('/hyve/layout') }}" class="d-flex flex-column flex-sm-row gap-3 align-items-end">
                        <label class="hyve-date-box w-100">
                            <span class="d-block mb-2 text-uppercase fw-semibold" style="font-size:0.72rem; letter-spacing:0.2em; color:#163129;">Filter Date</span>
                            <input id="layout-date" type="date" name="date" value="{{ $selectedDate }}" class="form-control hyve-input">
                        </label>
                        <button type="submit" class="hyve-btn-dark">Load Layout</button>
                    </form>

                    <a href="{{ url('/hyve/booking') }}" class="btn btn-outline-dark rounded-pill px-4 py-3 text-uppercase fw-semibold" style="letter-spacing:0.18em; font-size:0.78rem; border-color:rgba(22,49,41,0.12); color:#163129;">
                        Open Booking List
                    </a>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-4">
                <span class="hyve-pill hyve-pill--available"><span class="hyve-pill-dot" style="background:#10b981"></span>Available</span>
                <span class="hyve-pill hyve-pill--booked"><span class="hyve-pill-dot" style="background:#f59e0b"></span>Booked</span>
                <span class="hyve-pill hyve-pill--occupied"><span class="hyve-pill-dot" style="background:#ef4444"></span>Occupied</span>
            </div>
        </div>

        <div class="hyve-card p-4 p-xl-4">
            <div class="hyve-feature-grid">
                <div class="hyve-feature-grid hyve-feature-grid--top mb-3">
                    @foreach ($featuredRooms as $room)
                        <button type="button" class="hyve-room-card hyve-room-card--featured {{ $room['name'] === 'Conference Room' ? 'hyve-room-card--conference' : '' }} {{ $room['status_class'] }} js-room-trigger" data-room='@json($room)'>
                            <div class="hyve-room-meta">{{ $room['name'] === 'Conference Room' ? 'Zeal Room (8 Seats)' : 'Tenacity Office (4 Seats)' }}</div>
                            <div class="hyve-room-name hyve-room-name--featured">{{ $room['label'] }}</div>
                            <div class="hyve-room-copy">{{ $room['description'] ?: 'No room description available.' }}</div>
                            <div class="hyve-room-state">{{ $room['status_label'] }}</div>
                        </button>
                    @endforeach
                </div>

                <div class="hyve-feature-grid hyve-feature-grid--private mb-4">
                    @foreach ($privateRooms as $room)
                        <button type="button" class="hyve-room-card {{ $room['status_class'] }} js-room-trigger" data-room='@json($room)'>
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="hyve-room-name hyve-room-name--private">{{ $room['label'] }}</div>
                                    <div class="hyve-room-copy mt-1">Fortitude Office (2 Seats)</div>
                                </div>
                                <div class="hyve-room-state mt-0">{{ $room['status_label'] }}</div>
                            </div>
                        </button>
                    @endforeach
                </div>

                <div class="hyve-table-zone">
                    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
                        <div>
                            <p class="hyve-room-meta mb-2">Shared Table Zone</p>
                            <h2 class="mb-0" style="font-size:2rem; line-height:1.12; letter-spacing:-0.04em; color:#163129; font-weight:700;">Browse Every Shared Table At A Glance</h2>
                        </div>
                        <p class="hyve-copy mb-0" style="max-width: 520px; font-size:0.92rem;">
                            Each table card stays compact but readable so the admin side matches the public website layout while still letting you open seat schedules instantly.
                        </p>
                    </div>

                    <div class="hyve-table-zone-grid">
                        @foreach ($tables as $table)
                            @php
                                $tableRoomCount = collect($table['rows'])->flatten(1)->count();
                                $wideTable = $tableRoomCount > 4;
                            @endphp
                            <div class="hyve-table-card">
                                <div class="hyve-table-head">
                                    <div>
                                        <p class="hyve-room-meta mb-1">Shared Table</p>
                                        <div class="hyve-table-name">{{ $table['name'] }}</div>
                                    </div>
                                    <div class="hyve-table-seats">{{ $tableRoomCount }} Seats</div>
                                </div>

                                <div class="hyve-seat-grid {{ $wideTable ? 'hyve-seat-grid--wide' : '' }}">
                                    @foreach (collect($table['rows'])->flatten(1) as $seat)
                                        <button type="button" class="hyve-seat-card {{ $seat['status_class'] }} js-room-trigger" data-room='@json($seat)'>
                                            <div class="hyve-seat-label">{{ $seat['label'] }}</div>
                                            <div class="hyve-seat-state">{{ $seat['status_label'] }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="hyve-layout-note mt-4">
                <p class="hyve-room-meta mb-2">Layout Viewing</p>
                <h3 class="mb-2" style="font-size:1.45rem; font-weight:700; color:#163129;">Click any room to open its schedule popup.</h3>
                <p class="hyve-copy mb-0" style="font-size:0.92rem; max-width: 760px;">
                    The popup now shows the same slot view as the website: next available window, count of open windows, count of booked windows, available slot chips, unavailable slot chips, and a combined booking details timeline.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="hyve-room-modal hidden" data-room-modal aria-hidden="true">
    <div class="hyve-room-modal__panel">
        <div class="hyve-room-modal__hero">
            <div class="d-flex justify-content-between align-items-start gap-4">
                <div style="max-width: 42rem;">
                    <p class="text-uppercase fw-semibold mb-3" style="font-size:0.75rem; letter-spacing:0.24em; color:#c49c5b;">Room Schedule</p>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <h3 class="mb-0" style="font-size:2rem; font-weight:700; letter-spacing:-0.03em;" data-room-detail-name>No room selected yet</h3>
                        <span class="rounded-pill px-3 py-2" style="border:1px solid rgba(255,255,255,0.12); background:rgba(255,255,255,0.08); font-size:0.7rem; font-weight:700; letter-spacing:0.2em; text-transform:uppercase; color:rgba(255,255,255,0.82);" data-room-detail-type>
                            Layout View
                        </span>
                    </div>
                    <p class="mt-3 mb-0" style="font-size:0.92rem; line-height:1.9; color:rgba(255,255,255,0.72);" data-room-detail-meta>
                        Click any room or table on the layout to inspect the available and occupied time slots for the filtered date.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-pill px-3 py-2" style="background:rgba(255,255,255,0.1); font-size:0.72rem; font-weight:700; letter-spacing:0.2em; text-transform:uppercase; color:rgba(255,255,255,0.82);" data-room-detail-status>
                        Waiting for selection
                    </div>
                    <button type="button" class="hyve-room-close" data-room-modal-close aria-label="Close room schedule">x</button>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-12 col-md-4">
                    <div class="hyve-room-card-dark">
                        <p class="mb-2 text-uppercase fw-semibold" style="font-size:0.68rem; letter-spacing:0.18em; color:#c49c5b;">Next Available</p>
                        <p class="mb-1 fw-semibold" style="font-size:1.15rem;" data-room-detail-next-slot>No slot loaded yet</p>
                        <p class="mb-0" style="font-size:0.9rem; color:rgba(255,255,255,0.62);">Earliest available schedule for the selected date.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="hyve-room-card-dark">
                        <p class="mb-2 text-uppercase fw-semibold" style="font-size:0.68rem; letter-spacing:0.18em; color:#c49c5b;">Available Slots</p>
                        <p class="mb-1 fw-semibold" style="font-size:1.15rem;" data-room-detail-available-count>0 open</p>
                        <p class="mb-0" style="font-size:0.9rem; color:rgba(255,255,255,0.62);">Remaining schedules still open for booking.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="hyve-room-card-dark">
                        <p class="mb-2 text-uppercase fw-semibold" style="font-size:0.68rem; letter-spacing:0.18em; color:#c49c5b;">Booked Slots</p>
                        <p class="mb-1 fw-semibold" style="font-size:1.15rem;" data-room-detail-booked-count>0 reserved</p>
                        <p class="mb-0" style="font-size:0.9rem; color:rgba(255,255,255,0.62);">Schedules already booked or occupied.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="hyve-room-modal__body">
            <div class="row g-4 px-4 py-4 m-0" style="background:#163129;">
                <div class="col-12 col-md-6">
                    <div class="hyve-room-card-dark h-100">
                        <p class="mb-3 text-uppercase fw-semibold" style="font-size:0.74rem; letter-spacing:0.18em; color:#c49c5b;">Available Slots</p>
                        <div class="d-flex flex-wrap gap-2" data-room-detail-available>
                            <span class="hyve-room-chip" style="color:rgba(255,255,255,0.72);">No room selected</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="hyve-room-card-dark h-100">
                        <p class="mb-3 text-uppercase fw-semibold" style="font-size:0.74rem; letter-spacing:0.18em; color:#c49c5b;">Unavailable Slots</p>
                        <div class="d-flex flex-wrap gap-2" data-room-detail-booked>
                            <span class="hyve-room-chip" style="color:rgba(255,255,255,0.72);">No room selected</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-4 py-4" style="border-top:1px solid rgba(255,255,255,0.08); background:#122720;">
                <div class="hyve-room-card-dark">
                    <p class="mb-3 text-uppercase fw-semibold" style="font-size:0.74rem; letter-spacing:0.18em; color:#c49c5b;">Booking Details</p>
                    <div class="d-grid gap-3" data-room-detail-timeline>
                        <div class="rounded-4 px-4 py-3" style="border:1px solid rgba(255,255,255,0.1); color:rgba(255,255,255,0.72);">Click a room to view its schedule details.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('[data-room-modal]');
    const modalClose = modal.querySelector('[data-room-modal-close]');
    const layoutDateInput = document.getElementById('layout-date');
    const roomDetailName = modal.querySelector('[data-room-detail-name]');
    const roomDetailType = modal.querySelector('[data-room-detail-type]');
    const roomDetailMeta = modal.querySelector('[data-room-detail-meta]');
    const roomDetailStatus = modal.querySelector('[data-room-detail-status]');
    const roomDetailNextSlot = modal.querySelector('[data-room-detail-next-slot]');
    const roomDetailAvailableCount = modal.querySelector('[data-room-detail-available-count]');
    const roomDetailBookedCount = modal.querySelector('[data-room-detail-booked-count]');
    const roomDetailAvailable = modal.querySelector('[data-room-detail-available]');
    const roomDetailBooked = modal.querySelector('[data-room-detail-booked]');
    const roomDetailTimeline = modal.querySelector('[data-room-detail-timeline]');

    const formatDateLabel = (value) => {
        if (!value) return '';
        const date = new Date(`${value}T00:00:00`);
        return new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(date);
    };

    const statusLabel = (status) => {
        if (status === 'occupied') return 'Occupied';
        if (status === 'booked') return 'Booked';
        return 'Available';
    };

    const compactStatusLabel = (status) => {
        if (status === 'occupied') return 'Occupied';
        if (status === 'booked') return 'Booked';
        return 'Open';
    };

    const roomTypeLabel = (room) => {
        if (room.name === 'Conference Room') return 'Conference Room';
        if ((room.name || '').startsWith('Room ')) return 'Private Room';
        return 'Shared Table Seat';
    };

    const renderChips = (container, slots, type) => {
        container.innerHTML = '';

        if (!slots.length) {
            const empty = document.createElement('span');
            empty.className = 'hyve-room-chip';
            empty.style.color = 'rgba(255,255,255,0.72)';
            empty.textContent = 'No windows available';
            container.appendChild(empty);
            return;
        }

        slots.forEach((slot) => {
            const chip = document.createElement('span');
            chip.className = `hyve-room-chip ${type === 'available' ? 'hyve-room-chip--available' : 'hyve-room-chip--booked'}`;
            chip.textContent = slot.label;
            container.appendChild(chip);
        });
    };

    const renderTimeline = (items) => {
        roomDetailTimeline.innerHTML = '';

        if (!items.length) {
            roomDetailTimeline.innerHTML = '<div class="rounded-4 px-4 py-3" style="border:1px solid rgba(255,255,255,0.1); color:rgba(255,255,255,0.72);">No booking detail timeline available for this room on the selected date.</div>';
            return;
        }

        items.forEach((item) => {
            const row = document.createElement('div');
            const isAvailable = item.type === 'available';
            row.className = 'rounded-4 px-4 py-3';
            row.style.border = '1px solid rgba(255,255,255,0.1)';
            row.style.background = 'rgba(255,255,255,0.07)';
            row.innerHTML = `
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                    <div class="fw-semibold" style="font-size:1rem;">${item.label}</div>
                    <span class="hyve-room-chip ${isAvailable ? 'hyve-room-chip--available' : 'hyve-room-chip--booked'}">${isAvailable ? 'Available' : 'Booked'}</span>
                </div>
            `;
            roomDetailTimeline.appendChild(row);
        });
    };

    const renderModal = (room) => {
        const availableSlots = room.available_slots || [];
        const bookedSlots = room.booked_slots || [];
        const bookingDetails = room.booking_details || [];

        roomDetailName.textContent = room.name || room.room_name || 'No room selected yet';
        roomDetailType.textContent = roomTypeLabel(room);
        roomDetailMeta.textContent = `${room.description || 'No room description available.'} | ${room.space_label || 'Workspace'}`;
        roomDetailStatus.textContent = `${statusLabel(room.status)} on ${formatDateLabel(layoutDateInput.value) || 'selected date'}`;
        roomDetailNextSlot.textContent = availableSlots.length ? availableSlots[0].label : 'No open windows left';
        roomDetailAvailableCount.textContent = `${availableSlots.length} ${availableSlots.length === 1 ? 'open window' : 'open windows'}`;
        roomDetailBookedCount.textContent = `${bookedSlots.length} ${bookedSlots.length === 1 ? 'reserved window' : 'reserved windows'}`;
        renderChips(roomDetailAvailable, availableSlots, 'available');
        renderChips(roomDetailBooked, bookedSlots, 'booked');
        renderTimeline(bookingDetails);
    };

    const openModal = () => {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hyve-room-modal-open');
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('hyve-room-modal-open');
    };

    document.querySelectorAll('.js-room-trigger').forEach((trigger) => {
        trigger.addEventListener('click', function () {
            const room = JSON.parse(this.dataset.room || '{}');
            renderModal(room);
            openModal();
        });
    });

    modalClose.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
});
</script>
@endsection

@extends('layouts.app')

@section('title', 'Voyage Tracking Map | Villa Shipping Lines')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
    #fleet-map { height: min(680px, 70vh); min-height: 440px; background: #dbeafe; }
    .fleet-vessel-marker { display:grid; place-items:center; width:28px; height:28px; color:var(--vessel-color,#087e8b); font-size:26px; line-height:1; filter:drop-shadow(-1px -1px 0 #fff) drop-shadow(1px 1px 0 #fff) drop-shadow(0 3px 4px rgba(15,23,42,.5)); }
    .fleet-route-point { display:grid; place-items:center; width:28px; height:28px; border:3px solid white; border-radius:999px; color:white; box-shadow:0 4px 12px rgba(15,23,42,.3); font-size:11px; font-weight:900; }
    .fleet-direction-arrow { display:block; color:#fff; font-size:24px; font-weight:900; line-height:24px; text-shadow:-1px -1px 0 #24477f,1px -1px 0 #24477f,-1px 1px 0 #24477f,1px 1px 0 #24477f,0 2px 5px #0f172a99; transform-origin:center; }
    [data-vessel-card] { transition:opacity .2s ease,filter .2s ease,border-color .2s ease,box-shadow .2s ease,background-color .2s ease; }
    [data-vessel-card].fleet-card-muted { opacity:.48; filter:saturate(.45); }
    [data-vessel-card].fleet-card-focused { border-color:#24477f; background:#eff6ff; box-shadow:0 0 0 2px #bfdbfe; }
    [data-focus-voyage][aria-pressed="true"] { background:#0f274c; box-shadow:0 0 0 3px #bfdbfe; }
    @media (max-width: 767.98px) { #fleet-map { height: 62vh; min-height: 390px; } }
</style>
@endpush

@section('content')
@php
    $mapVoyages = $voyages->map(function ($voyage) use ($voyageMetrics) {
        $positions = $voyage->positionLogs->map(fn ($position) => [
            'lat' => $position->latitude,
            'lng' => $position->longitude,
            'location' => $position->location_name,
            'recorded_at' => optional($position->recorded_at)->format('M d, Y h:i A'),
        ])->values();

        return [
            'id' => $voyage->voyage_id,
            'vessel' => $voyage->vessel?->vessel_name ?? 'Unknown Vessel',
            'voyage' => $voyage->voyage_no,
            'status' => strtoupper((string) ($voyage->status ?: 'OPEN')),
            'completed' => strtoupper((string) $voyage->status) === 'COMPLETED',
            'location' => $voyage->current_location,
            'lat' => $voyage->current_latitude,
            'lng' => $voyage->current_longitude,
            'origin' => [
                'name' => $voyage->port_location,
                'lat' => $voyage->origin_latitude,
                'lng' => $voyage->origin_longitude,
            ],
            'destination' => [
                'name' => $voyage->port_destination,
                'lat' => $voyage->destination_latitude,
                'lng' => $voyage->destination_longitude,
            ],
            'positions' => $positions,
            'metrics' => $voyageMetrics->get($voyage->voyage_id),
            'url' => route('voyages.show', $voyage->voyage_id),
        ];
    })->values();
@endphp
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <div class="mx-auto max-w-7xl">
        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.14em] text-villa-600">Vessel Management / Voyages</p><h1 class="m-0 text-2xl font-extrabold tracking-tight text-villa-900 sm:text-3xl">Voyage Tracking Map</h1><p class="mt-1 text-sm text-slate-500">View active routes and the permanent tracking history of completed voyages.</p></div>
            <a href="{{ $backUrl }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-extrabold text-slate-700 no-underline shadow-sm hover:bg-slate-50"><i class="bi bi-arrow-left"></i> {{ $backLabel }}</a>
        </header>

        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div class="relative min-w-0"><div id="fleet-map" role="application" aria-label="Current vessel locations"></div><div id="fleet-map-loading" class="absolute inset-0 z-[500] grid place-items-center bg-slate-100 p-6 text-center text-sm font-bold text-slate-600">Loading vessel map…</div></div>
                <aside class="max-h-[680px] overflow-y-auto border-t border-slate-200 p-4 xl:border-l xl:border-t-0">
                    <div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-extrabold text-slate-900">Voyage Track History</h2><span class="rounded-full bg-villa-50 px-2.5 py-1 text-xs font-extrabold text-villa-700">{{ $voyages->count() }}</span></div>
                    <div class="mb-4 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3 text-[.68rem] font-bold text-slate-600">
                        <span><i class="bi bi-circle-fill mr-1 text-emerald-600"></i>Origin</span>
                        <span><i class="bi bi-ship mr-1 text-villa-700"></i>Current</span>
                        <span><i class="bi bi-dash-lg mr-1 text-villa-700"></i>Recorded track</span>
                        <span><i class="bi bi-circle-fill mr-1 text-rose-600"></i>Destination</span>
                    </div>
                    <div class="space-y-2">
                        @forelse($voyages as $voyage)
                            @php
                                $isCompleted = strtoupper((string) $voyage->status) === 'COMPLETED';
                                $metrics = $voyageMetrics->get($voyage->voyage_id);
                            @endphp
                            <div class="rounded-2xl border border-slate-200 p-3 transition hover:border-villa-400 hover:bg-villa-50" data-vessel-card="{{ $voyage->voyage_id }}">
                                <div class="flex items-start justify-between gap-2"><p class="font-extrabold text-slate-900">{{ $voyage->vessel?->vessel_name ?? 'Unknown Vessel' }}</p><span @class(['rounded-full px-2 py-0.5 text-[.65rem] font-extrabold', 'bg-emerald-100 text-emerald-800' => $isCompleted, 'bg-blue-100 text-blue-800' => ! $isCompleted])>{{ $voyage->status ?: 'OPEN' }}</span></div>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ $voyage->voyage_no }} · {{ $voyage->current_location ?: 'Location pending' }}</p>
                                <p class="mt-1 text-[.68rem] font-bold text-villa-600">{{ $voyage->positionLogs->count() }} route {{ Str::plural('point', $voyage->positionLogs->count()) }} combined into one voyage track</p>
                                @if($voyage->current_latitude !== null && $voyage->current_longitude !== null)<p class="mt-1 text-[.68rem] text-slate-400">{{ number_format($voyage->current_latitude, 5) }}, {{ number_format($voyage->current_longitude, 5) }}</p>@else<p class="mt-1 text-[.68rem] font-bold text-amber-700">No map position recorded yet</p>@endif
                                @if($isCompleted)
                                    <div class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50/70 p-3">
                                        <div class="mb-2 flex items-center justify-between gap-2"><p class="text-[.68rem] font-extrabold uppercase tracking-wide text-emerald-800">Completed Voyage Summary</p><span @class(['rounded-full px-2 py-0.5 text-[.6rem] font-extrabold', 'bg-emerald-100 text-emerald-800' => $metrics['eta_tone'] === 'on-time', 'bg-rose-100 text-rose-800' => $metrics['eta_tone'] === 'late', 'bg-slate-100 text-slate-600' => $metrics['eta_tone'] === 'neutral'])>{{ $metrics['eta_performance'] }}</span></div>
                                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-[.67rem]">
                                            <div><dt class="font-bold text-slate-400">Elapsed time</dt><dd class="font-extrabold text-slate-800">{{ $metrics['elapsed'] ?? 'Not enough data' }}</dd></div>
                                            <div><dt class="font-bold text-slate-400">Fuel consumed</dt><dd class="font-extrabold text-slate-800">{{ $metrics['fuel_consumed'] !== null ? number_format($metrics['fuel_consumed'], 2).' L' : 'No fuel data' }}</dd></div>
                                            <div><dt class="font-bold text-slate-400">Tracked distance</dt><dd class="font-extrabold text-slate-800">{{ $metrics['distance_nm'] !== null ? number_format($metrics['distance_nm'], 2).' NM' : 'Not enough data' }}</dd></div>
                                            <div><dt class="font-bold text-slate-400">Estimated avg speed</dt><dd class="font-extrabold text-slate-800">{{ $metrics['average_speed_knots'] !== null ? number_format($metrics['average_speed_knots'], 2).' kn' : 'Not enough data' }}</dd></div>
                                            <div><dt class="font-bold text-slate-400">Required avg to ETA</dt><dd class="font-extrabold text-slate-800">{{ $metrics['required_speed_knots'] !== null ? number_format($metrics['required_speed_knots'], 2).' kn' : 'No ETA data' }}</dd></div>
                                            <div><dt class="font-bold text-slate-400">Completed at</dt><dd class="font-extrabold text-slate-800">{{ $metrics['completed_at'] ?? '-' }}</dd></div>
                                        </dl>
                                        <div class="mt-2 border-t border-emerald-100 pt-2 text-[.67rem]"><p><span class="font-bold text-slate-400">Cargo:</span> <span class="font-extrabold text-slate-800">{{ $metrics['cargo'] ?? 'Not recorded' }}</span></p><p class="mt-1"><span class="font-bold text-slate-400">Planned ETA:</span> <span class="font-extrabold text-slate-800">{{ $metrics['eta'] ?? 'Not recorded' }}</span></p><p class="mt-1"><span class="font-bold text-slate-400">Time basis:</span> <span class="font-extrabold text-slate-800">{{ $metrics['time_basis'] }}</span></p></div>
                                    </div>
                                @else
                                    <div class="mt-3 rounded-xl border border-blue-100 bg-blue-50/70 p-3">
                                        <p class="mb-2 text-[.68rem] font-extrabold uppercase tracking-wide text-blue-800">Active Voyage Details</p>
                                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-[.67rem]">
                                            <div><dt class="font-bold text-slate-400">Cargo</dt><dd class="font-extrabold text-slate-800">{{ $metrics['cargo'] ?? 'Not recorded' }}</dd></div>
                                            <div><dt class="font-bold text-slate-400">Fuel at departure</dt><dd class="font-extrabold text-slate-800">{{ $voyage->fuel_rob ?: 'Not recorded' }}</dd></div>
                                            <div><dt class="font-bold text-slate-400">Fuel consumed</dt><dd class="font-extrabold text-slate-800">{{ $metrics['fuel_consumed'] !== null ? number_format($metrics['fuel_consumed'], 2).' L' : 'No fuel update' }}</dd></div>
                                            <div><dt class="font-bold text-slate-400">Planned ETA</dt><dd class="font-extrabold text-slate-800">{{ $metrics['eta'] ?? 'Not recorded' }}</dd></div>
                                            <div class="col-span-2"><dt class="font-bold text-slate-400">Destination</dt><dd class="font-extrabold text-slate-800">{{ $voyage->port_destination ?: 'Not recorded' }}</dd></div>
                                        </dl>
                                    </div>
                                @endif
                                <div class="mt-3 flex gap-2">
                                    <button type="button" class="inline-flex min-h-9 flex-1 items-center justify-center gap-1.5 rounded-lg bg-villa-700 px-3 text-xs font-extrabold text-white hover:bg-villa-900" data-focus-voyage="{{ $voyage->voyage_id }}"><i class="bi bi-crosshair"></i> Show Track</button>
                                    <a href="{{ route('voyages.show', $voyage->voyage_id) }}" class="inline-flex min-h-9 flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-extrabold text-slate-700 no-underline hover:bg-slate-100 hover:text-slate-950"><i class="bi bi-box-arrow-up-right"></i> Details</a>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">No voyage tracking history is available.</div>
                        @endforelse
                    </div>
                </aside>
            </div>
        </article>
        <p class="mt-3 text-xs text-slate-500"><i class="bi bi-info-circle me-1"></i>The solid line is the saved tracking history. For an active voyage, the dashed arrow points from its latest position to the destination. Completed voyage tracks remain available for reference. This is operational monitoring, not continuous AIS tracking.</p>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const loading = document.getElementById('fleet-map-loading');
    if (typeof window.L === 'undefined') { loading.textContent = 'The map could not load. Check the internet connection and reload.'; return; }
    const map = L.map('fleet-map').setView([12.8797, 121.7740], 6);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19, attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }).addTo(map);
    loading.remove();
    const voyages = @json($mapVoyages);
    const showRecordedPositionMarkers = @js($selectedVoyageId !== null);
    const mapLayers = [];
    const voyageLayerGroups = new Map();
    let focusedVoyageId = null;
    const initialFocusedVoyageId = @js($selectedVoyageId !== null ? (string) $selectedVoyageId : null);
    const colors = ['#24477f', '#0f766e', '#7c3aed', '#be123c', '#b45309', '#0369a1'];
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' })[character]);
    const coordinates = point => {
        const lat = Number.parseFloat(point?.lat), lng = Number.parseFloat(point?.lng);
        return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
    };
    const directionAngle = (start, end) => {
        const startLat = start[0] * Math.PI / 180, endLat = end[0] * Math.PI / 180;
        const deltaLng = (end[1] - start[1]) * Math.PI / 180;
        const y = Math.sin(deltaLng) * Math.cos(endLat);
        const x = Math.cos(startLat) * Math.sin(endLat) - Math.sin(startLat) * Math.cos(endLat) * Math.cos(deltaLng);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    };
    const pointIcon = (label, color, vessel = false) => L.divIcon({
        className: '',
        html: vessel
            ? `<span class="fleet-vessel-marker" style="--vessel-color:${color}" title="Current vessel location"><svg viewBox="0 0 36 32" width="28" height="28" aria-hidden="true"><path fill="currentColor" stroke="#fff" stroke-width="1.2" stroke-linejoin="round" d="M3 17.5h27.5l-4.8 8H8.2L3 17.5Z"/><path fill="currentColor" stroke="#fff" stroke-width="1.1" stroke-linejoin="round" d="M8 13.5h17l5.5 4H3l5-4Zm2-7h11v7H10v-7Zm11 3h5v4h-5v-4Z"/><path fill="#fff" d="M12 8.5h2.6v2.2H12zm4.2 0h2.6v2.2h-2.6zm6.2 2.5h2v1.5h-2z"/><path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M7 28.5c2 1.1 4 1.1 6 0s4-1.1 6 0 4 1.1 6 0"/></svg></span>`
            : `<span class="fleet-route-point" style="background:${color}">${label}</span>`,
        iconSize: [28,28],
        iconAnchor: [14,14],
    });
    const setVoyageFocus = voyageId => {
        focusedVoyageId = focusedVoyageId === voyageId ? null : voyageId;
        voyageLayerGroups.forEach((entries, groupId) => {
            const emphasized = focusedVoyageId === null || groupId === focusedVoyageId;
            entries.forEach(entry => {
                if (typeof entry.layer.setStyle === 'function') {
                    entry.layer.setStyle({
                        opacity: emphasized ? entry.opacity : .16,
                        fillOpacity: emphasized ? entry.fillOpacity : Math.min(entry.fillOpacity, .12),
                        weight: focusedVoyageId !== null && emphasized && entry.emphasize ? entry.weight + 2 : entry.weight,
                    });
                } else if (typeof entry.layer.setOpacity === 'function') {
                    entry.layer.setOpacity(emphasized ? entry.opacity : .18);
                }
            });
        });
        document.querySelectorAll('[data-vessel-card]').forEach(card => {
            const selected = focusedVoyageId !== null && card.dataset.vesselCard === focusedVoyageId;
            card.classList.toggle('fleet-card-focused', selected);
            card.classList.toggle('fleet-card-muted', focusedVoyageId !== null && !selected);
        });
        document.querySelectorAll('[data-focus-voyage]').forEach(button => {
            const selected = focusedVoyageId !== null && button.dataset.focusVoyage === focusedVoyageId;
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    };
    voyages.forEach((voyage, voyageIndex) => {
        const voyageId = String(voyage.id);
        const voyageLayers = [];
        const registerLayer = (layer, options = {}) => {
            mapLayers.push(layer);
            voyageLayers.push({
                layer,
                opacity: options.opacity ?? 1,
                fillOpacity: options.fillOpacity ?? 1,
                weight: options.weight ?? 0,
                emphasize: options.emphasize ?? false,
            });
            return layer;
        };
        const color = colors[voyageIndex % colors.length];
        const lat = Number.parseFloat(voyage.lat), lng = Number.parseFloat(voyage.lng);
        const current = Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
        const origin = coordinates(voyage.origin);
        const destination = coordinates(voyage.destination);
        const reports = (voyage.positions || []).map(position => ({ ...position, coordinates: coordinates(position) })).filter(position => position.coordinates);
        const actualTrack = [];
        const appendUnique = point => { if (point && (!actualTrack.length || actualTrack.at(-1)[0] !== point[0] || actualTrack.at(-1)[1] !== point[1])) actualTrack.push(point); };
        appendUnique(origin);
        reports.forEach(report => appendUnique(report.coordinates));
        appendUnique(current);

        if (actualTrack.length > 1) {
            const track = L.polyline(actualTrack, { color, weight: 5, opacity: .88 }).addTo(map);
            track.bindPopup(`<strong>${escapeHtml(voyage.vessel)}</strong><br>Voyage ${escapeHtml(voyage.voyage)}<br><strong>Status:</strong> ${escapeHtml(voyage.status)}<br><strong>Cargo:</strong> ${escapeHtml(voyage.metrics?.cargo || 'Not recorded')}<br><strong>Fuel consumed:</strong> ${voyage.metrics?.fuel_consumed !== null ? `${escapeHtml(Number(voyage.metrics.fuel_consumed).toFixed(2))} L` : 'No fuel update'}`);
            registerLayer(track, { opacity: .88, weight: 5, emphasize: true });
        }
        if (!voyage.completed && current && destination) {
            const planned = L.polyline([current, destination], { color, weight: 3, opacity: .65, dashArray: '9 9' }).addTo(map);
            registerLayer(planned, { opacity: .65, weight: 3, emphasize: true });
            const angle = directionAngle(current, destination) - 90;
            [.3, .55, .8].forEach(fraction => {
                const point = [current[0] + (destination[0] - current[0]) * fraction, current[1] + (destination[1] - current[1]) * fraction];
                const arrowIcon = L.divIcon({ className: '', html: `<span class="fleet-direction-arrow" style="transform:rotate(${angle}deg)">➜</span>`, iconSize: [28, 28], iconAnchor: [14, 14] });
                const arrow = L.marker(point, { icon: arrowIcon, interactive: false, keyboard: false }).addTo(map);
                registerLayer(arrow);
            });
        }
        if (origin) {
            const marker = L.marker(origin, { icon: pointIcon('O', '#059669') }).addTo(map).bindPopup(`<strong>Port Origin</strong><br>${escapeHtml(voyage.origin.name || 'Origin')}<br>${escapeHtml(voyage.vessel)} · ${escapeHtml(voyage.voyage)}`);
            registerLayer(marker);
        }
        if (showRecordedPositionMarkers) {
            reports.slice(0, -1).forEach((report, index) => {
                const marker = L.circleMarker(report.coordinates, { radius: 5, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 }).addTo(map).bindPopup(`<strong>Position ${index + 1}</strong><br>${escapeHtml(report.location || 'Recorded position')}<br>${escapeHtml(report.recorded_at || '')}`);
                registerLayer(marker, { opacity: 1, fillOpacity: 1, weight: 2 });
            });
        }
        if (destination) {
            const marker = L.marker(destination, { icon: pointIcon('D', '#dc2626') }).addTo(map).bindPopup(`<strong>Port Destination</strong><br>${escapeHtml(voyage.destination.name || 'Destination')}<br>${escapeHtml(voyage.vessel)} · ${escapeHtml(voyage.voyage)}`);
            registerLayer(marker);
        }
        let currentMarker = null;
        if (current) {
            const currentColor = voyage.completed ? '#059669' : color;
            const voyageDetails = `<br><strong>Cargo:</strong> ${escapeHtml(voyage.metrics?.cargo || 'Not recorded')}<br><strong>Fuel consumed:</strong> ${voyage.metrics?.fuel_consumed !== null ? `${escapeHtml(Number(voyage.metrics.fuel_consumed).toFixed(2))} L` : 'No fuel update'}<br><strong>ETA:</strong> ${escapeHtml(voyage.metrics?.eta || 'Not recorded')}`;
            const completedMetrics = voyage.completed
                ? `<br><strong>Elapsed:</strong> ${escapeHtml(voyage.metrics?.elapsed || 'Not enough data')}<br><strong>Tracked distance:</strong> ${voyage.metrics?.distance_nm !== null ? `${escapeHtml(Number(voyage.metrics.distance_nm).toFixed(2))} NM` : 'Not enough data'}<br><strong>Avg speed:</strong> ${voyage.metrics?.average_speed_knots !== null ? `${escapeHtml(Number(voyage.metrics.average_speed_knots).toFixed(2))} kn` : 'Not enough data'}`
                : '';
            currentMarker = L.marker(current, { icon: pointIcon('', currentColor, true), zIndexOffset: 1000 }).addTo(map).bindPopup(`<strong>${escapeHtml(voyage.vessel)}</strong><br>${escapeHtml(voyage.voyage)}<br><strong>Status:</strong> ${escapeHtml(voyage.status)}<br><strong>${voyage.completed ? 'Final' : 'Current'}:</strong> ${escapeHtml(voyage.location || 'Position pending')}${voyageDetails}${completedMetrics}<br>${reports.length ? `Last report: ${escapeHtml(reports.at(-1).recorded_at || '')}<br>` : ''}<a href="${encodeURI(voyage.url)}">View voyage details</a>`);
            registerLayer(currentMarker);
        }
        voyageLayerGroups.set(voyageId, voyageLayers);
        const focusButton = document.querySelector(`[data-focus-voyage="${voyage.id}"]`);
        focusButton?.setAttribute('aria-pressed', 'false');
        focusButton?.addEventListener('click', () => {
            setVoyageFocus(voyageId);
            if (focusedVoyageId === null) {
                if (mapLayers.length) map.fitBounds(L.featureGroup(mapLayers).getBounds().pad(.15), { maxZoom: 12 });
                return;
            }
            const focusPoints = [...actualTrack, destination].filter(Boolean);
            if (focusPoints.length > 1) map.fitBounds(L.latLngBounds(focusPoints).pad(.18), { maxZoom: 11 });
            else if (focusPoints.length === 1) map.setView(focusPoints[0], Math.max(map.getZoom(), 9));
            currentMarker?.openPopup();
        });
    });
    if (mapLayers.length) map.fitBounds(L.featureGroup(mapLayers).getBounds().pad(.15), { maxZoom:12 });
    if (initialFocusedVoyageId !== null) {
        document.querySelector(`[data-focus-voyage="${initialFocusedVoyageId}"]`)?.click();
    }
});
</script>
@endpush

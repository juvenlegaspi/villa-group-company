<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crew Vessel Location Update | Villa Shipping Lines</title>
    <link rel="icon" href="{{ asset('logo.jpg') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *{box-sizing:border-box}body{margin:0;min-width:280px;background:#eef3f8;color:#172033;font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.crew-shell{min-height:100vh;padding:18px}.crew-wrap{max-width:1380px;margin:auto}.crew-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;padding:18px 22px;border-radius:24px;background:linear-gradient(135deg,#17345f,#087e8b);color:#fff;box-shadow:0 16px 35px #17345f2b}.crew-brand{display:flex;align-items:center;gap:13px}.crew-brand img{width:48px;height:48px;border-radius:14px;object-fit:cover;background:#fff}.crew-grid{display:grid;grid-template-columns:360px minmax(0,1fr);min-height:690px;overflow:hidden;border:1px solid #d9e2ef;border-radius:24px;background:#fff;box-shadow:0 18px 45px #17345f17}.crew-sidebar{padding:22px;overflow:auto}.crew-map-wrap{position:relative;min-height:690px;background:#dbeafe}.crew-map{position:absolute;inset:0}.field{width:100%;min-height:48px;border:1px solid #cbd5e1;border-radius:13px;background:#fff;padding:11px 13px;color:#172033;outline:none}.field:focus{border-color:#087e8b;box-shadow:0 0 0 4px #087e8b1c}.label{display:block;margin-bottom:6px;font-size:12px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.06em}.btn{display:inline-flex;min-height:48px;align-items:center;justify-content:center;gap:8px;border:0;border-radius:13px;padding:10px 16px;font-weight:800;cursor:pointer}.btn-primary{width:100%;background:#24477f;color:#fff}.btn-secondary{width:100%;border:1px solid #cbd5e1;background:#fff;color:#24477f}.btn:disabled{cursor:not-allowed;opacity:.55}.status-card{margin-bottom:18px;padding:15px;border:1px solid #dbe5ef;border-radius:17px;background:#f8fafc}.notice{margin-bottom:16px;padding:13px 15px;border-radius:14px;font-size:14px;font-weight:700}.notice-success{border:1px solid #a7f3d0;background:#ecfdf5;color:#047857}.notice-error{border:1px solid #fecaca;background:#fff1f2;color:#be123c}.map-ship{display:grid;width:32px;height:32px;place-items:center;color:#d97706;filter:drop-shadow(-1px -1px 0 #fff) drop-shadow(1px 1px 0 #fff) drop-shadow(0 3px 4px #0f172a80)}.map-arrow{display:block;color:#fff;font-size:22px;font-weight:900;line-height:22px;text-shadow:-1px -1px 0 #0891b2,1px -1px 0 #0891b2,-1px 1px 0 #0891b2,1px 1px 0 #0891b2,0 2px 5px #0f172a99;transform-origin:center}@media(max-width:900px){.crew-shell{padding:8px}.crew-header{border-radius:18px;padding:15px}.crew-header .helper{display:none}.crew-grid{grid-template-columns:1fr;min-height:0;border-radius:18px}.crew-sidebar{padding:17px}.crew-map-wrap{min-height:52vh;order:-1}.crew-map{min-height:52vh}}@media(max-width:520px){.crew-map-wrap,.crew-map{min-height:44vh}.crew-brand img{width:42px;height:42px}.crew-header h1{font-size:17px}}
    </style>
</head>
<body>
<main class="crew-shell">
    <div class="crew-wrap">
        <header class="crew-header">
            <div class="crew-brand">
                <img src="{{ asset('logo.jpg') }}" alt="Villa Group">
                <div><p style="margin:0 0 3px;font-size:11px;font-weight:900;letter-spacing:.13em;text-transform:uppercase;color:#bde9ec">Villa Shipping Lines</p><h1 style="margin:0;font-size:22px;font-weight:900">Vessel Location Update</h1></div>
            </div>
            <p class="helper" style="margin:0;max-width:350px;text-align:right;font-size:13px;color:#dcecff">Secure crew link &middot; Update the vessel position at least every 3 hours.</p>
        </header>

        @if(session('success'))<div class="notice notice-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="notice notice-error"><strong>Location was not saved.</strong><ul style="margin:6px 0 0;padding-left:20px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="status-card" style="margin-bottom:16px;background:#fff;box-shadow:0 8px 24px #17345f12">
            <label class="label" for="portal-vessel-select">Select your vessel</label>
            <select class="field" id="portal-vessel-select" @disabled($vessels->isEmpty())>
                <option value="">Choose an ongoing vessel</option>
                @foreach($vessels as $availableVessel)
                    <option value="{{ $availableVessel->id }}" @selected($vessel?->id === $availableVessel->id)>{{ $availableVessel->vessel_name }}</option>
                @endforeach
            </select>
            <p style="margin:7px 2px 0;font-size:12px;color:#64748b">Only vessels with an ongoing voyage are available. Confirm the vessel name carefully before saving.</p>
        </section>

        <section class="crew-grid">
            <aside class="crew-sidebar">
                <div class="status-card">
                    <p style="margin:0 0 5px;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#64748b">Vessel</p>
                    <h2 style="margin:0;font-size:20px;font-weight:900;color:#17345f">{{ $vessel?->vessel_name ?? 'Select a vessel above' }}</h2>
                    @if($voyage)
                        <p style="margin:5px 0 0;font-size:13px;color:#64748b">{{ $voyage->voyage_code }}@if($voyage->voyage_no) / {{ $voyage->voyage_no }}@endif &middot; {{ $voyage->port_location ?: 'Origin pending' }} to {{ $voyage->port_destination ?: 'Destination pending' }}</p>
                    @else
                        <p style="margin:7px 0 0;font-size:13px;font-weight:700;color:#b45309">{{ $vessels->isEmpty() ? 'There are no ongoing voyages available.' : 'Choose the vessel where you are currently assigned.' }}</p>
                    @endif
                </div>

                @if($voyage)
                    @php
                        $hoursSinceUpdate = $lastUpdatedAt ? $lastUpdatedAt->diffInMinutes(now()) / 60 : null;
                        $updateOverdue = $hoursSinceUpdate === null || $hoursSinceUpdate >= 3;
                    @endphp
                    <div class="status-card" style="border-color:{{ $updateOverdue ? '#fcd34d' : '#a7f3d0' }};background:{{ $updateOverdue ? '#fffbeb' : '#ecfdf5' }}">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px"><span style="font-size:12px;font-weight:900;color:{{ $updateOverdue ? '#92400e' : '#047857' }}">{{ $updateOverdue ? 'LOCATION UPDATE DUE' : 'LOCATION IS CURRENT' }}</span><span style="font-size:11px;font-weight:800;color:#64748b">3-hour interval</span></div>
                        <p style="margin:7px 0 0;font-size:13px;color:#475569">Last update: <strong>{{ $lastUpdatedAt?->format('M d, Y g:i A') ?? 'No update yet' }}</strong></p>
                    </div>

                    <form method="POST" action="{{ route('crew-location.update', $token) }}" id="crew-location-form">
                        @csrf
                        <input type="hidden" name="vessel_id" value="{{ $vessel->id }}">
                        <div style="margin-bottom:14px"><label class="label" for="reporter_name">Your name / crew name</label><input class="field" id="reporter_name" name="reporter_name" maxlength="120" required value="{{ old('reporter_name') }}" placeholder="Example: Juan Dela Cruz"></div>
                        <div style="margin-bottom:14px"><label class="label" for="location_name">Pinned place name</label><input class="field" id="location_name" name="location_name" maxlength="255" required value="{{ old('location_name', $voyage->current_location) }}" placeholder="Pin the position or type its name"><p id="map-message" style="margin:6px 0 0;font-size:12px;color:#64748b">Click the map, use GPS, or enter coordinates manually.</p></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                            <div><label class="label" for="latitude">Latitude</label><input class="field" type="number" step="0.0000001" min="-90" max="90" id="latitude" name="latitude" required value="{{ old('latitude', $voyage->current_latitude) }}"></div>
                            <div><label class="label" for="longitude">Longitude</label><input class="field" type="number" step="0.0000001" min="-180" max="180" id="longitude" name="longitude" required value="{{ old('longitude', $voyage->current_longitude) }}"></div>
                        </div>
                        <input type="hidden" id="accuracy_meters" name="accuracy_meters" value="{{ old('accuracy_meters') }}">
                        <button type="button" class="btn btn-secondary" id="gps-button" style="margin-bottom:10px">Use My GPS Location</button>
                        <div style="margin:0 0 12px;padding:14px;border:1px solid #bae6fd;border-radius:16px;background:#f0f9ff">
                            <p style="margin:0;font-size:12px;font-weight:900;color:#075985;text-transform:uppercase;letter-spacing:.05em">30-minute automatic GPS</p>
                            <p style="margin:5px 0 11px;font-size:12px;line-height:1.5;color:#475569">Keep this page open and the phone screen awake. Offline GPS points are stored on this phone and synced when internet returns.</p>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                                <button type="button" class="btn btn-primary" id="start-auto-tracking" style="min-height:44px">Start Tracking</button>
                                <button type="button" class="btn btn-secondary" id="stop-auto-tracking" style="min-height:44px" disabled>Stop</button>
                            </div>
                            <p id="auto-tracking-status" style="margin:9px 0 0;font-size:12px;font-weight:800;color:#64748b">Automatic tracking is off.</p>
                        </div>
                        <button type="submit" class="btn btn-primary" id="save-button">Save Current Location</button>
                        <p style="margin:9px 2px 0;font-size:11px;line-height:1.5;color:#64748b">Your name, update time, coordinates and device network information are recorded for the voyage audit trail.</p>
                    </form>
                @else
                    <div class="notice notice-error" style="margin:0">{{ $vessels->isEmpty() ? 'Location updates will become available after a new voyage is created.' : 'Select a vessel first to open its ongoing track and location form.' }}</div>
                @endif
            </aside>

            <div class="crew-map-wrap">
                @if($voyage)<div id="crew-map" class="crew-map" aria-label="Ongoing voyage tracking map"></div>@else<div style="position:absolute;inset:0;display:grid;place-items:center;padding:30px;text-align:center;color:#64748b"><div><strong style="display:block;font-size:18px;color:#334155">{{ $vessels->isEmpty() ? 'No active route to display' : 'Select your vessel' }}</strong><span style="font-size:13px">{{ $vessels->isEmpty() ? 'This page will show tracks when an ongoing voyage is available.' : 'The ongoing voyage map and its saved track will load here.' }}</span></div></div>@endif
            </div>
        </section>
    </div>
</main>

<script>
document.getElementById('portal-vessel-select')?.addEventListener('change', event => {
    const url = new URL(window.location.href);
    if (event.target.value) url.searchParams.set('vessel', event.target.value); else url.searchParams.delete('vessel');
    window.location.assign(url.toString());
});
</script>

@if($voyage)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const voyage = @json($mapPayload);
    const reverseUrl = @js(route('crew-location.reverse', $token));
    const automaticUpdateUrl = @js(route('crew-location.automatic', $token));
    const vesselId = @json($vessel->id);
    const trackerStorageKey = @js('crew-location-tracker:'.$token.':'.$vessel->id);
    const queueStorageKey = @js('crew-location-queue:'.$token.':'.$vessel->id);
    const latitude = document.getElementById('latitude'), longitude = document.getElementById('longitude'), locationName = document.getElementById('location_name'), accuracy = document.getElementById('accuracy_meters'), message = document.getElementById('map-message');
    const reporterName = document.getElementById('reporter_name'), startAutoButton = document.getElementById('start-auto-tracking'), stopAutoButton = document.getElementById('stop-auto-tracking'), autoStatus = document.getElementById('auto-tracking-status');
    const map = L.map('crew-map').setView([12.8797, 121.7740], 6);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
    const valid = (lat, lng) => Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
    const coords = point => { const lat = Number.parseFloat(point?.lat ?? point?.latitude), lng = Number.parseFloat(point?.lng ?? point?.longitude); return valid(lat, lng) ? [lat, lng] : null; };
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c]);
    const shipIcon = () => L.divIcon({className:'',html:'<span class="map-ship"><svg viewBox="0 0 36 32" width="32" height="32" aria-hidden="true"><path fill="currentColor" stroke="#fff" stroke-width="1.2" stroke-linejoin="round" d="M3 17.5h27.5l-4.8 8H8.2L3 17.5Z"/><path fill="currentColor" stroke="#fff" stroke-width="1.1" stroke-linejoin="round" d="M8 13.5h17l5.5 4H3l5-4Zm2-7h11v7H10v-7Zm11 3h5v4h-5v-4Z"/><path fill="#fff" d="M12 8.5h2.6v2.2H12zm4.2 0h2.6v2.2h-2.6zm6.2 2.5h2v1.5h-2z"/><path fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M7 28.5c2 1.1 4 1.1 6 0s4-1.1 6 0 4 1.1 6 0"/></svg></span>',iconSize:[32,32],iconAnchor:[16,16],popupAnchor:[0,-15]});
    let currentMarker = null, manualTimer = null, automaticTimer = null, wakeLock = null, captureInProgress = false;
    const points = [];
    const addUnique = point => { if (point && (!points.length || points.at(-1)[0] !== point[0] || points.at(-1)[1] !== point[1])) points.push(point); };
    const origin = coords(voyage.origin), destination = coords(voyage.destination);
    addUnique(origin); voyage.track.forEach(item => addUnique(coords(item))); addUnique(coords(voyage.current));
    if (points.length > 1) L.polyline(points, {color:'#24477f',weight:4,opacity:.92}).addTo(map);
    if (origin) L.circleMarker(origin,{radius:7,color:'#fff',weight:3,fillColor:'#059669',fillOpacity:1}).addTo(map).bindPopup(`<strong>Port Origin</strong><br>${escapeHtml(voyage.origin.name || 'Origin')}`);
    if (destination) L.circleMarker(destination,{radius:7,color:'#fff',weight:3,fillColor:'#dc2626',fillOpacity:1}).addTo(map).bindPopup(`<strong>Port Destination</strong><br>${escapeHtml(voyage.destination.name || 'Destination')}`);
    voyage.track.slice(0,-1).forEach((item,index)=>{const point=coords(item);if(point)L.circleMarker(point,{radius:4,color:'#fff',weight:2,fillColor:'#24477f',fillOpacity:1}).addTo(map).bindPopup(`<strong>Position ${index+1}</strong><br>${escapeHtml(item.location_name || '')}<br>${escapeHtml(item.recorded_at || '')}`)});
    const setPoint = (lat, lng, center = true) => {
        lat = Number.parseFloat(lat); lng = Number.parseFloat(lng); if (!valid(lat,lng)) return false;
        latitude.value = lat.toFixed(7); longitude.value = lng.toFixed(7);
        if (!currentMarker) currentMarker = L.marker([lat,lng],{icon:shipIcon(),draggable:true,zIndexOffset:1000}).addTo(map).bindPopup('<strong>Current vessel position</strong>'); else currentMarker.setLatLng([lat,lng]);
        currentMarker.off('dragend').on('dragend', event => {const p=event.target.getLatLng();setPoint(p.lat,p.lng,false);reverse(p.lat,p.lng)});
        if (center) map.setView([lat,lng],Math.max(map.getZoom(),10));
        return true;
    };
    const reverse = async (lat,lng) => {
        message.textContent='Identifying the pinned place...';
        try { const response=await fetch(`${reverseUrl}?latitude=${encodeURIComponent(lat)}&longitude=${encodeURIComponent(lng)}`,{headers:{Accept:'application/json'}}); const data=await response.json(); if(!response.ok)throw new Error(data.message||'Location lookup unavailable.'); locationName.value=data.name; message.textContent='Place name and coordinates are ready to save.'; }
        catch(error){message.textContent='Place name lookup is unavailable. Please type the location name manually.';}
    };
    const readStored = (key, fallback) => { try { const value=JSON.parse(localStorage.getItem(key)); return value ?? fallback; } catch (_) { return fallback; } };
    const writeStored = (key, value) => { try { localStorage.setItem(key,JSON.stringify(value)); return true; } catch (_) { return false; } };
    const trackerState = () => readStored(trackerStorageKey,{enabled:false,reporter:''});
    const locationQueue = () => { const queue=readStored(queueStorageKey,[]); return Array.isArray(queue)?queue:[]; };
    const setAutoStatus = (text, tone='neutral') => { autoStatus.textContent=text; autoStatus.style.color=tone==='good'?'#047857':tone==='warn'?'#b45309':tone==='bad'?'#be123c':'#64748b'; };
    const updateAutoButtons = enabled => { startAutoButton.disabled=enabled; stopAutoButton.disabled=!enabled; };
    const requestWakeLock = async () => { if(!('wakeLock' in navigator)||document.visibilityState!=='visible')return;try{wakeLock=await navigator.wakeLock.request('screen');wakeLock.addEventListener('release',()=>{wakeLock=null})}catch(_){wakeLock=null} };
    const releaseWakeLock = async () => { if(wakeLock){try{await wakeLock.release()}catch(_){}wakeLock=null} };
    const gpsPosition = () => new Promise((resolve,reject) => {
        if(!navigator.geolocation){reject(new Error('GPS is not supported by this device.'));return}
        navigator.geolocation.getCurrentPosition(resolve,reject,{enableHighAccuracy:true,timeout:20000,maximumAge:60000});
    });
    const lookupPlaceName = async (lat,lng) => {
        if(!navigator.onLine)return `GPS position (${lat.toFixed(5)}, ${lng.toFixed(5)})`;
        try{const response=await fetch(`${reverseUrl}?latitude=${encodeURIComponent(lat)}&longitude=${encodeURIComponent(lng)}`,{headers:{Accept:'application/json'}});const data=await response.json();return response.ok&&data.name?data.name:`GPS position (${lat.toFixed(5)}, ${lng.toFixed(5)})`}catch(_){return `GPS position (${lat.toFixed(5)}, ${lng.toFixed(5)})`}
    };
    const stopAutomaticTracking = async (status='Automatic tracking is off.',tone='neutral') => {
        const state=trackerState();writeStored(trackerStorageKey,{...state,enabled:false});
        if(automaticTimer){clearInterval(automaticTimer);automaticTimer=null}updateAutoButtons(false);await releaseWakeLock();setAutoStatus(status,tone);
    };
    const syncQueuedLocations = async () => {
        if(!navigator.onLine)return;
        let queue=locationQueue();
        while(queue.length){
            try{
                const response=await fetch(automaticUpdateUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify(queue[0])});
                const data=await response.json().catch(()=>({}));
                if(response.ok){queue.shift();writeStored(queueStorageKey,queue);setAutoStatus(queue.length?`${queue.length} offline GPS point(s) waiting to sync.`:`GPS saved. Next update in 30 minutes.`,'good');continue}
                if(response.status===422&&String(data.message||'').toLowerCase().includes('no ongoing voyage')){writeStored(queueStorageKey,[]);await stopAutomaticTracking('Tracking stopped because the voyage is already completed.','warn');return}
                if(response.status===404){await stopAutomaticTracking('This crew link is no longer valid. Ask the manager for the new link.','bad');return}
                if(response.status===422){queue.shift();writeStored(queueStorageKey,queue);continue}
                break;
            }catch(_){break}
        }
        if(queue.length)setAutoStatus(`${queue.length} GPS point(s) saved offline. Waiting for internet.`,'warn');
    };
    const captureAutomaticLocation = async () => {
        if(captureInProgress||!trackerState().enabled)return;captureInProgress=true;
        try{
            setAutoStatus('Getting the current GPS position...');
            const position=await gpsPosition(),lat=position.coords.latitude,lng=position.coords.longitude,name=await lookupPlaceName(lat,lng),state=trackerState();
            const item={vessel_id:vesselId,reporter_name:state.reporter||reporterName.value.trim(),location_name:name,latitude:lat,longitude:lng,accuracy_meters:position.coords.accuracy||null,captured_at:new Date(position.timestamp||Date.now()).toISOString()};
            const queue=locationQueue();queue.push(item);writeStored(queueStorageKey,queue);setPoint(lat,lng,true);locationName.value=name;accuracy.value=position.coords.accuracy||'';
            if(navigator.onLine)await syncQueuedLocations();else setAutoStatus(`${queue.length} GPS point(s) saved offline. Waiting for internet.`,'warn');
        }catch(error){setAutoStatus(error.message||'Unable to read the device GPS. Check location permission.','bad')}
        finally{captureInProgress=false}
    };
    const startAutomaticTracking = async () => {
        const reporter=reporterName.value.trim();if(!reporter){reporterName.focus();setAutoStatus('Enter the crew/updater name before starting.','bad');return}
        writeStored(trackerStorageKey,{enabled:true,reporter});updateAutoButtons(true);await requestWakeLock();setAutoStatus('Automatic tracking started. Getting GPS...','good');
        await captureAutomaticLocation();if(trackerState().enabled&&!automaticTimer)automaticTimer=setInterval(captureAutomaticLocation,30*60*1000);
    };
    const initial = coords(voyage.current) || points.at(-1) || origin;
    if(initial)setPoint(initial[0],initial[1],false);
    const bounds=[...points,destination].filter(Boolean); if(bounds.length>1)map.fitBounds(bounds,{padding:[35,35],maxZoom:11}); else if(bounds.length===1)map.setView(bounds[0],10);
    if(initial&&destination){L.polyline([initial,destination],{color:'#0891b2',weight:3,opacity:.75,dashArray:'9 8'}).addTo(map)}
    map.on('click', event => {setPoint(event.latlng.lat,event.latlng.lng,false);reverse(event.latlng.lat,event.latlng.lng)});
    const applyManual = () => {clearTimeout(manualTimer);manualTimer=setTimeout(()=>{const lat=Number.parseFloat(latitude.value),lng=Number.parseFloat(longitude.value);if(setPoint(lat,lng,true)){message.textContent='Manual coordinates pinned. Confirm or type the place name.';}},450)};
    latitude.addEventListener('input',applyManual);longitude.addEventListener('input',applyManual);
    document.getElementById('gps-button').addEventListener('click',()=>{if(!navigator.geolocation){message.textContent='GPS is not supported by this device.';return}message.textContent='Getting device GPS position...';navigator.geolocation.getCurrentPosition(position=>{accuracy.value=position.coords.accuracy||'';setPoint(position.coords.latitude,position.coords.longitude,true);reverse(position.coords.latitude,position.coords.longitude)},error=>{message.textContent=error.message||'Unable to access the device GPS.'},{enableHighAccuracy:true,timeout:15000,maximumAge:30000})});
    startAutoButton.addEventListener('click',startAutomaticTracking);
    stopAutoButton.addEventListener('click',()=>stopAutomaticTracking());
    window.addEventListener('online',syncQueuedLocations);
    document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'&&trackerState().enabled)requestWakeLock()});
    const savedTracker=trackerState();if(savedTracker.reporter&&!reporterName.value)reporterName.value=savedTracker.reporter;
    syncQueuedLocations();
    if(savedTracker.enabled){updateAutoButtons(true);requestWakeLock();setAutoStatus('Automatic tracking resumed. Keep this page and screen open.','good');captureAutomaticLocation();automaticTimer=setInterval(captureAutomaticLocation,30*60*1000)}
});
</script>
@endif
</body>
</html>

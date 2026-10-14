(function () {
    'use strict';

    window.initializeVsliLiveMap = function ({ tracks: initialTracks = [], liveFleetUrl, weatherUrl }) {
        const mapElement = document.getElementById('executive-fleet-map');
        if (!mapElement) return;

        const mapSection = document.getElementById('live-fleet-map-section');
        const mapLayout = document.getElementById('dashboard-map-layout');
        const detailsToggle = document.getElementById('dashboard-map-details-toggle');
        const fullscreenButton = document.getElementById('dashboard-map-fullscreen');
        const detailsTitle = document.getElementById('dashboard-map-details-title');
        const detailsList = document.getElementById('dashboard-map-details-list');
        const countBadge = document.getElementById('dashboard-map-count');
        const emptyMessage = document.getElementById('dashboard-map-empty');
        const liveTime = document.getElementById('dashboard-live-time');
        const liveDate = document.getElementById('dashboard-live-date');
        const liveSync = document.getElementById('dashboard-live-sync');
        const filterButtons = document.querySelectorAll('[data-dashboard-map-filter]');
        const colors = ['#1d4ed8', '#0f766e', '#7c3aed', '#be123c', '#d97706', '#0284c7', '#c026d3', '#15803d', '#e11d48', '#4f46e5', '#0891b2', '#9333ea'];
        let tracks = Array.isArray(initialTracks) ? initialTracks : [];
        let currentMapMode = 'active';
        let liveRefreshRunning = false;
        const weatherByVoyage = new Map();
        const weatherRequests = new Map();

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        })[character]);
        const detailValue = (value, fallback = 'Not recorded') => {
            const normalized = String(value ?? '').trim();
            return normalized ? escapeHtml(normalized) : fallback;
        };
        const fuelValue = (value, fallback = 'Not recorded') => {
            const amount = Number(value);
            return value !== null && value !== '' && Number.isFinite(amount)
                ? `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} L`
                : fallback;
        };
        const coordinates = (point) => {
            const latitude = Number(point?.lat);
            const longitude = Number(point?.lng);
            return Number.isFinite(latitude) && Number.isFinite(longitude) ? [longitude, latitude] : null;
        };
        const addUnique = (collection, point) => {
            if (point && (!collection.length || collection.at(-1)[0] !== point[0] || collection.at(-1)[1] !== point[1])) {
                collection.push(point);
            }
        };
        const matchRoute = (plannedRoute, reportedPositions, completed = false, completionPoint = null) => window.VoyageRouteMatcher?.match(
            plannedRoute,
            reportedPositions,
            { completed, completionPoint, deviationThresholdNm: 5 },
        ) || { track: reportedPositions, latestMatch: null, maxDeviationNm: null, hasDeviation: false, followsPlannedRoute: false };
        const deviationWarning = (voyage) => voyage.route_deviation
            ? `<span class="alert alert-warning d-block py-2 px-2 mt-2 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i>Reported position is up to ${Number(voyage.route_deviation_nm).toFixed(2)} NM from the approved route.</span>`
            : '';
        const colorForVoyage = (voyage) => {
            const identity = String(voyage?.id ?? voyage?.vessel ?? 'vessel');
            let hash = 0;
            for (let index = 0; index < identity.length; index++) hash = ((hash << 5) - hash) + identity.charCodeAt(index);
            return colors[Math.abs(hash) % colors.length];
        };
        const weatherIcon = (symbolCode) => {
            const code = String(symbolCode || '').toLowerCase();
            if (code.includes('thunder')) return '⛈️';
            if (code.includes('snow') || code.includes('sleet')) return '🌨️';
            if (code.includes('rain')) return '🌧️';
            if (code.includes('fog')) return '🌫️';
            if (code.includes('partlycloudy')) return '⛅';
            if (code.includes('cloudy')) return '☁️';
            if (code.includes('fair')) return '🌤️';
            if (code.includes('clearsky')) return '☀️';
            return '🌡️';
        };
        const weatherHtml = (voyage) => {
            if (voyage.completed) return '<span class="text-muted">Weather is shown for active voyages.</span>';
            const record = weatherByVoyage.get(String(voyage.id));
            if (!record) return '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Loading current weather...</span>';
            if (!record.data?.available) return '<span class="text-muted">Weather temporarily unavailable.</span>';
            const weather = record.data;
            const temperature = weather.temperature_c == null ? '--' : `${weather.temperature_c}°C`;
            const wind = weather.wind_speed_knots == null ? 'Wind unavailable' : `Wind ${weather.wind_speed_knots} kn${weather.wind_direction ? ` ${escapeHtml(weather.wind_direction)}` : ''}`;
            const rain = weather.precipitation_mm == null ? '' : ` | Rain ${weather.precipitation_mm} mm`;
            const humidity = weather.humidity_percent == null ? '' : ` | Humidity ${weather.humidity_percent}%`;
            return `<span class="vsli-weather-reading"><span class="weather-icon" aria-hidden="true">${weatherIcon(weather.symbol_code)}</span><span><strong>${temperature} | ${escapeHtml(weather.condition || 'Weather available')}</strong><br><small>${wind}${rain}${humidity}</small></span></span><span class="vsli-weather-attribution">Latest model forecast | MET Norway</span>`;
        };
        const startLeafletMap = () => {
            if (!window.L) {
                mapElement.classList.add('d-flex', 'align-items-center', 'justify-content-center', 'text-center', 'text-muted', 'p-5');
                mapElement.innerHTML = '<div><i class="bi bi-map d-block fs-1 mb-3"></i><strong>The tracking map could not start.</strong><br><small>Check the internet connection, then reload this page.</small></div>';
                if (liveSync) liveSync.textContent = 'Map connection unavailable';
                return;
            }

            mapElement.classList.remove('d-flex', 'align-items-center', 'justify-content-center', 'text-center', 'text-muted', 'p-5');
            mapElement.innerHTML = '';
            const map = window.L.map(mapElement, { scrollWheelZoom: true, zoomControl: true }).setView([12.4, 122.2], 5);
            window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            }).addTo(map);

            const layers = {
                active: window.L.layerGroup().addTo(map),
                previous: window.L.layerGroup(),
            };
            const features = new Map();
            const mapBounds = { active: [], previous: [] };
            let selectedVoyageId = null;

            const popupHtml = (voyage) => `<div style="min-width:245px">
                <strong>${escapeHtml(voyage.vessel)}</strong><br>
                <span>${escapeHtml(voyage.voyage)} | ${escapeHtml(voyage.status)}</span><hr style="margin:.5rem 0">
                <strong>${voyage.completed ? 'Final' : 'Current'}:</strong> ${escapeHtml(voyage.current?.name || voyage.positions?.at(-1)?.name || 'Position recorded')}<br>
                <strong>Destination:</strong> ${escapeHtml(voyage.destination?.name || 'Not set')}<br>
                <small>Last update: ${escapeHtml(voyage.last_update || 'Unknown')}</small>
                ${deviationWarning(voyage)}
                <div style="margin-top:9px">${weatherHtml(voyage)}</div>
            </div>`;

            const updateWeatherViews = (voyage) => {
                detailsList?.querySelectorAll(`[data-weather-voyage="${CSS.escape(String(voyage.id))}"]`).forEach((element) => {
                    element.innerHTML = weatherHtml(voyage);
                });
                features.get(String(voyage.id))?.marker?.setPopupContent(popupHtml(voyage));
            };

            const loadVoyageWeather = async (voyage, force = false) => {
                if (!weatherUrl || voyage.completed) return;
                const saved = (voyage.positions || []).map(coordinates).filter(Boolean);
                const point = coordinates(voyage.current) || saved.at(-1) || coordinates(voyage.origin);
                if (!point) return;
                const coordinateKey = `${point[1].toFixed(3)},${point[0].toFixed(3)}`;
                const voyageKey = String(voyage.id);
                const existing = weatherByVoyage.get(voyageKey);
                if (!force && existing?.coordinateKey === coordinateKey && existing.expiresAt > Date.now()) {
                    updateWeatherViews(voyage);
                    return;
                }
                if (weatherRequests.has(coordinateKey)) {
                    const data = await weatherRequests.get(coordinateKey);
                    weatherByVoyage.set(voyageKey, { coordinateKey, data, expiresAt: Date.now() + 9 * 60 * 1000 });
                    updateWeatherViews(voyage);
                    return;
                }

                const request = (async () => {
                    try {
                        const url = new URL(weatherUrl, window.location.origin);
                        url.searchParams.set('lat', point[1]);
                        url.searchParams.set('lng', point[0]);
                        const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) throw new Error('Weather request failed');
                        return await response.json();
                    } catch (error) {
                        return { available: false };
                    }
                })();
                weatherRequests.set(coordinateKey, request);
                const data = await request;
                weatherRequests.delete(coordinateKey);
                weatherByVoyage.set(voyageKey, { coordinateKey, data, expiresAt: Date.now() + (data.available ? 9 : 2) * 60 * 1000 });
                updateWeatherViews(voyage);
            };

            const highlightVoyage = (voyageId) => {
                selectedVoyageId = voyageId == null ? null : String(voyageId);
                features.forEach((feature, featureId) => {
                    const selected = selectedVoyageId === null || featureId === selectedVoyageId;
                    feature.visualLayers.forEach(({ layer, opacity, fillOpacity, weight }) => {
                        layer.setStyle({
                            opacity: selected ? opacity : .12,
                            fillOpacity: selected ? fillOpacity : .1,
                            weight: selected && selectedVoyageId !== null ? weight + 2 : weight,
                        });
                        if (selected && selectedVoyageId !== null) layer.bringToFront?.();
                    });
                    feature.marker?.setOpacity(selected ? 1 : .2);
                    feature.marker?.setZIndexOffset(selected && selectedVoyageId !== null ? 1000 : 0);
                });
                detailsList?.querySelectorAll('[data-dashboard-voyage]').forEach((card) => {
                    const selected = card.dataset.dashboardVoyage === selectedVoyageId;
                    card.classList.toggle('is-selected', selected);
                    card.classList.toggle('is-dimmed', selectedVoyageId !== null && !selected);
                });
            };

            const renderVoyageDetails = (mode) => {
                if (!detailsList || !detailsTitle) return;
                const visibleVoyages = tracks.filter((voyage) => (voyage.completed ? 'previous' : 'active') === mode);
                detailsTitle.textContent = mode === 'active' ? 'Active Voyages' : 'Previous Voyages';
                if (!visibleVoyages.length) {
                    detailsList.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-map d-block fs-3 mb-2"></i>No voyage details available.</div>';
                    return;
                }
                detailsList.innerHTML = visibleVoyages.map((voyage) => {
                    const color = colorForVoyage(voyage);
                    const locationLabel = voyage.completed ? 'Final location' : 'Current location';
                    const dateLabel = voyage.completed ? 'Completed' : 'ETA';
                    const dateValue = voyage.completed ? voyage.completed_at : voyage.eta;
                    return `<button type="button" class="vsli-map-detail-card" style="border-left:5px solid ${color}" data-dashboard-voyage="${escapeHtml(voyage.id)}">
                        <span class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <span><strong class="d-flex align-items-center gap-2 text-dark"><i class="vsli-voyage-color" style="background:${color}"></i>${detailValue(voyage.vessel, 'Unknown vessel')}</strong><small class="text-muted">${detailValue(voyage.voyage, 'Voyage not set')}</small></span>
                            <span class="badge rounded-pill ${voyage.completed ? 'bg-secondary' : 'bg-success'}">${detailValue(voyage.status, voyage.completed ? 'Completed' : 'Active')}</span>
                        </span>
                        <span class="d-grid gap-1 small text-secondary">
                            <span><strong class="text-dark">Cargo:</strong> ${detailValue(voyage.cargo)}</span>
                            <span><strong class="text-dark">Fuel departure:</strong> ${fuelValue(voyage.fuel_at_departure)}</span>
                            <span><strong class="text-dark">Fuel consumed:</strong> ${fuelValue(voyage.fuel_consumed)}</span>
                            <span><strong class="text-dark">${locationLabel}:</strong> ${detailValue(voyage.current?.name || voyage.positions?.at(-1)?.name)}</span>
                            <span><strong class="text-dark">Destination:</strong> ${detailValue(voyage.destination?.name)}</span>
                            <span><strong class="text-dark">${dateLabel}:</strong> ${detailValue(dateValue)}</span>
                            <span><strong class="text-dark">Last update:</strong> ${detailValue(voyage.last_update)}</span>
                            ${deviationWarning(voyage)}
                            <span class="mt-1 d-grid gap-1" data-weather-voyage="${escapeHtml(voyage.id)}">${weatherHtml(voyage)}</span>
                        </span>
                        <span class="d-block mt-2 small fw-semibold text-primary"><i class="bi bi-crosshair me-1"></i>Focus this voyage</span>
                    </button>`;
                }).join('');
                detailsList.querySelectorAll('[data-dashboard-voyage]').forEach((card) => card.addEventListener('click', () => {
                    const feature = features.get(String(card.dataset.dashboardVoyage));
                    if (!feature) return;
                    highlightVoyage(card.dataset.dashboardVoyage);
                    if (feature.bounds.length > 1) map.fitBounds(feature.bounds, { padding: [55, 55], maxZoom: 10 });
                    else if (feature.bounds.length === 1) map.setView(feature.bounds[0], 10);
                    feature.marker?.openPopup();
                }));
            };

            const rebuildMap = (nextTracks) => {
                tracks = Array.isArray(nextTracks) ? nextTracks : [];
                Object.values(layers).forEach((layer) => layer.clearLayers());
                features.clear();
                mapBounds.active.length = 0;
                mapBounds.previous.length = 0;
                const currentPoints = tracks.map((voyage) => {
                    const saved = (voyage.positions || []).map(coordinates).filter(Boolean);
                    return coordinates(voyage.current) || saved.at(-1) || coordinates(voyage.origin);
                });
                const collisionTotals = new Map();
                currentPoints.forEach((point, index) => {
                    if (!point) return;
                    const mode = tracks[index].completed ? 'previous' : 'active';
                    const key = `${mode}:${point[0].toFixed(5)}:${point[1].toFixed(5)}`;
                    collisionTotals.set(key, (collisionTotals.get(key) || 0) + 1);
                });
                const collisionSlots = new Map();

                tracks.forEach((voyage, index) => {
                    const mode = voyage.completed ? 'previous' : 'active';
                    const color = colorForVoyage(voyage);
                    const origin = coordinates(voyage.origin);
                    const destination = coordinates(voyage.destination);
                    const savedPositionReports = (voyage.positions || []).map(position => ({ ...position, coordinates: coordinates(position) })).filter(position => position.coordinates);
                    const current = currentPoints[index];
                    const visualLayers = [];
                    const actualTrack = [];
                    addUnique(actualTrack, origin);
                    savedPositionReports.filter(position => position.source !== 'voyage_completion').forEach(position => addUnique(actualTrack, position.coordinates));
                    if (!voyage.completed) addUnique(actualTrack, current);
                    const completionReport = voyage.completed ? savedPositionReports.findLast(position => position.source === 'voyage_completion') : null;
                    const completionPoint = completionReport?.coordinates || (voyage.completed ? current : null);
                    const toLatLng = (point) => [point[1], point[0]];
                    const plannedRoute = (voyage.planned_route || []).map((point) => Array.isArray(point) && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1])) ? [Number(point[0]), Number(point[1])] : null).filter(Boolean);
                    const routeMatch = matchRoute(plannedRoute, actualTrack, voyage.completed, completionPoint);
                    const displayedTrack = routeMatch.track;
                    voyage.route_deviation = routeMatch.hasDeviation;
                    voyage.route_deviation_nm = routeMatch.maxDeviationNm;
                    let matchedTrackLayer = null;
                    if (displayedTrack.length > 1) {
                        matchedTrackLayer = window.L.polyline(displayedTrack.map(toLatLng), { color, weight: 5, opacity: .92 }).addTo(layers[mode]);
                        visualLayers.push({ layer: matchedTrackLayer, opacity: .92, fillOpacity: 0, weight: 5 });
                    }
                    if (plannedRoute.length > 1 || (!voyage.completed && destination && current)) {
                        const plannedPoints = plannedRoute.length > 1 ? plannedRoute : [current, destination];
                        const plannedOpacity = voyage.completed ? .4 : .7;
                        const layer = window.L.polyline(plannedPoints.map(toLatLng), { color, weight: 3, opacity: plannedOpacity, dashArray: '8 8' }).addTo(layers[mode]);
                        visualLayers.push({ layer, opacity: plannedOpacity, fillOpacity: 0, weight: 3 });
                    }
                    if (routeMatch.hasDeviation && routeMatch.latestMatch) {
                        const deviationLayer = window.L.polyline(
                            [routeMatch.latestMatch.matched, routeMatch.latestMatch.reported].map(toLatLng),
                            { color: '#dc2626', weight: 2, opacity: .8, dashArray: '4 7' },
                        ).addTo(layers[mode]);
                        visualLayers.push({ layer: deviationLayer, opacity: .8, fillOpacity: 0, weight: 2 });
                    }
                    matchedTrackLayer?.bringToFront();
                    if (origin) {
                        const layer = window.L.circleMarker(toLatLng(origin), { radius: 7, color: '#fff', weight: 3, fillColor: '#059669', fillOpacity: 1 }).addTo(layers[mode]);
                        visualLayers.push({ layer, opacity: 1, fillOpacity: 1, weight: 3 });
                    }
                    if (destination) {
                        const layer = window.L.circleMarker(toLatLng(destination), { radius: 7, color: '#fff', weight: 3, fillColor: '#dc2626', fillOpacity: 1 }).addTo(layers[mode]);
                        visualLayers.push({ layer, opacity: 1, fillOpacity: 1, weight: 3 });
                    }

                    let marker = null;
                    if (current) {
                        const collisionKey = `${mode}:${current[0].toFixed(5)}:${current[1].toFixed(5)}`;
                        const total = collisionTotals.get(collisionKey) || 1;
                        const slot = collisionSlots.get(collisionKey) || 0;
                        collisionSlots.set(collisionKey, slot + 1);
                        const angle = (Math.PI * 2 * slot / total) - (Math.PI / 2);
                        const dx = total > 1 ? Math.round(Math.cos(angle) * 22) : 0;
                        const dy = total > 1 ? Math.round(Math.sin(angle) * 22) : 0;
                        const icon = window.L.divIcon({
                            className: '',
                            iconSize: [30, 30],
                            iconAnchor: [15 - dx, 15 - dy],
                            html: `<button type="button" class="vsli-map-marker ${voyage.completed ? 'is-completed' : ''}" style="--vessel-color:${color}" aria-label="Open ${escapeHtml(voyage.vessel || 'vessel')} details"><svg viewBox="0 0 36 32" width="30" height="30" aria-hidden="true"><path fill="currentColor" stroke="#fff" stroke-width="1.2" stroke-linejoin="round" d="M3 17.5h27.5l-4.8 8H8.2L3 17.5Z"/><path fill="currentColor" stroke="#fff" stroke-width="1.1" stroke-linejoin="round" d="M8 13.5h17l5.5 4H3l5-4Zm2-7h11v7H10v-7Zm11 3h5v4h-5v-4Z"/><path fill="#fff" d="M12 8.5h2.6v2.2H12zm4.2 0h2.6v2.2h-2.6zm6.2 2.5h2v1.5h-2z"/></svg></button>`,
                        });
                        marker = window.L.marker(toLatLng(current), { icon }).bindPopup(popupHtml(voyage)).addTo(layers[mode]);
                        marker.on('click', () => highlightVoyage(voyage.id));
                    }
                    const bounds = [...displayedTrack, current, destination].filter(Boolean).map(toLatLng);
                    mapBounds[mode].push(...bounds);
                    features.set(String(voyage.id), { bounds, marker, voyage, visualLayers, mode });
                });
                if (selectedVoyageId !== null && !features.has(selectedVoyageId)) selectedVoyageId = null;
                highlightVoyage(selectedVoyageId);
                tracks.filter((voyage) => !voyage.completed).forEach((voyage) => loadVoyageWeather(voyage));
            };

            const showMapMode = (mode, shouldFit = true) => {
                currentMapMode = mode;
                Object.entries(layers).forEach(([key, layer]) => {
                    if (key === mode && !map.hasLayer(layer)) layer.addTo(map);
                    if (key !== mode && map.hasLayer(layer)) map.removeLayer(layer);
                });
                filterButtons.forEach((button) => {
                    const selected = button.dataset.dashboardMapFilter === mode;
                    button.classList.toggle('is-active', selected);
                    button.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });
                const count = tracks.filter((voyage) => (voyage.completed ? 'previous' : 'active') === mode).length;
                if (countBadge) countBadge.textContent = `${count} ${count === 1 ? 'track' : 'tracks'}`;
                renderVoyageDetails(mode);
                highlightVoyage(selectedVoyageId);
                emptyMessage?.classList.toggle('d-none', count > 0);
                if (shouldFit && mapBounds[mode].length) map.fitBounds(mapBounds[mode], { padding: [45, 45], maxZoom: 9 });
            };

            const updateClock = () => {
                const now = new Date();
                if (liveTime) liveTime.textContent = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).format(now);
                if (liveDate) liveDate.textContent = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }).format(now);
            };
            const refreshLiveFleet = async () => {
                if (liveRefreshRunning || document.hidden) return;
                liveRefreshRunning = true;
                if (liveSync) liveSync.textContent = 'Checking vessel updates...';
                try {
                    const response = await fetch(liveFleetUrl, { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) throw new Error('Live data request failed');
                    const payload = await response.json();
                    rebuildMap(payload.tracks);
                    showMapMode(currentMapMode, false);
                    if (liveSync) liveSync.textContent = `Live - synced ${new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(new Date())}`;
                } catch (error) {
                    if (liveSync) liveSync.textContent = 'Connection interrupted - retrying';
                } finally {
                    liveRefreshRunning = false;
                }
            };

            filterButtons.forEach((button) => button.addEventListener('click', () => {
                selectedVoyageId = null;
                showMapMode(button.dataset.dashboardMapFilter);
            }));
            map.on('click', () => highlightVoyage(null));
            detailsToggle?.addEventListener('click', () => {
                const hidden = mapLayout?.classList.toggle('is-details-hidden') ?? false;
                detailsToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
                const label = detailsToggle.querySelector('span');
                if (label) label.textContent = hidden ? 'Show details' : 'Hide details';
                window.setTimeout(() => map.invalidateSize(), 100);
            });
            fullscreenButton?.addEventListener('click', async () => {
                if (document.fullscreenElement) await document.exitFullscreen();
                else if (mapSection?.requestFullscreen) await mapSection.requestFullscreen();
                else mapSection?.classList.toggle('is-monitor-fullscreen');
                window.setTimeout(() => map.invalidateSize(), 150);
            });
            document.addEventListener('fullscreenchange', () => window.setTimeout(() => map.invalidateSize(), 150));
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshLiveFleet(); });
            rebuildMap(tracks);
            showMapMode('active');
            updateClock();
            window.setInterval(updateClock, 1000);
            window.setInterval(refreshLiveFleet, 15000);
            window.setInterval(() => {
                if (!document.hidden) tracks.filter((voyage) => !voyage.completed).forEach((voyage) => loadVoyageWeather(voyage, true));
            }, 10 * 60 * 1000);
            if (liveSync) liveSync.textContent = 'Live - refreshes every 15 seconds';
        };

        startLeafletMap();
        return;
    };
})();





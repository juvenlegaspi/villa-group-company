<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const canCreate = @json($canCreateChecklist);
    const vessels = @json($vessels->map(fn ($vessel) => ['id' => $vessel->id, 'name' => $vessel->vessel_name])->values());
    const routes = {list: @json(route('shipping.calendar.events.index')), store: @json(route('shipping.calendar.events.store')), base: @json(url('/shipping/calendar/events'))};
    const requestedVessel = Number(new URLSearchParams(location.search).get('vessel'));
    const savedVessel = Number(localStorage.getItem('shippingChecklistVessel'));
    const initialVessel = vessels.find(vessel => vessel.id === requestedVessel)?.id || vessels.find(vessel => vessel.id === savedVessel)?.id || vessels[0]?.id || null;
    const state = {date: new Date(), view: localStorage.getItem('shippingCalendarView') || 'month', vesselId: initialVessel, events: [], detail: null};
    const content = document.getElementById('calendarContent');
    const title = document.getElementById('calendarTitle');
    const vesselLabel = document.getElementById('selectedVesselLabel');
    const loading = document.getElementById('calendarLoading');
    const checklistModal = canCreate ? bootstrap.Modal.getOrCreateInstance(document.getElementById('checklistModal')) : null;
    const detailModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('checklistDetailModal'));
    const pad = number => String(number).padStart(2, '0');
    const dateOnly = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const localValue = date => `${dateOnly(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    const addDays = (date, days) => { const result = new Date(date); result.setDate(result.getDate() + days); return result; };
    const startOfWeek = date => { const result = new Date(date); result.setHours(0, 0, 0, 0); result.setDate(result.getDate() - result.getDay()); return result; };
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[character]));
    const val = id => document.getElementById(id)?.value ?? '';
    const setVal = (id, value) => { const element = document.getElementById(id); if (element) element.value = value; };

    async function api(url, options = {}) {
        const headers = {Accept: 'application/json', 'X-CSRF-TOKEN': csrf};
        if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {...options, headers: {...headers, ...(options.headers || {})}});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join('<br>') : (data.message || 'The request could not be completed.'));
        return data;
    }

    function range() {
        if (state.view === 'month') {
            const first = new Date(state.date.getFullYear(), state.date.getMonth(), 1);
            const start = startOfWeek(first);
            return {start, end: addDays(start, 41)};
        }
        if (state.view === 'week') {
            const start = startOfWeek(state.date);
            return {start, end: addDays(start, 6)};
        }
        const start = new Date(state.date);
        start.setHours(0, 0, 0, 0);
        return {start, end: start};
    }

    async function load() {
        highlightVessel();
        if (!state.vesselId) {
            title.textContent = 'No vessel assigned'; vesselLabel.textContent = '';
            content.innerHTML = '<div class="p-12 text-center text-sm font-bold text-slate-500">Ask the administrator to assign a vessel to your account.</div>';
            return;
        }
        loading.classList.remove('hidden'); content.innerHTML = '';
        const visibleRange = range();
        try {
            state.events = await api(`${routes.list}?start=${dateOnly(visibleRange.start)}&end=${dateOnly(visibleRange.end)}&vessel_id=${state.vesselId}`);
            render();
        } catch (error) {
            content.innerHTML = `<div class="p-10 text-center text-rose-700">${esc(error.message)}</div>`;
        } finally { loading.classList.add('hidden'); }
    }

    function highlightVessel() {
        document.querySelectorAll('[data-vessel-id]').forEach(button => button.classList.toggle('active', Number(button.dataset.vesselId) === state.vesselId));
        vesselLabel.textContent = vessels.find(vessel => vessel.id === state.vesselId)?.name || '';
    }

    function render() {
        document.querySelectorAll('[data-calendar-view]').forEach(button => {
            button.className = `rounded-lg px-4 py-2 text-xs font-black capitalize ${button.dataset.calendarView === state.view ? 'bg-white text-villa-800 shadow' : 'text-slate-500'}`;
        });
        state.view === 'month' ? renderMonth() : renderAgenda();
    }

    function eventsForDay(day) {
        const start = new Date(day); start.setHours(0, 0, 0, 0);
        const end = new Date(day); end.setHours(23, 59, 59, 999);
        return state.events.filter(event => new Date(event.start) <= end && new Date(event.end) >= start).sort((left, right) => new Date(left.start) - new Date(right.start));
    }

    function itemButton(item) {
        const time = new Date(item.start).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
        return `<button class="vcal-item ${item.status === 'cancelled' ? 'cancelled' : ''}" style="--item-color:${item.color}" data-occurrence="${item.occurrence_id}" draggable="${item.can_edit && !item.recurring}"><span class="opacity-70">${time}</span> ${esc(item.title)}</button>`;
    }

    function renderMonth() {
        const visibleRange = range(); const month = state.date.getMonth();
        title.textContent = state.date.toLocaleDateString(undefined, {month: 'long', year: 'numeric'});
        let html = '<div class="vcal-scroll"><div class="vcal-grid">' + ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => `<div class="vcal-weekday">${day}</div>`).join('');
        for (let index = 0; index < 42; index++) {
            const day = addDays(visibleRange.start, index); const key = dateOnly(day); const items = eventsForDay(day);
            html += `<div class="vcal-day ${day.getMonth() !== month ? 'outside' : ''} ${key === dateOnly(new Date()) ? 'today' : ''}" data-day="${key}"><div class="flex items-center justify-between"><button class="vcal-number" data-create-day="${key}">${day.getDate()}</button>${canCreate ? `<button class="vcal-add" data-create-day="${key}" aria-label="Add checklist">+</button>` : ''}</div>${items.slice(0, 4).map(itemButton).join('')}${items.length > 4 ? `<button class="mt-1 text-xs font-bold text-villa-700" data-open-day="${key}">+${items.length - 4} more</button>` : ''}</div>`;
        }
        content.innerHTML = html + '</div></div>'; bindCalendar();
    }

    function renderAgenda() {
        const visibleRange = range();
        const days = state.view === 'week' ? Array.from({length: 7}, (_, index) => addDays(visibleRange.start, index)) : [visibleRange.start];
        title.textContent = state.view === 'week' ? `${visibleRange.start.toLocaleDateString(undefined, {month: 'short', day: 'numeric'})} – ${visibleRange.end.toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'})}` : visibleRange.start.toLocaleDateString(undefined, {weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'});
        content.innerHTML = `<div class="vcal-agenda ${state.view}">${days.map(day => {
            const key = dateOnly(day); const items = eventsForDay(day);
            return `<section class="vcal-column ${key === dateOnly(new Date()) ? 'today' : ''}" data-day="${key}"><div class="mb-3"><b class="block text-sm">${day.toLocaleDateString(undefined, {weekday: 'short'})}</b><span class="text-xs text-slate-500">${day.toLocaleDateString(undefined, {month: 'short', day: 'numeric'})}</span></div>${canCreate ? `<button type="button" class="vcal-agenda-add" data-create-day="${key}" aria-label="Add checklist for ${key}"><span aria-hidden="true">+</span> Add Checklist</button>` : ''}${items.length ? items.map(itemButton).join('') : '<p class="text-xs text-slate-400">No checklist scheduled</p>'}</section>`;
        }).join('')}</div>`;
        bindCalendar();
    }

    function bindCalendar() {
        content.querySelectorAll('[data-occurrence]').forEach(button => {
            button.onclick = click => { click.stopPropagation(); const occurrence = state.events.find(item => item.occurrence_id === button.dataset.occurrence); if (occurrence) openDetail(occurrence.event_id); };
            button.ondragstart = drag => drag.dataTransfer.setData('text/plain', button.dataset.occurrence);
        });
        content.querySelectorAll('[data-create-day]').forEach(button => button.onclick = click => { click.stopPropagation(); openCreate(button.dataset.createDay); });
        content.querySelectorAll('[data-open-day]').forEach(button => button.onclick = click => { click.stopPropagation(); state.date = new Date(`${button.dataset.openDay}T12:00`); state.view = 'day'; load(); });
        content.querySelectorAll('[data-day]').forEach(cell => {
            if (canCreate) cell.onclick = click => { if (!click.target.closest('button')) openCreate(cell.dataset.day); };
            cell.ondragover = drag => { drag.preventDefault(); cell.classList.add('drag-over'); };
            cell.ondragleave = () => cell.classList.remove('drag-over');
            cell.ondrop = drop => { drop.preventDefault(); drop.stopPropagation(); cell.classList.remove('drag-over'); const item = state.events.find(candidate => candidate.occurrence_id === drop.dataTransfer.getData('text/plain')); if (item) moveChecklist(item, cell.dataset.day); };
        });
    }

    function openCreate(day = dateOnly(state.date)) {
        if (!canCreate || !state.vesselId) return;
        document.getElementById('checklistForm').reset(); setVal('checklistId', '');
        document.getElementById('checklistModalTitle').textContent = 'Add Checklist'; clearErrors();
        const start = new Date(`${day}T08:00`); const end = new Date(`${day}T09:00`);
        setVal('checklistVessel', state.vesselId); setVal('checklistStart', localValue(start)); setVal('checklistEnd', localValue(end));
        setVal('checklistReminder', '0'); setVal('checklistRecurrenceInterval', 1); toggleRecurrence(); checklistModal.show();
    }

    async function openDetail(id) {
        try {
            const data = await api(`${routes.base}/${id}`); const event = data.event; const attachments = data.attachments || []; state.detail = data;
            document.getElementById('checklistDetailTitle').textContent = event.title;
            const badge = document.getElementById('detailType');
            badge.textContent = {routine: 'Routine checklist', scheduled: 'Scheduled activity', one_time: 'One-time activity'}[event.checklist_type] || 'Checklist';
            badge.className = 'mb-2 inline-flex rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-black uppercase text-cyan-800';
            const attachmentHtml = attachments.length
                ? `<div class="grid gap-2">${attachments.map(file => `<div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2"><a href="${esc(file.download_url)}" class="flex min-w-0 flex-1 items-center justify-between gap-3 px-1 text-decoration-none"><span class="min-w-0"><b class="block truncate text-sm text-slate-800">${esc(file.name)}</b><small class="text-slate-500">${formatBytes(file.size_bytes)} · ${esc(file.uploaded_by)} · ${esc(file.uploaded_at)}</small></span><i class="bi bi-download shrink-0 text-cyan-700"></i></a>${data.can_edit ? `<button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border-0 bg-rose-100 text-rose-700" data-delete-attachment="${file.id}" data-delete-url="${esc(file.delete_url)}" aria-label="Remove ${esc(file.name)}"><i class="bi bi-trash3"></i></button>` : ''}</div>`).join('')}</div>`
                : '<p class="mb-0 text-sm text-slate-500">No attachment uploaded.</p>';
            document.getElementById('checklistDetailBody').innerHTML = `<div class="grid gap-4 sm:grid-cols-2"><div><b class="text-xs uppercase text-slate-400">Vessel</b><p class="mt-1 font-bold">${esc(event.vessel)}</p></div><div><b class="text-xs uppercase text-slate-400">Schedule</b><p class="mt-1 font-bold">${formatSchedule(event)}</p></div><div><b class="text-xs uppercase text-slate-400">Created by</b><p class="mt-1 font-bold">${esc(event.creator)}</p></div><div><b class="text-xs uppercase text-slate-400">Area / location</b><p class="mt-1 font-bold">${esc(event.location || '—')}</p></div></div>${event.description ? `<div class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm">${esc(event.description)}</div>` : ''}<div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4"><h3 class="mb-3 text-sm font-black text-slate-900"><i class="bi bi-paperclip me-1 text-cyan-700"></i>Checklist attachments (${attachments.length})</h3>${attachmentHtml}</div><div class="mt-5 rounded-xl border border-cyan-200 bg-cyan-50 p-4"><h3 class="mb-2 text-sm font-black text-cyan-950">Reminder recipients</h3><div class="flex flex-wrap gap-2">${data.recipients.length ? data.recipients.map(recipient => `<span class="rounded-full bg-white px-3 py-1.5 text-xs font-bold ring-1 ring-cyan-200">${esc(recipient.name)}</span>`).join('') : '<span class="text-sm font-bold text-rose-700">No assigned Captain or Operations Manager found.</span>'}</div><p class="mb-0 mt-3 text-xs text-cyan-800">System alert, email and SMS are sent at the configured reminder time when recipient contact details and Semaphore are available.</p></div><details class="mt-5 rounded-xl border"><summary class="cursor-pointer p-3 text-sm font-black">Audit history (${data.audits.length})</summary><div class="border-t p-3">${data.audits.map(audit => `<p class="mb-2 text-xs"><b>${esc(audit.action)}</b> · ${esc(audit.user)} · ${esc(audit.date)}</p>`).join('') || '<p class="text-xs text-slate-400">No history</p>'}</div></details>`;
            const footer = document.getElementById('checklistDetailFooter');
            footer.innerHTML = data.can_edit && canCreate ? '<button id="editChecklistButton" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Edit</button><button id="cancelChecklistButton" class="rounded-xl bg-amber-100 px-4 py-2 text-sm font-bold text-amber-800">Cancel schedule</button><button id="deleteChecklistButton" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white">Delete</button>' : '';
            document.getElementById('editChecklistButton')?.addEventListener('click', editDetail);
            document.getElementById('cancelChecklistButton')?.addEventListener('click', () => cancelChecklist(event.id));
            document.getElementById('deleteChecklistButton')?.addEventListener('click', () => deleteChecklist(event.id));
            document.querySelectorAll('[data-delete-attachment]').forEach(button => button.addEventListener('click', () => deleteAttachment(button.dataset.deleteUrl, event.id)));
            detailModal.show();
        } catch (error) { toast(error.message, false); }
    }

    function editDetail() {
        const event = state.detail.event; detailModal.hide(); document.getElementById('checklistForm').reset();
        const fields = {checklistId: 'id', checklistTitle: 'title', checklistType: 'checklist_type', checklistVessel: 'vessel_id', checklistStart: 'starts_at', checklistEnd: 'ends_at', checklistLocation: 'location', checklistDescription: 'description', checklistRecurrence: 'recurrence_frequency', checklistRecurrenceInterval: 'recurrence_interval', checklistRecurrenceEnd: 'recurrence_ends_on', checklistReminder: 'reminder_minutes'};
        Object.entries(fields).forEach(([field, key]) => setVal(field, event[key] ?? ''));
        document.getElementById('checklistModalTitle').textContent = 'Edit Checklist'; clearErrors(); toggleRecurrence(); setTimeout(() => checklistModal.show(), 180);
    }

    function payload() {
        const type = val('checklistType'); const colors = {routine: '#2563eb', scheduled: '#06b6d4', one_time: '#f59e0b'};
        return {title: val('checklistTitle'), checklist_type: type, vessel_id: Number(val('checklistVessel')), starts_at: val('checklistStart'), ends_at: val('checklistEnd'), all_day: false, color: colors[type], location: val('checklistLocation'), description: val('checklistDescription'), recurrence_frequency: val('checklistRecurrence'), recurrence_interval: Number(val('checklistRecurrenceInterval') || 1), recurrence_ends_on: val('checklistRecurrenceEnd'), reminder_minutes: Number(val('checklistReminder'))};
    }

    function formPayload(id) {
        const form = new FormData();
        Object.entries(payload()).forEach(([key, value]) => form.append(key, value === false ? '0' : value));
        Array.from(document.getElementById('checklistAttachments')?.files || []).forEach(file => form.append('attachments[]', file));
        if (id) form.append('_method', 'PUT');
        return form;
    }

    if (canCreate) document.getElementById('checklistForm').onsubmit = async submit => {
        submit.preventDefault(); clearErrors(); const id = val('checklistId'); const button = document.getElementById('saveChecklistButton'); button.disabled = true;
        try {
            const data = await api(id ? `${routes.base}/${id}` : routes.store, {method: 'POST', body: formPayload(id)});
            checklistModal.hide(); state.vesselId = Number(val('checklistVessel')); toast(data.message, true); await load();
        } catch (error) { showErrors(error.message); } finally { button.disabled = false; }
    };

    async function moveChecklist(item, day) {
        const oldStart = new Date(item.start); const oldEnd = new Date(item.end);
        const start = new Date(`${day}T${pad(oldStart.getHours())}:${pad(oldStart.getMinutes())}`); const end = new Date(start.getTime() + (oldEnd - oldStart));
        try { const data = await api(`${routes.base}/${item.event_id}/move`, {method: 'PATCH', body: JSON.stringify({starts_at: localValue(start), ends_at: localValue(end)})}); toast(data.message, true); load(); } catch (error) { toast(error.message, false); }
    }
    async function cancelChecklist(id) {
        if (!confirm('Cancel this checklist schedule?')) return;
        try { const data = await api(`${routes.base}/${id}/cancel`, {method: 'PATCH', body: '{}'}); detailModal.hide(); toast(data.message, true); load(); } catch (error) { toast(error.message, false); }
    }
    async function deleteChecklist(id) {
        if (!confirm('Delete this checklist schedule?')) return;
        try { const data = await api(`${routes.base}/${id}`, {method: 'DELETE'}); detailModal.hide(); toast(data.message, true); load(); } catch (error) { toast(error.message, false); }
    }
    async function deleteAttachment(url, eventId) {
        if (!confirm('Remove this checklist attachment?')) return;
        try { const data = await api(url, {method: 'DELETE'}); toast(data.message, true); await openDetail(eventId); } catch (error) { toast(error.message, false); }
    }

    function formatSchedule(event) { return `${new Date(event.starts_at).toLocaleString()} – ${new Date(event.ends_at).toLocaleString()}${event.recurrence_frequency ? ' · Repeats ' + event.recurrence_frequency : ''}`; }
    function formatBytes(bytes) { const size = Number(bytes || 0); if (size < 1024) return `${size} B`; if (size < 1048576) return `${(size / 1024).toFixed(1)} KB`; return `${(size / 1048576).toFixed(1)} MB`; }
    function clearErrors() { const errors = document.getElementById('checklistErrors'); if (errors) { errors.classList.add('d-none'); errors.innerHTML = ''; } }
    function showErrors(message) { const errors = document.getElementById('checklistErrors'); errors.innerHTML = `<b>Checklist was not saved.</b><div class="mt-1">${message}</div>`; errors.classList.remove('d-none'); }
    function toast(message, successful) { const element = document.getElementById('calendarToast'); element.textContent = message; element.className = `vcal-toast ${successful ? 'bg-emerald-600' : 'bg-rose-600'}`; setTimeout(() => element.classList.add('hidden'), 4000); }
    function toggleRecurrence() { const show = Boolean(val('checklistRecurrence')); document.getElementById('recurrenceIntervalField')?.classList.toggle('hidden', !show); document.getElementById('recurrenceEndField')?.classList.toggle('hidden', !show); }

    document.querySelectorAll('[data-vessel-id]').forEach(button => button.onclick = () => { state.vesselId = Number(button.dataset.vesselId); localStorage.setItem('shippingChecklistVessel', state.vesselId); load(); });
    document.getElementById('newChecklistButton')?.addEventListener('click', () => openCreate());
    document.getElementById('checklistRecurrence')?.addEventListener('change', toggleRecurrence);
    document.getElementById('todayButton').onclick = () => { state.date = new Date(); load(); };
    document.getElementById('previousButton').onclick = () => { state.date = state.view === 'month' ? new Date(state.date.getFullYear(), state.date.getMonth() - 1, 1) : addDays(state.date, state.view === 'week' ? -7 : -1); load(); };
    document.getElementById('nextButton').onclick = () => { state.date = state.view === 'month' ? new Date(state.date.getFullYear(), state.date.getMonth() + 1, 1) : addDays(state.date, state.view === 'week' ? 7 : 1); load(); };
    document.querySelectorAll('[data-calendar-view]').forEach(button => button.onclick = () => { state.view = button.dataset.calendarView; localStorage.setItem('shippingCalendarView', state.view); load(); });
    load().then(() => { const eventId = new URLSearchParams(location.search).get('event'); if (eventId) openDetail(eventId); });
});
</script>

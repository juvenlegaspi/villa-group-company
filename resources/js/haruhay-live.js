const live = document.querySelector('[data-haruhay-live]');
if (live) {
    const directory = document.querySelector('#haruhay-live-directory');
    const toast = document.querySelector('#haruhay-reservation-toast');
    const state = document.querySelector('[data-reservation-live-state]');
    const key = `haruhay-reservation-cursor-${live.dataset.user}`;
    let cursor = 0;
    try { cursor = Number(localStorage.getItem(key)) || 0; } catch { /* Browser storage is optional. */ }
    let version = '';
    let timer;
    let inFlight = false;
    let stopped = false;
    const schedule = delay => { clearTimeout(timer); if (!stopped) timer = setTimeout(poll, delay); };
    const poll = async () => {
        if (stopped || inFlight || document.hidden) return;
        inFlight = true;
        let delay = 5000;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const url = new URL(live.dataset.url, location.href);
            url.searchParams.set('since', String(cursor));
            if (directory) {
                for (const name of ['search', 'status', 'page']) {
                    const value = new URL(location.href).searchParams.get(name);
                    if (value) url.searchParams.set(name, value);
                }
                url.searchParams.set('directory', '1');
                if (version) url.searchParams.set('version', version);
            }
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
            if (response.redirected || [401, 403, 419].includes(response.status)) {
                stopped = true;
                toast.hidden = true;
                if (state) state.textContent = 'Live updates paused. Sign in again.';
                return;
            }
            if (!response.ok) throw new Error('Live updates temporarily unavailable');
            const data = await response.json();
            if (data.unread_count === 0) toast.hidden = true;
            document.querySelectorAll('[data-haruhay-reservation-link]').forEach(link => {
                link.classList.toggle('haruhay-has-new', data.unread_count > 0);
                link.setAttribute('aria-label', `Online Reservations, ${data.unread_count} unread`);
            });
            document.querySelectorAll('[data-haruhay-reservation-badge]').forEach(badge => {
                badge.hidden = data.unread_count === 0;
                badge.textContent = data.unread_count > 99 ? '99+' : String(data.unread_count);
            });
            const bell = document.querySelector('.notification-action');
            if (bell) {
                let badge = bell.querySelector('.notification-count');
                if (!badge) { badge = document.createElement('span'); badge.className = 'notification-count'; bell.append(badge); }
                badge.hidden = data.notification_count === 0;
                badge.textContent = data.notification_count > 99 ? '99+' : String(data.notification_count);
                bell.setAttribute('aria-label', `Notifications, ${data.notification_count} unread`);
            }
            if (directory && data.directory_html !== undefined && version !== data.version) directory.innerHTML = data.directory_html;
            if (directory) {
                version = data.version;
                if (data.stats) document.querySelectorAll('[data-reservation-stat]').forEach(element => { element.textContent = data.stats[element.dataset.reservationStat] ?? 0; });
                const total = document.querySelector('#haruhay-reservation-total');
                if (total && data.total !== undefined) total.textContent = data.total;
            }
            if (data.items?.length) {
                const item = data.items[0];
                const destination = new URL(item.url, location.href);
                if (destination.origin === location.origin) {
                    toast.querySelector('[data-reservation-toast-message]').textContent = `${item.name} requested ${item.room}. ${item.reference}${data.items.length > 1 ? ` · ${data.items.length - 1} more unread requests` : ''}`;
                    toast.querySelector('[data-reservation-toast-link]').href = destination.href;
                    toast.hidden = false;
                }
            }
            cursor = Math.max(cursor, Number(data.latest_id) || 0);
            try { localStorage.setItem(key, String(cursor)); } catch { /* Updates work without storage. */ }
            if (state) state.textContent = 'Live · checks every 5 seconds';
        } catch {
            delay = 15000;
            if (state) state.textContent = 'Reconnecting to live updates…';
        } finally {
            clearTimeout(timeout);
            inFlight = false;
            schedule(delay);
        }
    };
    toast.querySelector('[data-dismiss-reservation-toast]').addEventListener('click', () => { toast.hidden = true; });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { clearTimeout(timer); poll(); } });
    window.addEventListener('focus', () => { clearTimeout(timer); poll(); });
    window.addEventListener('storage', event => { if (event.key === key) cursor = Math.max(cursor, Number(event.newValue) || 0); });
    poll();
}

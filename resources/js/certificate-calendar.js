const dateLabel = (date) => new Date(`${date}T00:00:00Z`).toLocaleDateString('en-US', { timeZone: 'UTC', month: 'long', day: 'numeric', year: 'numeric' });
const element = (tag, text, className = '') => {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = className;
    return node;
};

document.querySelectorAll('[data-certificate-calendar]').forEach((root) => {
    const initial = JSON.parse(root.querySelector('[data-calendar-initial]').textContent);
    const find = (name) => root.querySelector(`[data-calendar-${name}]`);
    const dialog = root.querySelector('dialog');
    let data = initial;
    let selected;
    let pending;
    let requestId = 0;

    const drawGrid = (target, monthData, mini = false) => {
        target.replaceChildren();
        const [year, month] = monthData.month.split('-').map(Number);
        const offset = new Date(Date.UTC(year, month - 1, 1)).getUTCDay();
        const days = new Date(Date.UTC(year, month, 0)).getUTCDate();
        if (mini) {
            target.setAttribute('aria-hidden', 'true');
            ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach((label) => target.append(element('span', label, 'mini-weekday')));
        }
        for (let i = 0; i < offset; i++) target.append(element('span', ''));
        for (let day = 1; day <= days; day++) {
            const date = `${monthData.month}-${String(day).padStart(2, '0')}`;
            const events = monthData.events.filter((event) => event.date === date);
            const cell = element(mini ? 'span' : 'button', String(day), `certificate-day${events.length ? ' has-expiry' : ''}${date === monthData.today ? ' is-today' : ''}`);
            if (!mini) {
                cell.type = 'button';
                cell.dataset.date = date;
                cell.setAttribute('aria-label', `${dateLabel(date)}: ${events.length} certificate expiries`);
                cell.setAttribute('aria-pressed', String(date === selected));
                if (date === monthData.today) cell.setAttribute('aria-current', 'date');
                if (events.length) cell.append(element('small', `${events.length} ${events.length === 1 ? 'expiry' : 'expiries'}`));
                cell.addEventListener('click', () => select(date));
            }
            target.append(cell);
        }
    };

    const select = (date) => {
        selected = date;
        find('grid').querySelectorAll('button').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.date === date)));
        find('selected-date').textContent = dateLabel(date);
        const list = find('events');
        list.replaceChildren();
        const events = data.events.filter((event) => event.date === date);
        if (!events.length) list.append(element('p', 'No certificate expiries on this date.', 'certificate-calendar-empty'));
        events.forEach((event) => {
            const card = element('article', '', 'certificate-expiry-event');
            card.append(element('span', event.status, 'certificate-expiry-status'), element('h4', event.name), element('p', event.vessel));
            if (event.number) card.append(element('p', `Certificate no. ${event.number}`));
            const url = new URL(event.url, window.location.href);
            if (url.origin === window.location.origin) {
                const link = element('a', 'View certificate →');
                link.href = url.href;
                card.append(link);
            }
            list.append(card);
        });
    };

    const render = () => {
        find('title').textContent = data.title;
        find('month').value = data.month;
        selected = data.month === data.today.slice(0, 7) ? data.today : (data.events[0]?.date ?? `${data.month}-01`);
        drawGrid(find('grid'), data);
        select(selected);
        find('feedback').textContent = `${data.total} certificate ${data.total === 1 ? 'expiry' : 'expiries'} in ${data.title}. Select a date for details.`;
        find('previous').disabled = data.month === '1900-01';
        find('next').disabled = data.month === '2099-12';
    };

    const load = async (month) => {
        if (!/^(19|20)\d{2}-(0[1-9]|1[0-2])$/.test(month)) return;
        pending?.abort();
        pending = new AbortController();
        const id = ++requestId;
        find('feedback').textContent = 'Loading certificate expiry dates…';
        find('grid').setAttribute('aria-busy', 'true');
        try {
            const url = new URL(root.dataset.endpoint, window.location.href);
            url.searchParams.set('month', month);
            if (root.dataset.vessel) url.searchParams.set('vessel_id', root.dataset.vessel);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pending.signal });
            if (!response.ok) throw new Error('Unable to load calendar');
            const result = await response.json();
            if (id !== requestId) return;
            data = result;
            render();
        } catch (error) {
            if (error.name !== 'AbortError' && id === requestId) {
                find('month').value = data.month;
                find('feedback').textContent = 'Could not load this month. Please try again. If your session expired, sign in again.';
            }
        } finally {
            if (id === requestId) find('grid').removeAttribute('aria-busy');
        }
    };
    const shift = (amount) => {
        const [year, month] = data.month.split('-').map(Number);
        const date = new Date(Date.UTC(year, month - 1 + amount, 1));
        load(`${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`);
    };

    root.querySelector('[data-mini-month]').textContent = initial.title;
    drawGrid(root.querySelector('[data-mini-grid]'), initial, true);
    render();
    root.querySelectorAll('[data-calendar-open]').forEach((button) => button.addEventListener('click', () => dialog.showModal()));
    find('close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    find('previous').addEventListener('click', () => shift(-1));
    find('next').addEventListener('click', () => shift(1));
    find('today').addEventListener('click', () => load(initial.today.slice(0, 7)));
    find('month').addEventListener('change', (event) => load(event.target.value));
});

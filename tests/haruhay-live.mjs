import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync('resources/js/haruhay-live.js', 'utf8');
const element = () => ({ hidden: false, textContent: '', dataset: {}, attributes: {}, classes: new Set(), listeners: {}, classList: { toggle() {} }, setAttribute(name, value) { this.attributes[name] = value; }, addEventListener(name, handler) { this.listeners[name] = handler; } });
const live = element(); live.dataset = { url: 'http://localhost/haruhay/reservations/live', user: '5' };
const directory = element(); let replacements = 0; let html = '';
Object.defineProperty(directory, 'innerHTML', { get: () => html, set: value => { html = value; replacements++; } });
const toast = element(); toast.hidden = true;
const message = element(), toastLink = element(), dismiss = element(), badge = element(), nav = element(), bell = element(), state = element(), total = element(), stat = element();
stat.dataset.reservationStat = 'PendingReview';
nav.classList.toggle = (name, enabled) => enabled ? nav.classes.add(name) : nav.classes.delete(name);
const toastParts = { '[data-reservation-toast-message]': message, '[data-reservation-toast-link]': toastLink, '[data-dismiss-reservation-toast]': dismiss };
toast.querySelector = selector => toastParts[selector];
bell.querySelector = () => null; bell.append = value => { bell.badge = value; };
const map = { '[data-haruhay-live]': live, '#haruhay-live-directory': directory, '#haruhay-reservation-toast': toast, '[data-reservation-live-state]': state, '.notification-action': bell, '#haruhay-reservation-total': total };
const documentEvents = {}, windowEvents = {}, timers = new Map(), requests = [], stored = new Map();
let nextTimer = 0;
const responses = [
 { unread_count: 1, notification_count: 2, latest_id: 8, items: [{ name: '<script>Unsafe</script>', room: 'DR-14', reference: 'RSV-TEST', url: '/haruhay/reservations/8' }], version: 'v1', directory_html: '<tr>escaped server-rendered row</tr>', stats: { PendingReview: 1 }, total: 1 },
 { unread_count: 0, notification_count: 1, latest_id: 8, items: [], version: 'v1' },
];
const context = {
 URL, AbortController, location: { href: 'http://localhost/haruhay/reservations?status=PendingReview&page=2', origin: 'http://localhost' },
 document: { hidden: false, querySelector: selector => map[selector] ?? null, querySelectorAll: selector => ({ '[data-haruhay-reservation-link]': [nav], '[data-haruhay-reservation-badge]': [badge], '[data-reservation-stat]': [stat] }[selector] ?? []), createElement: element, addEventListener: (event, handler) => { documentEvents[event] = handler; } },
 window: { addEventListener: (event, handler) => { windowEvents[event] = handler; } },
 localStorage: { getItem: key => stored.get(key), setItem: (key, value) => stored.set(key, value) },
 setTimeout: (handler, delay) => { timers.set(++nextTimer, { handler, delay }); return nextTimer; }, clearTimeout: id => timers.delete(id),
 fetch: async url => { requests.push(url); const data = responses.shift(); return data ? { ok: true, status: 200, json: async () => data } : { ok: false, status: 401 }; },
};
vm.runInNewContext(source, context);
await new Promise(setImmediate);
assert.equal(requests.length, 1);
assert.equal(requests[0].searchParams.get('page'), '2');
assert.equal(requests[0].searchParams.get('status'), 'PendingReview');
assert.equal(replacements, 1);
assert.equal(toast.hidden, false);
assert.ok(message.textContent.includes('<script>Unsafe</script>'));
assert.equal(message.innerHTML, undefined, 'Applicant names must use textContent');
assert.equal(badge.textContent, '1'); assert.ok(nav.classes.has('haruhay-has-new'));
assert.equal(stored.get('haruhay-reservation-cursor-5'), '8');
dismiss.listeners.click(); assert.equal(toast.hidden, true);
context.document.hidden = true;
const scheduled = [...timers.values()].find(timer => timer.delay === 5000); scheduled.handler();
await new Promise(setImmediate); assert.equal(requests.length, 1, 'Hidden pages must not poll');
context.document.hidden = false; documentEvents.visibilitychange();
await new Promise(setImmediate);
assert.equal(requests.length, 2); assert.equal(replacements, 1, 'Unchanged directory must not be replaced');
assert.equal(badge.hidden, true); assert.equal(nav.classes.has('haruhay-has-new'), false);
windowEvents.focus(); await new Promise(setImmediate);
assert.ok(state.textContent.includes('Sign in again')); assert.equal(toast.hidden, true);
console.log('Live reservation UI passed: background updates, badges, filters, safe text, deduplication, visibility pause and expired-session handling.');

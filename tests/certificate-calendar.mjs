import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
class Node {
    constructor() { this.children = []; this.listeners = {}; this.dataset = {}; this.attributes = {}; this.textContent = ''; }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren() { this.children = []; }
    setAttribute(key, value) { this.attributes[key] = value; }
    removeAttribute(key) { delete this.attributes[key]; }
    addEventListener(name, handler) { this.listeners[name] = handler; }
    querySelectorAll() { return this.children.filter(node => node.type === 'button'); }
    showModal() { this.open = true; }
    close() { this.open = false; }
    click() { return this.listeners.click?.({ target: this }); }
}
const nodes = new Map();
const get = key => { if (!nodes.has(key)) nodes.set(key, new Node()); return nodes.get(key); };
const initial = { month: '2028-02', title: 'February 2028', today: '2028-02-15', total: 1, events: [{ date: '2028-02-29', name: '<script>unsafe</script>', vessel: 'Test vessel', status: 'Expiring soon', url: 'http://localhost/vessel-certificates/1' }] };
get('[data-calendar-initial]').textContent = JSON.stringify(initial);
const root = new Node();
root.dataset = { endpoint: 'http://localhost/vessel-certificates/expiry-calendar', vessel: '1' };
root.querySelector = get;
const opener = new Node();
root.querySelectorAll = () => [opener];
const requests = [];
vm.runInNewContext(fs.readFileSync('resources/js/certificate-calendar.js', 'utf8'), {
    document: { querySelectorAll: () => [root], createElement: () => new Node() },
    window: { location: { href: 'http://localhost/vessel-certificates/1', origin: 'http://localhost' } },
    URL, AbortController, Date,
    fetch: async url => { requests.push(url); return { ok: true, json: async () => ({ ...initial, month: url.searchParams.get('month'), title: 'Changed month', events: [], total: 0 }) }; },
});
const grid = get('[data-calendar-grid]');
assert.equal(grid.children.filter(node => node.type === 'button').length, 29);
const leap = grid.children.find(node => node.dataset.date === '2028-02-29');
assert.match(leap.className, /has-expiry/);
leap.click();
assert.equal(get('[data-calendar-events]').children[0].children[1].textContent, '<script>unsafe</script>');
opener.click();
assert.equal(get('dialog').open, true);
get('[data-calendar-close]').click();
assert.equal(get('dialog').open, false);
get('[data-calendar-next]').click();
await new Promise(resolve => setTimeout(resolve, 0));
assert.equal(requests[0].searchParams.get('month'), '2028-03');
assert.equal(requests[0].searchParams.get('vessel_id'), '1');
get('[data-calendar-previous]').click();
await new Promise(resolve => setTimeout(resolve, 0));
assert.equal(requests[1].searchParams.get('month'), '2028-02');
assert.equal(get('[data-calendar-events]').children[0].textContent, 'No certificate expiries on this date.');
console.log('Calendar UI passed: leap day, red expiry, safe text, modal, next/previous month, vessel filter, empty state.');

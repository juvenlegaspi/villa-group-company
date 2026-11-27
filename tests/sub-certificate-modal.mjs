import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = fs.readFileSync('resources/js/sub-certificate-modal.js', 'utf8');

async function editWith(response) {
    const controls = Object.fromEntries(['document', 'remarks', 'certificate_name', 'issue_date', 'expiry_date'].map(name => [name, { value: '', files: [] }]));
    controls.namedItem = name => controls[name];
    const errors = { hidden: true, messages: [], replaceChildren() { this.messages = []; }, append(line) { this.messages.push(line.textContent); } };
    const save = { disabled: false };
    const handlers = {};
    const form = { elements: controls, reset() {}, reportValidity: () => true, querySelector: () => save, addEventListener: (name, callback) => { handlers[name] = callback; } };
    const parts = {
        '[data-sub-certificate-form]': form,
        '[data-sub-certificate-errors]': errors,
        '[data-sub-certificate-parent]': {},
        '#sub-certificate-modal-title': {},
        '[data-sub-certificate-note]': {},
        '[data-sub-certificate-document]': {},
    };
    const dialog = { querySelector: selector => parts[selector], querySelectorAll: () => [], showModal() {}, addEventListener() {} };
    const button = { dataset: { mode: 'edit', storeUrl: '/certificate/2/update', loadUrl: '/certificate/2/edit', parentId: '1', parentName: 'Main' }, addEventListener: (name, callback) => { handlers.open = callback; } };
    const requests = [], navigations = [];
    const context = {
        document: { getElementById: () => dialog, querySelectorAll: () => [button], createElement: () => ({}) },
        window: { location: { href: 'http://villa.test/vessel-certificates/1', assign: url => navigations.push(url) } },
        URL,
        FormData: class { constructor(value) { this.form = value; } },
        fetch: async (url, options) => {
            requests.push({ url, options });
            return options?.method === 'POST' ? response : { ok: true, json: async () => ({ fields: { certificate_name: 'Annual audit', remarks: 'Original remarks', issue_date: '2026-01-01', expiry_date: '2027-01-01' } }) };
        },
    };
    vm.runInNewContext(source, context);
    await handlers.open();
    assert.equal(controls.document.required, false, 'Editing must allow keeping the existing document');
    assert.equal(controls.remarks.value, 'Original remarks');
    controls.remarks.value = 'Updated remarks';
    await handlers.submit({ preventDefault() {} });
    assert.equal(requests[1].url, '/certificate/2/update');
    assert.equal(requests[1].options.method, 'POST');
    assert.equal(requests[1].options.body.form.elements.remarks.value, 'Updated remarks');
    assert.equal(save.disabled, false);
    return { errors, navigations, controls };
}

const saved = await editWith({ ok: true, status: 200, json: async () => ({ message: 'Sub-certificate updated.' }) });
assert.equal(saved.navigations.length, 1);
assert.ok(saved.navigations[0].endsWith('?expand=1#certificate-1'));
for (const response of [
    { ok: true, status: 200, redirected: true, json: async () => ({}) },
    { ok: true, status: 200, json: async () => { throw new Error('HTML login page'); } },
    { ok: false, status: 422, json: async () => ({ errors: { expiry_date: ['Expiry must follow issue date.'] } }) },
]) {
    const failed = await editWith(response);
    assert.equal(failed.navigations.length, 0, 'An unconfirmed save must not reload as successful');
    assert.equal(failed.errors.hidden, false);
    assert.ok(failed.errors.messages.length);
    assert.equal(failed.controls.remarks.value, 'Updated remarks');
}
console.log('Sub-certificate popup: edit submission, optional attachment, confirmed save, redirects, HTML responses and validation errors passed.');

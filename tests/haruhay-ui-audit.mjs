import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import assert from 'node:assert/strict';

// Exercise the Blade scripts without requiring a browser or extra packages.
function script(file, values = {}) {
    const source = fs.readFileSync(file, 'utf8');
    let code = [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)].map(m => m[1]).join('\n');
    while (code.includes('@json(')) {
        const start = code.indexOf('@json(');
        let depth = 1, end = start + 6, quote = null;
        for (; end < code.length; end++) {
            const char = code[end];
            if (quote) { if (char === quote && code[end - 1] !== '\\') quote = null; continue; }
            if (char === '"' || char === "'") { quote = char; continue; }
            if (char === '(') depth++;
            if (char === ')' && --depth === 0) break;
        }
        assert.equal(depth, 0, 'Unclosed @json in ' + file);
        const expression = code.slice(start + 6, end).trim();
        code = code.slice(0, start) + JSON.stringify(values[expression] ?? null) + code.slice(end + 1);
    }
    return code.replace(/^\s*@(if|elseif|else|endif).*$/gm, '');
}

function field(value = '') {
    const listeners = new Map();
    return {
        value, validity: {valid: true}, style: {}, dataset: {}, selectionStart: value.length,
        addEventListener(name, callback) { const handlers = listeners.get(name) ?? []; handlers.push(callback); listeners.set(name, handlers); },
        emit(name, trusted = true) { for (const callback of listeners.get(name) ?? []) callback({type: name, isTrusted: trusted}); },
        dispatchEvent(event) { this.emit(event.type, false); },
        setCustomValidity(message) { this.error = message; }, setSelectionRange() {},
    };
}

const files = dir => fs.readdirSync(dir, {withFileTypes: true}).flatMap(entry => entry.isDirectory() ? files(path.join(dir, entry.name)) : [path.join(dir, entry.name)]);
let compiled = 0;
for (const file of files('resources/views/haruhay')) {
    const code = script(file);
    if (!code.trim()) continue;
    new Function(code);
    compiled++;
}

const names = {
    billing_starts_on: field('2028-02-29'), first_due_on: field('2028-02-29'), due_day: field('29'),
    billing_cycle: field('lease_anniversary'), deposit_required: field('3000'), advance_required: field('3000'),
};
const ids = {
    leaseStart: field('2028-01-31'), leaseDuration: field('1'), leaseEnd: field(), firstMonthPolicy: field('full_month'),
    firstMonthCharge: field(), firstChargeField: field(), contractRent: field('3000'), contractModal: field(),
};
vm.runInNewContext(script('resources/views/haruhay/contracts/modal.blade.php'), {
    document: {
        addEventListener: (_, callback) => callback(), getElementById: id => ids[id],
        querySelector: selector => names[selector.match(/name="([^"]+)"/)[1]],
        querySelectorAll: () => [ids.contractRent, names.deposit_required, names.advance_required, ids.firstMonthCharge],
    },
    Event: class { constructor(type) { this.type = type; } },
    window: {bootstrap: {Modal: {getOrCreateInstance: () => ({show() {}})}}},
});
assert.equal(ids.leaseEnd.value, '2028-02-28', 'Leap-year month end');
names.billing_starts_on.value = '2028-03-10'; names.billing_starts_on.emit('input');
assert.equal(names.first_due_on.value, '2028-03-10', 'Default first due follows billing start');
assert.equal(names.due_day.value, 10, 'Anniversary due day follows billing start');
assert.equal(names.due_day.readOnly, true);
names.first_due_on.value = '2028-04-01'; names.first_due_on.emit('input');
names.billing_starts_on.value = '2028-03-15'; names.billing_starts_on.emit('input');
assert.equal(names.first_due_on.value, '2028-04-01', 'Preserve custom first due');
names.billing_cycle.value = 'calendar_month'; names.billing_cycle.emit('change');
assert.equal(names.due_day.readOnly, false);
names.deposit_required.value = '500'; names.deposit_required.emit('input');
ids.contractRent.value = '4000'; ids.contractRent.emit('input');
assert.equal(names.deposit_required.value.replaceAll(',', ''), '500', 'Preserve edited deposit');
assert.equal(names.advance_required.value.replaceAll(',', ''), '4000', 'Default advance tracks agreed rent');

const receiptIds = Object.fromEntries(['receiptAmount', 'receiptBill', 'receiptMethod', 'receiptReference', 'receiptProofField', 'receiptProof', 'receiptApplied', 'receiptCredit', 'receiptType', 'receiptBillField', 'collectionModalTitle', 'receiptSave', 'receiptModeHint', 'collectionModal'].map(id => [id, field()]));
receiptIds.receiptAmount.value = '1000'; receiptIds.receiptBill.value = '1'; receiptIds.receiptMethod.value = 'Cash'; receiptIds.receiptType.value = 'advance';
const buttons = [field(), field()]; buttons[0].dataset.receiptMode = 'payment'; buttons[1].dataset.receiptMode = 'advance';
vm.runInNewContext(script('resources/views/haruhay/collections/modal.blade.php', {'$currentBill?->balance_cents ?? 0': 50000, "$current['period']['rent'] ?? 0": 3000}), {
    document: {addEventListener: (_, callback) => callback(), getElementById: id => receiptIds[id], querySelectorAll: () => buttons},
    window: {bootstrap: {Modal: {getOrCreateInstance: () => ({show() {}})}}},
});
assert.equal(receiptIds.receiptBill.disabled, true, 'Advance never submits invoice id');
buttons[0].emit('click');
assert.equal(receiptIds.receiptAmount.value, '500.00', 'Payment defaults to remaining balance');
assert.equal(receiptIds.receiptBill.required, true);
buttons[1].emit('click');
assert.equal(receiptIds.receiptAmount.value, '3,000.00', 'Advance defaults to rent');
receiptIds.receiptMethod.value = 'GCash'; receiptIds.receiptMethod.emit('change');
assert.equal(receiptIds.receiptProof.required, true, 'Online proof required');
receiptIds.receiptMethod.value = 'Cash'; receiptIds.receiptMethod.emit('change');
assert.equal(receiptIds.receiptProof.disabled, true, 'Cash does not submit stale online proof');

for (const data of [
    {trend: [], aging: {Current: 0}, units: {Occupied: 0, Vacant: 0, Reserved: 0}, outstanding: 0, totalUnits: 0},
    {trend: [{period: 'Nov 2026', billing: 350000, collections: 300000, rate: 85.71}], aging: {Current: 50000}, units: {Occupied: 1, Vacant: 1, Reserved: 0}, outstanding: 50000, totalUnits: 2},
]) {
    const charts = [];
    vm.runInNewContext(script('resources/views/haruhay/reports/index.blade.php', {'$chartData': data}), {
        document: {addEventListener: (_, callback) => callback(), getElementById: id => ({id, addEventListener() {}})},
        window: {Chart: class {constructor(element, config) {charts.push({element, config});} resize() {}}, addEventListener() {}, print() {}},
    });
    assert.equal(charts.length, 4);
    assert.equal(charts[0].config.type, 'line'); assert.equal(charts[3].config.type, 'doughnut');
}
for (const data of [
    {trend: [], aging: {Current: 0}, units: {Occupied: 0, Vacant: 0, Reserved: 0}, totalUnits: 0, occupancyRate: 0},
    {trend: [{period: 'Nov 2026', billing: 1000000, collections: 350000, rate: 30}], aging: {Current: 0, '1–30 Days': 700000}, units: {Occupied: 1, Vacant: 1, Reserved: 0}, totalUnits: 2, occupancyRate: 50},
]) {
    const charts = [];
    vm.runInNewContext(script('resources/views/dashboard/haruhay.blade.php', {'$chartData': data}), {
        document: {addEventListener: (_, callback) => callback(), getElementById: id => ({id})},
        window: {Chart: class {constructor(element, config) {charts.push({element, config});}}},
    });
    assert.equal(charts.length, 4, 'Executive dashboard builds all four graphs');
    assert.equal(charts[0].config.type, 'bar');
    assert.equal(charts[1].config.type, 'doughnut');
    assert.equal(charts[2].config.type, 'line');
    assert.equal(charts[3].config.options.indexAxis, 'y');
    if (data.totalUnits) {
        assert.equal(charts[0].config.data.datasets[0].data[0], 10000, 'Billing cents converted to pesos');
        assert.equal(charts[0].config.data.datasets[1].data[0], 3500, 'Collections counted once');
        assert.equal(charts[2].config.data.datasets[0].data[0], 30, 'Collection rate is invoice settlement, not cash divided by billing');
        assert.equal(charts[3].config.data.datasets[0].data[1], 7000);
    } else {
        assert.equal(charts[1].config.options.plugins.tooltip.enabled, false, 'Empty occupancy has no fabricated room tooltip');
    }
}
const chartFallback = {hidden: true};
vm.runInNewContext(script('resources/views/dashboard/haruhay.blade.php'), {
    document: {addEventListener: (_, callback) => callback(), getElementById: () => chartFallback}, window: {},
});
assert.equal(chartFallback.hidden, false, 'Dashboard explains missing chart assets');
process.stdout.write(`Haruhay UI audit passed: ${compiled} inline scripts compile; contract dates, editable amounts, payment proof rules, report charts and executive dashboard charts verified.\n`);

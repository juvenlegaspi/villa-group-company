import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

function select(value, options) {
    return {value, options, listeners: {}, get selectedOptions() {return this.options.filter(option => option.value === this.value);},
        addEventListener(event, callback) {this.listeners[event] = callback;}, dispatchEvent(event) {this.listeners[event.type]?.();}};
}
const option = (value, dataset) => ({value, dataset, hidden: false});
const division = select('1', [option('1', {name: 'haruhay'}), option('2', {name: 'villa shipping lines'})]);
const department = select('10', [option('10', {name: 'property management', division: '1'}), option('20', {name: 'marine operations', division: '2'})]);
const position = select('staff', ['staff', 'cashier', 'finance', 'manager'].map((key, index) => option(key, {division: '1', department: '10', code: ['haruhay-property-staff', 'haruhay-cashier', 'haruhay-finance-reviewer', 'haruhay-property-manager'][index]})));
const role = select('standard', [option('standard', {slug: 'standard-user'}), option('admin', {slug: 'system-administrator'})]);
const checkboxes = [{disabled: false}, {disabled: false}];
const assignments = {hidden: true, classList: {toggle(name, value) {assignments.hidden = value;}}, querySelectorAll() {return checkboxes;}};
const elements = {divisionSelect: division, departmentSelect: department, positionSelect: position, accessRoleSelect: role, accessRoleHelp: {}, haruhayPropertyAssignmentFields: assignments};
const blade = fs.readFileSync('resources/views/users/partials/assignment-script.blade.php', 'utf8');
const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1].replace('@json($approvalModulesByDivision)', '{}');
vm.runInNewContext(script, {document: {getElementById: id => elements[id] ?? null, addEventListener(event, callback) {callback();}}, Event: class {constructor(type) {this.type = type;}}});
for (const code of ['staff', 'cashier', 'finance']) {
    position.value = code; position.listeners.change();
    assert.equal(assignments.hidden, false, code + ' must show property assignment choices');
    assert.equal(checkboxes.every(input => !input.disabled), true);
}
position.value = 'manager'; position.listeners.change();
assert.equal(assignments.hidden, true, 'Property managers have all-location access');
assert.equal(checkboxes.every(input => input.disabled), true);
position.value = 'staff'; position.listeners.change();
role.value = 'admin'; role.listeners.change();
assert.equal(assignments.hidden, true, 'System admins do not need property assignments');
role.value = 'standard'; role.listeners.change();
assert.equal(assignments.hidden, false);
division.value = '2'; division.listeners.change();
assert.equal(assignments.hidden, true, 'Other companies must not submit Haruhay assignments');
assert.equal(checkboxes.every(input => input.disabled), true);
console.log('Haruhay user assignment UI passed: staff, cashier, finance, manager, admin and company switching.');

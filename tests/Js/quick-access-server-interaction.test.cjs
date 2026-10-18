const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '../..');
const code = fs.readFileSync(path.join(root, 'public/js/quick-access.js'), 'utf8');
const blade = fs.readFileSync(path.join(root, 'resources/views/partials/public-quick-access.blade.php'), 'utf8');

function fixture() {
    const handlers = {};
    const attrs = {};
    const classes = new Set();
    const panel = { open: false, matches: selector => selector === '[data-quick-access-server-panel]' };
    const trigger = {
        closest(selector) {
            if (selector === '[data-quick-access-server-trigger]') return this;
            if (selector === '[data-quick-access]') return bar;
            return null;
        },
        setAttribute(k, v) { attrs[k] = v; },
    };
    const outside = { closest: () => null };
    const bar = {
        querySelector(selector) {
            if (selector === '[data-quick-access-server-panel]') return panel;
            if (selector === '[data-quick-access-server-trigger]') return trigger;
            return null;
        },
        contains(el) { return el === trigger || el === panel; },
        classList: {
            toggle(k, value) {
                if (value) classes.add(k); else classes.delete(k);
                return classes.has(k);
            },
            contains(k) { return classes.has(k); },
        },
    };
    class Element {}
    Object.setPrototypeOf(trigger, Element.prototype);
    Object.setPrototypeOf(panel, Element.prototype);
    Object.setPrototypeOf(outside, Element.prototype);
    const doc = {
        addEventListener(type, fn) { handlers[type] = fn; },
        querySelectorAll() { return [bar]; },
    };
    vm.runInNewContext(code, { document: doc, window: {}, Element });
    return { handlers, trigger, panel, bar, attrs, classes, outside };
}

test('server icon opens and closes independent panel, updates aria and state', () => {
    const f = fixture();
    f.handlers.click({ target: f.trigger, preventDefault() {} });
    assert.equal(f.panel.open, true);
    assert.equal(f.attrs['aria-expanded'], 'true');
    assert.equal(f.classes.has('has-open-server'), true);
    f.handlers.click({ target: f.trigger, preventDefault() {} });
    assert.equal(f.panel.open, false);
    assert.equal(f.attrs['aria-expanded'], 'false');
});

test('outside click or Escape closes panel', () => {
    const f = fixture();
    f.handlers.click({ target: f.trigger, preventDefault() {} });
    f.handlers.click({ target: f.outside });
    assert.equal(f.panel.open, false);
    f.handlers.click({ target: f.trigger, preventDefault() {} });
    f.handlers.keydown({ key: 'Escape' });
    assert.equal(f.panel.open, false);
});

test('Blade renders shared infrastructure widget and loads versioned standalone JS', () => {
    assert.match(blade, /data-quick-access-server-trigger/);
    assert.match(blade, /data-quick-access-server-panel/);
    assert.match(blade, /data-infrastructure-status/);
    assert.match(blade, /js\/quick-access\.js/);
    assert.match(blade, /js\/infrastructure-status\.js/);
});

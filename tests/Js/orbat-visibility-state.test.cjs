const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(
    path.resolve(__dirname, '../../public/js/filament-orbat-layout.js'),
    'utf8',
);

function setup() {
    const listeners = {};
    const hooks = {};
    const classes = new Set();
    const toggle = {
        attrs: {},
        setAttribute(name, value) { this.attrs[name] = value; },
        querySelector() { return { textContent: '' }; },
    };
    const section = {
        dataset: { orbatEventId: '27', orbatGroupIndex: '8' },
        classList: {
            toggle(name, forced) {
                if (forced) classes.add(name); else classes.delete(name);
            },
            contains(name) { return classes.has(name); },
        },
        querySelector(selector) {
            return selector === '[data-orbat-toggle-section]' ? toggle : null;
        },
        matches(selector) { return selector === '.event-orbat-visibility-section'; },
        closest() { return this; },
    };
    const modal = {
        querySelectorAll() { return [section]; },
    };
    const document = {
        body: {},
        readyState: 'loading',
        addEventListener(name, callback) { listeners[name] = callback; },
        querySelectorAll(selector) {
            return selector === '.fi-modal-window .event-orbat-visibility-section' ? [section] : [];
        },
    };
    const window = {
        innerWidth: 1280,
        scrollX: 0,
        scrollY: 0,
        requestAnimationFrame(callback) { callback(); return 1; },
        addEventListener() {},
        getComputedStyle(node) { return { overflowY: node.overflowY || 'visible' }; },
        Livewire: { hook(name, callback) { hooks[name] = callback; } },
        scrollTo(x, y) { this.scrollX = x; this.scrollY = y; },
    };
    vm.runInNewContext(script, {
        window, document,
        MutationObserver: class { observe() {} },
        ResizeObserver: class { observe() {} },
        Set, WeakMap, WeakSet,
    });
    return { listeners, hooks, classes, modal, section, document, window };
}

test('un plegado masivo persiste al reconstruirse la sección de Livewire', () => {
    const env = setup();
    const button = {
        dataset: { orbatToggleAll: 'collapse' },
        closest(selector) {
            if (selector === '[data-orbat-toggle-all]') return this;
            if (selector === '.fi-modal-window') return env.modal;
            return null;
        },
    };
    env.listeners.click({ target: button, preventDefault() {}, stopPropagation() {} });
    assert.ok(env.classes.has('event-orbat-visibility-section--collapsed'));

    // Una actualización del toggle quita el CSS de plegado al reconstruir el DOM.
    env.classes.delete('event-orbat-visibility-section--collapsed');
    env.hooks['morph.updated']({ el: env.section });
    assert.ok(env.classes.has('event-orbat-visibility-section--collapsed'));
});

test('el scroll del modal se conserva al actualizar un toggle', () => {
    const env = setup();
    const scroller = {
        parentElement: env.document.body,
        isConnected: true,
        overflowY: 'auto',
        scrollHeight: 1900,
        clientHeight: 400,
        scrollTop: 715,
        scrollLeft: 0,
    };
    const control = {
        parentElement: scroller,
        closest(selector) {
            if (selector === '.event-orbat-visibility-control') return this;
            if (selector === '.event-orbat-visibility-section') return env.section;
            if (selector === '.fi-modal-window') return env.modal;
            return null;
        },
    };
    env.listeners.pointerdown({ target: control });
    scroller.scrollTop = 0; // Simulación del salto tras el morph.
    env.hooks.morphed({ el: env.section });
    assert.equal(scroller.scrollTop, 715);
});

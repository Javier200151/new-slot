const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '../..');
function loadEditor(relativeFile, factory) {
    const blade = fs.readFileSync(path.join(root, relativeFile), 'utf8');
    let code = blade.split('<script>')[1].split('</script>')[0];
    code = code.replace("@js(asset('images/quick-access-icons.svg'))", "'/images/quick-access-icons.svg'");
    const context = { window: {}, console };
    vm.runInNewContext(code, context);
    return context.window[factory];
}

test('quick-access editor adds destinations independently and supports icon choice', () => {
    const create = loadEditor('resources/views/filament/pages/edit-quick-access.blade.php', 'sqaQuickAccessEditor');
    const state = create([], [{type:'link', destination:'users', label:'Usuarios'}], ['users','user-group','link'], {'__guest__':'Invitado'}, 'right', true);
    state.add({type:'link', destination:'users', label:'Usuarios'});
    state.add({type:'link', destination:'users', label:'Usuarios'});
    assert.equal(state.menu.length, 2);
    assert.equal(state.menu[0].icon, 'users');
    state.pickIcon(0, 'user-group');
    assert.equal(state.menu[0].icon, 'user-group');
    state.move(0, 1);
    assert.equal(state.menu[1].icon, 'user-group');
    state.remove(0);
    assert.equal(state.menu.length, 1);
});

test('quick-access icon search filters entire supplied catalog', () => {
    const create = loadEditor('resources/views/filament/pages/edit-quick-access.blade.php', 'sqaQuickAccessEditor');
    const state = create([], [], ['users','calendar-days','calendar-date-range','link'], {}, 'right', true);
    assert.equal(state.matchingIcons().length, 4);
    state.iconSearch = 'calendar';
    assert.equal(state.matchingIcons().length, 2);
});

test('footer builder keeps styles and reordering features', () => {
    const create = loadEditor('resources/views/filament/pages/edit-public-footer.blade.php', 'footerNavigationEditor');
    const state = create([], [{type:'link', destination:'users', label:'Usuarios'}, {type:'server_status',label:'Estado'}], {'__guest__':'Invitado', 'ACTIVO':'Activo'});
    state.add(state.catalog[0]);
    state.add(state.catalog[1]);
    state.add(state.catalog[1]);
    assert.equal(state.menu.length, 2);
    state.move(0, 1);
    assert.equal(state.menu[0].type, 'server_status');
    assert.equal(state.dirty, true);
    const blade = fs.readFileSync(path.join(root,'resources/views/filament/pages/edit-public-footer.blade.php'),'utf8');
    assert.ok(blade.includes("sqa-navigation-builder-styles"));
});


test('server status is a single exceptional dropdown in quick access and footer catalogs', () => {
    const create = loadEditor('resources/views/filament/pages/edit-quick-access.blade.php', 'sqaQuickAccessEditor');
    const state = create([], [{type:'server_status',label:'Estado de servidores'}], ['server-stack','users'], {'__guest__':'Invitado','ACTIVO':'Activo','RECLUTA':'Recluta'}, 'right', true);
    state.add(state.catalog[0]);
    assert.equal(state.menu.length, 1);
    assert.equal(state.menu[0].type, 'server_status');
    assert.equal(state.menu[0].icon, 'server-stack');
    assert.deepEqual(Array.from(state.menu[0].visible_to), ['ACTIVO','RECLUTA']);
    state.add(state.catalog[0]);
    assert.equal(state.menu.length, 1);
    assert.match(state.notice, /ya está colocado/);
    const quickView = fs.readFileSync(path.join(root,'resources/views/partials/public-quick-access.blade.php'),'utf8');
    assert.ok(quickView.includes('data-infrastructure-status'));
    assert.ok(quickView.includes('data-quick-access-server-trigger'));
});

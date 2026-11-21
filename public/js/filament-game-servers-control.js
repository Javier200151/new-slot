/* SQA Mission Control: browser talks ONLY to authenticated Filament Livewire methods. */
(function () {
  'use strict';
  const IDS = ['academia', 'academia_vietnam', 'academia_ww2'];
  const GROUPS = [
    { id: 'today', title: 'Hoy' },
    { id: 'upcoming', title: 'Próximos eventos' },
    { id: 'past', title: 'Eventos pasados' },
    { id: 'campaign', title: 'Campañas sin evento en estos días' },
    { id: 'folder', title: 'Otras misiones en la carpeta de la web' }
  ];
  function defaultServer(id) {
    return { id, state: 'sin_conexion', controlable: false, players: 0, max: 0,
      hc: 0, expectedHc: 0, uptime: '—', sinceText: 'Sin conexión',
      name: id === 'operativos' ? 'Arma 3 Operativos' : ({academia:'Arma 3 Academia', academia_vietnam:'Academia Vietnam', academia_ww2:'Academia WW2'})[id],
      port: ({operativos:2302,academia:2402,academia_vietnam:2502,academia_ww2:2602})[id],
      mission: '—', auto: false, time: '06:00', nextRestart: '', lastError: '' };
  }
  function uptime(iso) {
    if (!iso) return '—';
    const ms = Date.now() - Date.parse(iso);
    if (!Number.isFinite(ms) || ms < 0) return '—';
    const seconds = Math.floor(ms / 1000);
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    return days ? `${days} d ${hours} h ${mins} min` : `${hours} h ${mins} min`;
  }
  function normalizeServer(row) {
    const base = defaultServer(row.id);
    const daily = row.reinicio_diario || {};
    return {
      ...base,
      name: row.nombre || base.name,
      port: row.puerto_juego || base.port,
      state: row.estado || 'sin_conexion',
      controlable: !!row.controlable,
      uptime: uptime(row.desde),
      players: row.jugadores?.actuales ?? 0,
      max: row.jugadores?.max ?? 0,
      hc: row.headless?.conectados ?? 0,
      expectedHc: row.headless?.esperados ?? 0,
      mission: row.mision?.nombre || 'Ninguna',
      pbo: row.mision?.pbo || '',
      launchedBy: row.lanzado_por?.nombre || '',
      auto: !!daily.activo,
      time: daily.hora || '06:00',
      nextRestart: daily.proximo || '',
      lastError: row.ultimo_error || '',
      sinceText: row.estado === 'externo' ? 'Iniciado desde otra herramienta' : '',
    };
  }
  window.sqaMissionControl = function () {
    return {
      init() {
        this.$watch('activitySearch', () => { this.activeActivityIndex = 0; });
        this.refresh(true);
        // Read-only status refresh: no action is issued when the user is idle.
        this.pollTimer = setInterval(() => this.refresh(false), 10000);
      },
      destroy() { if (this.pollTimer) clearInterval(this.pollTimer); },
      pollTimer: null,
      loading: true,
      updating: false,
      ready: false,
      connected: false,
      readOnly: true,
      canLaunch: false,
      canStop: false,
      canConfigure: false,
      mode: '',
      checkedAt: '',
      error: '',
      feedback: '',
      servers: {},
      activities: [],
      selectedId: '',
      activitySearch: '',
      activityPickerOpen: false,
      activeActivityIndex: 0,
      stopAcademies: false,
      confirmation: null,
      log: [],
      get academies() { return IDS.map(id => this.servers[id] || defaultServer(id)); },
      get operation() { return this.servers.operativos || defaultServer('operativos'); },
      get activeActivityName() { return this.operation.mission || ''; },
      get activePbo() { return this.operation.pbo || ''; },
      get selectedActivity() { return this.activities.find(a => a.id === this.selectedId) || { id: '', name: 'Selecciona un evento', event: '', fileLabel: '', pbo: null, html: null, map: '—', slots: '—', profile: '—', addons: 0, sync: 'missing', pboReady: false, htmlReady: false, launchable: false, reason: 'No hay una actividad seleccionada.' }; },
      get canChangeServer() { return this.connected && !this.readOnly && !this.updating; },
      get canLaunchSelected() {
        return this.canChangeServer && this.canLaunch && this.operation.controlable &&
          this.operation.state === 'detenido' && !!this.selectedActivity?.launchable;
      },
      get selectableActivityGroups() {
        return GROUPS.map(group => ({ ...group, items: this.activities.filter(a => a.group === group.id) }))
          .filter(group => group.items.length > 0);
      },
      get filteredActivityGroups() {
        const words = this.foldActivityText(this.activitySearch).split(/\s+/).filter(Boolean);
        return this.selectableActivityGroups.map(group => ({ ...group,
          items: group.items.filter(a => words.every(word => this.foldActivityText(
            [group.title, a.name, a.whenLabel, a.event, a.fileLabel, a.pbo, a.map, a.mode].filter(Boolean).join(' ')
          ).includes(word)))
        })).filter(group => group.items.length > 0);
      },
      get filteredActivities() { return this.filteredActivityGroups.flatMap(g => g.items); },
      get selectedSyncTone() { return this.syncTone(this.selectedActivity?.sync); },
      async refresh(force = false) {
        if ((this.updating && !force) || (this.loading && this.ready)) return;
        this.loading = true;
        try {
          const data = await this.$wire.loadPanel(force);
          this.connected = !!data.connected;
          this.readOnly = !!data.readOnly;
          this.canLaunch = !!data.canLaunch;
          this.canStop = !!data.canStop;
          this.canConfigure = !!data.canConfigure;
          this.error = data.error || '';
          this.mode = data.mode || '';
          this.checkedAt = data.checkedAt || '';
          this.servers = Object.fromEntries((data.servers || []).map(row => [row.id, normalizeServer(row)]));
          this.activities = Array.isArray(data.activities) ? data.activities : [];
          this.log = Array.isArray(data.log) ? data.log : [];
          if (!this.activities.find(a => a.id === this.selectedId)) {
            this.selectedId = this.activities.find(a => a.launchable)?.id || this.activities[0]?.id || '';
          }
        } catch (e) {
          this.connected = false;
          this.readOnly = true;
          this.servers = {};
          this.error = 'No se ha podido obtener el estado de Mission Control.';
        } finally { this.ready = true; this.loading = false; }
      },
      async reloadActivities() {
        await this.refresh(true);
        this.feedback = this.error || 'Listado actualizado.';
      },
      foldActivityText(s) { return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es'); },
      toggleActivityPicker() { this.activityPickerOpen ? this.closeActivityPicker() : this.openActivityPicker(); },
      openActivityPicker() {
        if (this.operation.state !== 'detenido') return;
        this.activityPickerOpen = true;
        this.activitySearch = '';
        this.activeActivityIndex = Math.max(0, this.filteredActivities.findIndex(item => item.id === this.selectedId));
        this.$nextTick(() => this.$refs.activitySearchInput?.focus());
      },
      closeActivityPicker() { this.activityPickerOpen = false; this.activitySearch = ''; },
      activityIndex(id) { return this.filteredActivities.findIndex(item => item.id === id); },
      moveActivitySelection(direction) {
        const options = this.filteredActivities;
        if (!options.length) return;
        this.activeActivityIndex = (this.activeActivityIndex + direction + options.length) % options.length;
        this.$nextTick(() => this.$root.querySelectorAll('.sqa-server-panel__picker-option')[this.activeActivityIndex]?.scrollIntoView({block:'nearest'}));
      },
      chooseActiveActivity() { const option = this.filteredActivities[this.activeActivityIndex] || this.filteredActivities[0]; if (option) this.chooseActivity(option); },
      chooseActivity(activity) { if (this.operation.state !== 'detenido') return; this.selectedId = activity.id; this.closeActivityPicker(); this.$nextTick(() => this.$root.querySelector('#sqa-demo-activity')?.focus()); },
      statusClass(s) { return ({en_marcha:'sqa-server-panel__status--running', detenido:'sqa-server-panel__status--stopped', arrancando:'sqa-server-panel__status--starting', parando:'sqa-server-panel__status--starting', caido:'sqa-server-panel__status--stopped', externo:'sqa-server-panel__status--starting'})[s] || 'sqa-server-panel__status--stopped'; },
      statusLabel(s) { return ({en_marcha:'En marcha', detenido:'Detenido', arrancando:'Arrancando', parando:'Parando', caido:'Caído', externo:'Externo', sin_conexion:'Sin conexión'})[s] || 'Desconocido'; },
      syncTone(s) { return s === 'ok' ? 'ok' : s === 'partial' ? 'warning' : 'danger'; },
      syncLabel(s) { return s === 'ok' ? 'PBO y HTML listos' : s === 'partial' ? 'Solo uno de los archivos listo' : 'Sin archivos preparados'; },
      syncHint(a) { return a?.reason || (a?.sync === 'ok' ? 'PBO y HTML disponibles en Windows.' : 'Hay archivos pendientes de sincronizar.'); },
      command(kind, id, action) {
        if (!this.canChangeServer) return;
        const item = kind === 'academia' ? this.academies.find(a => a.id === id) : this.operation;
        if (!item || !item.controlable) return;
        if (action === 'lanzar' && !this.canLaunchSelected) return;
        if (['arrancar', 'lanzar'].includes(action) && !this.canLaunch) return;
        if (['parar', 'reiniciar'].includes(action) && !this.canStop) return;
        const actionLabel = {lanzar:'Lanzar', arrancar:'Arrancar', parar:'Parar', reiniciar:'Reiniciar'}[action];
        this.confirmation = {
          id, action, title: actionLabel + ' servidor',
          message: '¿' + actionLabel + ' ' + (action === 'lanzar' ? this.selectedActivity.name : item.name) + '?'
            + (['parar','reiniciar'].includes(action) && item.players > 0 ? ' Hay ' + item.players + ' jugadores conectados.' : '')
            + (action === 'lanzar' && this.stopAcademies ? ' También se pararán las academias activas.' : ''),
          accept: actionLabel, danger: ['parar','reiniciar'].includes(action), force: false,
        };
      },
      closeConfirm() { if (!this.updating) this.confirmation = null; },
      async acceptConfirm() {
        const confirm = this.confirmation;
        if (!confirm || this.updating) return;
        this.updating = true;
        this.feedback = '';
        try {
          const result = await this.$wire.controlServer(confirm.id, confirm.action, this.selectedId, this.stopAcademies, confirm.force);
          if (result.requiresForce) {
            this.confirmation = { ...confirm, force: true, title: 'Confirmar parada forzada', message: result.message, accept: 'Sí, forzar', danger: true };
            return;
          }
          this.confirmation = null;
          this.feedback = result.message || (result.ok ? 'Orden aceptada.' : 'No se ha podido ejecutar la acción.');
          await this.refresh(true);
        } catch (e) {
          this.confirmation = null;
          this.feedback = 'No se ha podido comunicar con el servidor. La acción no está confirmada.';
        } finally { this.updating = false; }
      },
      async saveRestart(academy) {
        if (!this.canChangeServer || !this.canConfigure || !academy.controlable) return;
        this.updating = true;
        this.feedback = '';
        try {
          const result = await this.$wire.saveDailyRestart(academy.id, academy.auto, academy.time);
          this.feedback = result.message || '';
          await this.refresh(true);
        } catch (e) {
          this.feedback = 'No se ha guardado el reinicio diario.';
          await this.refresh(true);
        } finally { this.updating = false; }
      },
    };
  };
})();

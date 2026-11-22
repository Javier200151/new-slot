/* Squad ALPHA · SQA Mission Control. All requests go through authenticated Livewire. */
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
    return { id, state:'sin_conexion', controlable:false, players:null, max:null,
      hc:null, expectedHc:null, startedAt:null, sinceText:'',
      name:id === 'operativos' ? 'Arma 3 Operativos' : ({academia:'Arma 3 Academia', academia_vietnam:'Academia Vietnam', academia_ww2:'Academia WW2'})[id],
      port:({operativos:2302,academia:2402,academia_vietnam:2502,academia_ww2:2602})[id],
      mission:'', pbo:'', launchedBy:'', auto:false, time:'06:00', nextRestart:'', lastError:'', hasDaily:false };
  }
  function normalizeServer(row) {
    const base = defaultServer(row.id);
    const daily = row.reinicio_diario || {};
    return {
      ...base,
      name:row.nombre || base.name,
      port:row.puerto_juego || base.port,
      state:row.estado || 'sin_conexion',
      controlable:row.controlable === true,
      startedAt:row.desde || null,
      players:row.jugadores?.actuales ?? null,
      max:row.jugadores?.max ?? null,
      hc:row.headless?.conectados ?? null,
      expectedHc:row.headless?.esperados ?? null,
      mission:row.mision?.nombre || row.mision?.pbo || '',
      pbo:row.mision?.pbo || '',
      launchedBy:row.lanzado_por?.nombre || '',
      auto:!!daily.activo,
      time:daily.hora || '06:00',
      nextRestart:daily.proximo || '',
      hasDaily:!!row.reinicio_diario,
      lastError:row.ultimo_error || '',
      sinceText:row.estado === 'externo' ? 'Iniciado desde otra herramienta' : ''
    };
  }
  function str(value) { return value == null || value === '' ? '—' : String(value); }
  function validStatusAction(id, state, action) {
    const operation = id === 'operativos';
    if (action === 'lanzar') return operation && ['detenido','caido'].includes(state);
    if (action === 'arrancar') return !operation && ['detenido','caido'].includes(state);
    if (action === 'parar') return operation ? ['en_marcha','arrancando','caido'].includes(state) : state === 'en_marcha';
    if (action === 'reiniciar') return ['en_marcha','caido'].includes(state) && (operation || state === 'en_marcha');
    return false;
  }
  function plural(n, singular, plural) { return n === 1 ? singular : plural; }

  window.sqaMissionControl = function () {
    return {
      init() {
        this.$watch('activitySearch', () => { this.activeActivityIndex = 0; });
        this.refresh(true);
        // Server reads only; no start/stop/sync job is run by these polls.
        this.pollTimer = window.setInterval(() => this.refresh(false), 5000);
        this.clockTimer = window.setInterval(() => { this.nowMs = Date.now(); }, 1000);
      },
      destroy() {
        if (this.pollTimer) window.clearInterval(this.pollTimer);
        if (this.clockTimer) window.clearInterval(this.clockTimer);
        this.toasts.forEach(item => window.clearTimeout(item.timer));
      },
      pollTimer:null, clockTimer:null, nowMs:Date.now(),
      loading:false, ready:false, connected:false, readOnly:true, stale:false,
      canLaunch:false, canStop:false, canConfigure:false,
      mode:'', version:'', actorName:'', checkedAt:'', error:'', catalogError:'',
      servers:{}, activities:[], selectedId:'',
      activitySearch:'', activityPickerOpen:false, activeActivityIndex:0,
      stopAcademies:false, showAddons:false, confirmation:null, log:[],
      busyServer:{}, toasts:[], toastSeq:0,
      get academies() { return IDS.map(id => this.servers[id] || defaultServer(id)); },
      get operation() { return this.servers.operativos || defaultServer('operativos'); },
      get activeActivityName() { return this.operation.mission || ''; },
      get activePbo() { return this.operation.pbo || ''; },
      get selectedActivity() { return this.activities.find(a => a.id === this.selectedId) || null; },
      get displayActivity() { return this.selectedActivity || { name:'—', event:'',pbo:null,html:null,map:'—',slots:'—',profile:'—',addons:0,addonNames:[],missingAddons:[],serverMods:[],mode:'',sync:'missing',pboReady:false,htmlReady:false,launchable:false,reason:'Elige una misión para consultar sus datos.',saveInfo:null,webLink:null }; },
      get hasSelected() { return !!this.selectedActivity; },
      get addonNames() { return Array.isArray(this.displayActivity.addonNames) ? this.displayActivity.addonNames : []; },
      get missingAddons() { return Array.isArray(this.displayActivity.missingAddons) ? this.displayActivity.missingAddons : []; },
      get serverMods() { return Array.isArray(this.displayActivity.serverMods) ? this.displayActivity.serverMods : []; },
      get addonsAvailable() { return this.addonNames.length > 0 && this.missingAddons.length === 0; },
      get addonStatusText() {
        if (!this.hasSelected || this.addonNames.length === 0) return 'Sin addons configurados';
        if (this.missingAddons.length) return `Faltan ${this.missingAddons.length} addons en el servidor`;
        return `${this.addonNames.length} addons, todos en el servidor`;
      },
      get addonStatusDescription() {
        return this.missingAddons.length
          ? this.missingAddons.join(', ')
          : (this.addonNames.length ? 'Comprobado con el catálogo de mods del servidor' : 'No hay listado de addons para comprobar');
      },
      get canChangeServer() { return this.connected && !this.readOnly; },
      get canLaunchSelected() { return this.allowAction(this.operation,'lanzar') && !!this.selectedActivity?.launchable; },
      get selectableActivityGroups() {
        return GROUPS.map(group => ({ ...group, items:this.activities.filter(a => a.group === group.id) })).filter(group => group.items.length);
      },
      get filteredActivityGroups() {
        const words = this.foldActivityText(this.activitySearch).split(/\s+/).filter(Boolean);
        return this.selectableActivityGroups.map(group => ({ ...group,
          items:group.items.filter(a => words.every(word => this.foldActivityText(
            [group.title,a.name,a.whenLabel,a.event,a.fileLabel,a.pbo,a.map,a.mode].filter(Boolean).join(' ')
          ).includes(word)))
        })).filter(group => group.items.length);
      },
      get filteredActivities() { return this.filteredActivityGroups.flatMap(g => g.items); },
      get selectedSyncTone() { return this.syncTone(this.selectedActivity?.sync); },
      isBusy(id) { return !!this.busyServer[id]; },
      allowAction(server, action) {
        return !!server && this.canChangeServer && server.controlable === true && !this.isBusy(server.id)
          && (['arrancar','lanzar'].includes(action) ? this.canLaunch : this.canStop)
          && validStatusAction(server.id,server.state,action);
      },
      allowRestartConfig(server) { return !!server && this.canChangeServer && this.canConfigure && server.controlable && !this.isBusy(server.id) && server.hasDaily; },
      onlineUptime(server) {
        this.nowMs; // Alpine reactive clock every second
        if (!server || !server.startedAt || !['arrancando','en_marcha','parando','externo'].includes(server.state)) return '—';
        const diff = this.nowMs - Date.parse(server.startedAt);
        if (!Number.isFinite(diff) || diff < 0) return '—';
        const sec = Math.floor(diff/1000), days = Math.floor(sec/86400), hours = Math.floor(sec%86400/3600), min = Math.floor(sec%3600/60);
        if (days) return `${days} d ${hours} h ${min} min`;
        if (hours) return `${hours} h ${min} min`;
        if (min) return `${min} min`;
        return `${sec} s`;
      },
      number(value) { return value == null ? '—' : String(value); },
      playerLine(server) { return this.number(server.players) + ' / ' + this.number(server.max); },
      hcLine(server) { return this.number(server.hc) + ' / ' + this.number(server.expectedHc); },
      sinceLabel(server) {
        if (server.state === 'externo') return 'Iniciado desde FASTER';
        if (server.state === 'detenido') return 'Sin evento en curso';
        if (server.startedAt && ['arrancando','en_marcha','parando'].includes(server.state)) return 'Desde ' + this.formatDate(server.startedAt);
        return server.state === 'caido' ? 'El servidor se ha detenido inesperadamente' : '';
      },
      formatDate(iso) {
        const d = new Date(iso);
        if (!Number.isFinite(d.getTime())) return '—';
        const tz = 'Europe/Madrid';
        const now = new Date();
        const day = date => new Intl.DateTimeFormat('en-CA',{timeZone:tz,year:'numeric',month:'2-digit',day:'2-digit'}).format(date);
        const hour = new Intl.DateTimeFormat('es-ES',{timeZone:tz,hour:'2-digit',minute:'2-digit',hour12:false}).format(d);
        if (day(d) === day(now)) return 'hoy ' + hour;
        if (day(d) === day(new Date(now.getTime()-86400000))) return 'ayer ' + hour;
        return new Intl.DateTimeFormat('es-ES',{timeZone:tz,weekday:'short',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).format(d).replace(',','');
      },
      restartLine(academy) {
        if (!academy.auto) return 'Sin reinicio automático.';
        const date = academy.nextRestart ? this.formatDate(academy.nextRestart) : 'a las ' + academy.time;
        return 'Próximo reinicio: ' + date + '. Si hay jugadores, se retrasa hasta una hora.';
      },
      notice(message,type='info') {
        if (!message) return;
        const id = ++this.toastSeq;
        const item = { id, message:String(message), type, timer:null };
        this.toasts.push(item);
        item.timer = window.setTimeout(() => this.dismissNotice(id), type === 'error' ? 12000 : 6000);
      },
      dismissNotice(id) {
        const item = this.toasts.find(n => n.id === id);
        if (item?.timer) window.clearTimeout(item.timer);
        this.toasts = this.toasts.filter(n => n.id !== id);
      },
      async refresh(force=false) {
        if (this.loading) return;
        this.loading = true;
        try {
          const data = await this.$wire.loadPanel(force);
          const online = !!data.connected;
          this.connected = online;
          this.stale = !online;
          this.readOnly = !!data.readOnly;
          this.canLaunch = !!data.canLaunch;
          this.canStop = !!data.canStop;
          this.canConfigure = !!data.canConfigure;
          this.actorName = data.actorName || this.actorName;
          this.error = data.error || '';
          this.catalogError = data.catalogError || '';
          this.mode = data.mode || this.mode;
          this.version = data.version || this.version;
          if (online) {
            this.checkedAt = data.checkedAt || this.checkedAt;
            const rows = Array.isArray(data.servers) ? data.servers : [];
            if (rows.length) this.servers = Object.fromEntries(rows.map(row => [row.id,normalizeServer(row)]));
            if (!this.catalogError && Array.isArray(data.activities)) this.activities = data.activities;
            if (!this.selectedId) {
              const today = this.activities.filter(a => a.group === 'today' && a.launchable);
              this.selectedId = today.length === 1 ? today[0].id : '';
            } else if (!this.activities.some(a => a.id === this.selectedId)) this.selectedId = '';
          }
          if (Array.isArray(data.log)) this.log = data.log;
        } catch (_) {
          this.connected = false; this.readOnly = true; this.stale = true;
          this.error = 'Sin conexión con Mission Control. Los datos pueden estar desactualizados.';
        } finally { this.ready = true; this.loading = false; }
      },
      async reloadActivities() {
        await this.refresh(true);
        this.notice(this.error || this.catalogError || `Lista de misiones actualizada (${this.activities.length}).`, this.error || this.catalogError ? 'error' : 'ok');
      },
      foldActivityText(s) { return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es'); },
      toggleActivityPicker() { this.activityPickerOpen ? this.closeActivityPicker() : this.openActivityPicker(); },
      canPick() { return ['detenido','caido'].includes(this.operation.state) && !this.isBusy('operativos'); },
      openActivityPicker() {
        if (!this.canPick()) return;
        this.activityPickerOpen = true; this.activitySearch = '';
        this.activeActivityIndex = Math.max(0,this.filteredActivities.findIndex(a => a.id === this.selectedId));
        this.$nextTick(() => this.$refs.activitySearchInput?.focus());
      },
      closeActivityPicker() { this.activityPickerOpen = false; this.activitySearch = ''; },
      activityIndex(id) { return this.filteredActivities.findIndex(item => item.id === id); },
      moveActivitySelection(direction) {
        const options = this.filteredActivities;
        if (!options.length) return;
        this.activeActivityIndex = (this.activeActivityIndex+direction+options.length)%options.length;
        this.$nextTick(() => this.$root.querySelectorAll('.sqa-server-panel__picker-option')[this.activeActivityIndex]?.scrollIntoView({block:'nearest'}));
      },
      chooseActiveActivity() { const option = this.filteredActivities[this.activeActivityIndex] || this.filteredActivities[0]; if (option) this.chooseActivity(option); },
      chooseActivity(activity) { if (!this.canPick()) return; this.selectedId = activity.id; this.showAddons = false; this.closeActivityPicker(); this.$nextTick(() => this.$root.querySelector('#sqa-demo-activity')?.focus()); },
      statusClass(s) { return ({ en_marcha:'sqa-server-panel__status--running',detenido:'sqa-server-panel__status--stopped',arrancando:'sqa-server-panel__status--starting',parando:'sqa-server-panel__status--starting',caido:'sqa-server-panel__status--crashed',externo:'sqa-server-panel__status--external' })[s] || 'sqa-server-panel__status--stopped'; },
      statusLabel(s) { return ({ en_marcha:'En marcha',detenido:'Detenido',arrancando:'Arrancando…',parando:'Parando…',caido:'Caído',externo:'Externo (FASTER)',sin_conexion:'Sin conexión' })[s] || str(s); },
      syncTone(s) { return s === 'ok' ? 'ok' : s === 'partial' ? 'warning' : 'danger'; },
      syncLabel(s) { return s === 'ok' ? 'PBO y HTML listos' : s === 'partial' ? 'Solo uno de los archivos listo' : 'Sin archivos preparados'; },
      syncHint(a) { return a?.reason || (a?.sync === 'ok' ? 'PBO y HTML disponibles en Windows.' : 'Hay archivos pendientes de sincronizar.'); },
      launchLabel() {
        if (!this.selectedActivity) return 'Elige una misión';
        if (!this.connected || this.readOnly) return 'No disponible';
        if (!['detenido','caido'].includes(this.operation.state)) return 'Evento en curso';
        if (!this.selectedActivity.launchable) return 'No se puede lanzar';
        return 'Lanzar ' + this.selectedActivity.name;
      },
      lockReason() {
        if (!this.connected) return 'Sin conexión con Mission Control.';
        if (this.readOnly) return 'Mission Control está en modo solo lectura.';
        if (!this.operation.controlable || this.operation.state === 'externo') return 'El servidor se ha iniciado fuera de Mission Control.';
        if (!['detenido','caido'].includes(this.operation.state)) return 'Hay un evento en curso. Detén el servidor antes de seleccionar otro.';
        return '';
      },
      // The local panel acts on Academias immediately. Operativos require a detailed confirmation.
      command(kind,id,action) {
        const item = kind === 'academia' ? this.academies.find(a => a.id === id) : this.operation;
        if (!this.allowAction(item,action)) { this.notice('Esta acción no está disponible ahora.','error'); return; }
        if (action === 'lanzar' && !this.canLaunchSelected) { this.notice(this.selectedActivity?.reason || 'Elige una misión preparada.','error'); return; }
        if (kind === 'academia') { this.sendAction({id,action,selection:'',stopAcademies:false,force:false,forceToken:''}); return; }
        if (action === 'lanzar') {
          const a = this.selectedActivity;
          this.confirmation = {
            id,action,selection:a.id,stopAcademies:this.stopAcademies,force:false,forceToken:'',
            title:'¿Lanzar el evento?',
            message:a.mode === 'Campaña' ? 'Se iniciará la campaña con su progreso guardado en Mission Control.' : 'Se copiará el PBO a la carpeta del servidor y se arrancará con los addons configurados.',
            rows:[['Evento',a.name],['Fecha',a.event || 'Sin evento asociado'],['Archivo',a.pbo || a.fileLabel || '—'],
                  a.mode === 'Campaña' ? ['Campaña',a.saveInfo || a.name] : ['Perfil',a.profile || 'Estándar'],
                  ['Addons',String(a.addons ?? 0)],['Parar las academias',this.stopAcademies ? 'Sí, durante el evento' : 'No'],['Lanzado por',this.actorName || 'Administrador']],
            accept:'Lanzar evento',danger:false
          };
        } else {
          this.confirmation = {
            id,action,selection:'',stopAcademies:false,force:false,forceToken:'',
            title:action === 'parar' ? '¿Parar el servidor de operativos?' : '¿Reiniciar el servidor de operativos?',
            message:action === 'parar'
              ? 'El servidor se cerrará y los jugadores saldrán. Las academias detenidas para este evento volverán a arrancar.'
              : 'El servidor se reiniciará con el mismo evento. Todos los jugadores se desconectarán.',
            rows:[['Servidor',item.name],['Evento',item.mission || '—'],['Jugadores conectados',this.number(item.players)],['Lo hace',this.actorName || 'Administrador']],
            accept:action === 'parar' ? 'Sí, parar servidor' : 'Sí, reiniciar servidor',danger:true
          };
        }
        this.$nextTick(() => this.$refs.cancelServerConfirm?.focus());
      },
      closeConfirm() { this.confirmation = null; },
      async acceptConfirm() {
        const confirmation = this.confirmation;
        if (!confirmation || this.isBusy(confirmation.id)) return;
        this.confirmation = null;
        await this.sendAction(confirmation);
      },
      async sendAction(command) {
        if (this.isBusy(command.id) || !this.canChangeServer) return;
        if (command.action === 'lanzar' && this.selectedId !== command.selection) { this.notice('La misión seleccionada ha cambiado. Revísala de nuevo.','error'); return; }
        this.busyServer = {...this.busyServer,[command.id]:command.action};
        try {
          const result = await this.$wire.controlServer(command.id,command.action,command.selection || '',!!command.stopAcademies,!!command.force,command.forceToken || '');
          if (result.requiresForce) {
            const n = result.players;
            this.confirmation = {
              ...command,force:true,forceToken:result.forceToken || '',
              title:'Hay jugadores conectados',
              message:result.message || (n == null ? 'Hay jugadores conectados. ¿Seguro?' : `Hay ${n} ${plural(n,'jugador conectado','jugadores conectados')}. ¿Seguro?`) + ' Saldrán del servidor.',
              rows:[], accept:command.action === 'parar' ? 'Sí, parar' : command.action === 'reiniciar' ? 'Sí, reiniciar' : 'Sí, lanzar',danger:true
            };
            this.$nextTick(() => this.$refs.cancelServerConfirm?.focus());
          } else {
            this.notice(result.message || (result.ok ? 'Orden aceptada.' : 'No se pudo completar la acción.'),result.ok ? 'ok' : 'error');
          }
        } catch (_) {
          this.notice('No se ha podido confirmar la orden. Comprueba el estado del servidor antes de repetir.','error');
        } finally {
          const next = {...this.busyServer}; delete next[command.id]; this.busyServer = next;
          await this.refresh(false);
        }
      },
      async saveRestart(academy) {
        if (!this.allowRestartConfig(academy)) return;
        this.busyServer = {...this.busyServer,[academy.id]:'reinicio_diario'};
        try {
          const result = await this.$wire.saveDailyRestart(academy.id,academy.auto,academy.time);
          this.notice(result.message || 'Reinicio diario actualizado.',result.ok ? 'ok' : 'error');
        } catch (_) {
          this.notice('No se ha podido confirmar el reinicio diario. Comprueba su estado antes de repetir.','error');
        } finally {
          const next = {...this.busyServer}; delete next[academy.id]; this.busyServer = next;
          await this.refresh(false);
        }
      }
    };
  };
})();

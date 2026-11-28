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
        this.$watch('logFilter', () => this.renderLog());
        this.$watch('logErrorsOnly', () => this.renderLog());
        this.refresh(true);
        // Server reads only; no start/stop/sync job is run by these polls.
        this.pollTimer = window.setInterval(() => this.refresh(false), 5000);
        this.clockTimer = window.setInterval(() => { this.nowMs = Date.now(); }, 1000);
      },
      destroy() {
        if (this.pollTimer) window.clearInterval(this.pollTimer);
        if (this.clockTimer) window.clearInterval(this.clockTimer);
        this.closeLogs();
        this.toasts.forEach(item => window.clearTimeout(item.timer));
        this.confirmationQueue = [];
        if (this.$refs.serverConfirm?.open) this.$refs.serverConfirm.close();
      },
      pollTimer:null, clockTimer:null, nowMs:Date.now(),
      loading:false, ready:false, connected:false, readOnly:true, stale:false,
      canLaunch:false, canStop:false, canConfigure:false, canViewLogs:false,
      logServer:'', logFiles:[], logIndex:-1, logFilter:'', logErrorsOnly:false,
      logFollow:true, logText:'', logOffset:null, logBusy:false, logStatus:'',
      logTimer:null, logEpoch:0, logCatalogLoading:false,
      mode:'', version:'', actorName:'', checkedAt:'', error:'', catalogError:'',
      servers:{}, activities:[], selectedId:'',
      activitySearch:'', activityPickerOpen:false, activeActivityIndex:0,
      stopAcademies:false, showAddons:false, confirmation:null, confirmationQueue:[], log:[],
      busyServer:{}, toasts:[], toastSeq:0,
      get selectedLogFile() { return this.logFiles[this.logIndex] || null; },
      get logDownloadUrl() {
        const f = this.selectedLogFile;
        if (!this.canViewLogs || !f || !this.validLogTarget(f.source,f.name)) return '';
        return '/admin/servidores-juego/logs/' +
          [this.logServer,f.source,f.name].map(encodeURIComponent).join('/');
      },
      validLogTarget(source,file) {
        return /^[A-Za-z0-9_-]{1,64}$/.test(String(source))
          && /^[A-Za-z0-9_.-]{1,120}\.rpt$/.test(String(file))
          && !String(file).includes('..');
      },
      formatLogSize(value) {
        if (value == null || value === '') return 'Tamaño desconocido';
        const n = Number(value);
        if (!Number.isFinite(n) || n < 0) return String(value);
        return n < 1024 ? n+' B' : n < 1048576
          ? (n/1024).toFixed(1)+' KiB' : (n/1048576).toFixed(1)+' MiB';
      },
      normalizeLogFiles(data) {
        const files = [];
        const add = (row,src,title) => {
          if (!row || typeof row !== 'object') return;
          const name = String(row.nombre ?? row.archivo ?? row.name ?? '');
          const source = String(src ?? '');
          if (!this.validLogTarget(source,name)) return;
          const when = String(row.fecha ?? row.modificado ?? row.fecha_modificacion ?? row.modificado_en ?? '');
          const date = when && Number.isFinite(Date.parse(when)) ? this.formatDate(when) : (when || 'Sin fecha');
          const old = (row.ultimo ?? row.es_ultimo) === false;
          files.push({
            source,name,
            label: String(title || source)+' · '+date+' · '
              +this.formatLogSize(row.tamano ?? row.tamano_bytes ?? row.bytes ?? row.size)
              +(old ? ' (anterior)' : ''),
            when
          });
        };
        const sources = Array.isArray(data?.fuentes) ? data.fuentes : Array.isArray(data?.sources) ? data.sources : [];
        sources.forEach(group => {
          if (!group || typeof group !== 'object') return;
          const id = group.id ?? group.clave ?? group.fuente ?? group.codigo ?? '';
          const title = group.nombre ?? group.name ?? id;
          (Array.isArray(group.archivos) ? group.archivos : Array.isArray(group.logs) ? group.logs : [])
            .forEach(row => add(row,id,title));
        });
        const flat = Array.isArray(data?.archivos) ? data.archivos :
          Array.isArray(data?.logs) ? data.logs : Array.isArray(data) ? data : [];
        flat.forEach(row => {
          const origin = row?.fuente ?? row?.source ?? '';
          const id = typeof origin === 'object' && origin ? (origin.id ?? origin.clave ?? '') : origin;
          const title = typeof origin === 'object' && origin ? (origin.nombre ?? id) : (row?.fuente_nombre ?? id);
          add(row,id,title);
        });
        const unique = [...new Map(files.map(f => [f.source+':'+f.name,f])).values()];
        unique.sort((a,b) => (Date.parse(b.when)||0) - (Date.parse(a.when)||0));
        return unique;
      },
      async openLogs(server) {
        if (!this.canViewLogs || !['operativos',...IDS].includes(server)) return;
        this.closeLogs();
        this.logServer = server;
        this.logFiles = []; this.logIndex = -1;
        this.logText = ''; this.logOffset = null;
        this.logFollow = true; this.logErrorsOnly = false; this.logFilter = '';
        this.logCatalogLoading = true;
        this.logStatus = 'Cargando archivos…';
        const epoch = ++this.logEpoch;
        this.$refs.logsDialog?.showModal();
        this.renderLog();
        try {
          const result = await this.$wire.loadLogs(server);
          if (epoch !== this.logEpoch) return;
          if (!result?.ok) throw new Error(result?.message || 'No se pudo consultar el servidor.');
          this.logFiles = this.normalizeLogFiles(result.data);
          if (!this.logFiles.length) {
            this.logStatus = 'Este servidor no tiene logs.';
            return;
          }
          this.logStatus = '';
          await this.selectLog(0);
        } catch (e) {
          if (epoch === this.logEpoch) this.logStatus = e.message || 'No se pudieron cargar los archivos.';
        } finally {
          if (epoch === this.logEpoch) this.logCatalogLoading = false;
        }
      },
      async selectLog(index) {
        const n = Number(index);
        if (!Number.isInteger(n) || n < 0 || n >= this.logFiles.length) return;
        this.logIndex = n;
        const epoch = ++this.logEpoch;
        this.logText = ''; this.logOffset = null; this.logBusy = false;
        this.logStatus = 'Leyendo el final del archivo…';
        this.renderLog(true);
        this.startLogFollowing();
        await this.readCurrentLog(epoch,true);
      },
      closeLogs() {
        ++this.logEpoch;
        this.stopLogFollowing();
        this.logBusy = false;
        if (this.$refs.logsDialog?.open) this.$refs.logsDialog.close();
      },
      stopLogFollowing() {
        if (this.logTimer !== null) window.clearInterval(this.logTimer);
        this.logTimer = null;
      },
      startLogFollowing() {
        this.stopLogFollowing();
        this.logTimer = window.setInterval(() => {
          if (this.logFollow && !document.hidden && this.$refs.logsDialog?.open && !this.logBusy)
            this.readCurrentLog(this.logEpoch);
        },3000);
      },
      async readCurrentLog(epoch=this.logEpoch,first=false) {
        if (this.logBusy || epoch !== this.logEpoch || !this.canViewLogs || !this.selectedLogFile) return;
        const file = this.selectedLogFile;
        if (!this.validLogTarget(file.source,file.name)) return;
        this.logBusy = true;
        try {
          let more = false, rounds = 0;
          do {
            const result = await this.$wire.readLog(this.logServer,file.source,file.name,this.logOffset);
            if (epoch !== this.logEpoch || !this.$refs.logsDialog?.open) return;
            if (!result?.ok) throw new Error(result?.message || 'No se pudo leer el archivo.');
            const data = result.data || {};
            const end = Number(data.fin);
            if (!Number.isSafeInteger(end) || end < 0 || data.fin == null)
              throw new Error('Posición de lectura no válida.');
            const pre = this.$refs.logOutput;
            const atEnd = first || !pre || pre.scrollHeight-pre.scrollTop-pre.clientHeight < 70;
            if (data.reiniciado === true) this.logText = '';
            this.logText += String(data.texto ?? '');
            const lines = this.logText.split('\n');
            if (lines.length > 20000) this.logText = lines.slice(-20000).join('\n');
            this.logOffset = end; // Byte position, never text length.
            this.logStatus = 'Lectura actualizada.';
            this.renderLog(atEnd);
            more = data.mas === true && ++rounds < 4;
          } while (more && epoch === this.logEpoch);
        } catch (e) {
          if (epoch === this.logEpoch) this.logStatus = e.message || 'No se pudo leer el archivo.';
        } finally {
          if (epoch === this.logEpoch) this.logBusy = false;
        }
      },
      renderLog(toEnd=false) {
        const pre = this.$refs.logOutput;
        if (!pre) return;
        const filter = this.logFilter.trim().toLocaleLowerCase('es');
        const errors = this.logErrorsOnly;
        const text = !filter && !errors ? this.logText : this.logText.split('\n')
          .filter(line => (!errors || /error|exception|crash|warning/i.test(line))
            && (!filter || line.toLocaleLowerCase('es').includes(filter))).join('\n');
        // No HTML interpretation of chat, player names or RPT contents.
        pre.textContent = text;
        if (toEnd) pre.scrollTop = pre.scrollHeight;
      },
      get academies() { return IDS.map(id => this.servers[id] || defaultServer(id)); },
      get operation() { return this.servers.operativos || defaultServer('operativos'); },
      get activeActivityName() { return this.operation.mission || ''; },
      get activePbo() { return this.operation.pbo || ''; },
      get selectedActivity() { return this.activities.find(a => a.id === this.selectedId) || null; },
      get displayActivity() { return this.selectedActivity || { name:'—', event:'',pbo:null,map:'—',slots:'—',profile:'—',addons:0,addonNames:[],missingAddons:[],serverMods:[],mode:'',sync:'missing',pboReady:false,launchable:false,reason:'Elige una misión para consultar sus datos.',saveInfo:null,webLink:null }; },
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
      get activityCountLabel() {
        const count = this.filteredActivities.length;
        return this.activitySearch.trim()
          ? `${count} ${count === 1 ? 'coincidencia' : 'coincidencias'}`
          : `${count} ${count === 1 ? 'opción disponible' : 'opciones disponibles'}`;
      },
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
      modeLabel() {
        return ({ windows:'Windows', simulation:'Simulación', simulacion:'Simulación' })[this.mode] || this.mode;
      },
      lastReading() { return this.checkedAt ? `Última lectura: ${this.checkedAt}` : 'Todavía sin lectura'; },
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
        if (this.loading) return false;
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
          this.canViewLogs = !!data.canViewLogs;
          if (!this.canViewLogs && this.$refs.logsDialog?.open) this.closeLogs();
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
          if (!this.canPick() && this.activityPickerOpen) this.closeActivityPicker();
          return online && !this.catalogError;
        } catch (_) {
          this.connected = false; this.readOnly = true; this.stale = true;
          this.error = 'Sin conexión con Mission Control. Los datos pueden estar desactualizados.';
          return false;
        } finally { this.ready = true; this.loading = false; }
      },
      async reloadActivities() {
        if (this.loading) { this.notice('Ya se está actualizando el panel.', 'info'); return; }
        const updated = await this.refresh(true);
        this.notice(updated ? `Lista de eventos actualizada (${this.activities.length}).` : (this.catalogError || this.error || 'No se ha podido actualizar la lista.'), updated ? 'ok' : 'error');
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
      closeActivityPicker(restoreFocus = false) {
        if (!this.activityPickerOpen) return;
        this.activityPickerOpen = false;
        this.activitySearch = '';
        if (restoreFocus) this.$nextTick(() => this.$refs.activityPickerTrigger?.focus());
      },
      activityIndex(id) { return this.filteredActivities.findIndex(item => item.id === id); },
      moveActivitySelection(direction) {
        const options = this.filteredActivities;
        if (!options.length) return;
        this.activeActivityIndex = (this.activeActivityIndex+direction+options.length)%options.length;
        this.$nextTick(() => this.$root.querySelectorAll('.sqa-server-panel__picker-option')[this.activeActivityIndex]?.scrollIntoView({block:'nearest'}));
      },
      chooseActiveActivity() { const option = this.filteredActivities[this.activeActivityIndex] || this.filteredActivities[0]; if (option) this.chooseActivity(option); },
      chooseActivity(activity) { if (!this.canPick()) return; this.selectedId = activity.id; this.showAddons = false; this.closeActivityPicker(true); },
      statusClass(s) { return ({ en_marcha:'sqa-server-panel__status--running',detenido:'sqa-server-panel__status--stopped',arrancando:'sqa-server-panel__status--starting',parando:'sqa-server-panel__status--starting',caido:'sqa-server-panel__status--crashed',externo:'sqa-server-panel__status--external' })[s] || 'sqa-server-panel__status--stopped'; },
      statusLabel(s) { return ({ en_marcha:'En marcha',detenido:'Detenido',arrancando:'Arrancando…',parando:'Parando…',caido:'Caído',externo:'Externo (FASTER)',sin_conexion:'Sin conexión' })[s] || str(s); },
      syncTone(s) { return s === 'ok' ? 'ok' : s === 'partial' ? 'warning' : 'danger'; },
      syncLabel(s) { return s === 'ok' ? 'PBO y addons listos' : s === 'partial' ? 'PBO disponible, revisar addons' : 'PBO o addons no preparados'; },
      syncHint(a) { return a?.reason || (a?.sync === 'ok' ? 'PBO verificado y addons disponibles.' : 'Revisa el PBO y los addons de la actividad.'); },
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
      // The native modal isolates focus from the rest of Filament. Force prompts
      // from concurrent server requests must not overwrite another open prompt.
      openConfirm(command) {
        if (this.confirmation) { this.confirmationQueue.push(command); return; }
        this.confirmation = command;
        this.closeActivityPicker();
        this.$nextTick(() => {
          const dialog = this.$refs.serverConfirm;
          if (!this.confirmation || !dialog?.isConnected) return;
          if (!dialog.open) dialog.showModal();
          this.$refs.cancelServerConfirm?.focus();
        });
      },
      serverFingerprint(server) {
        return JSON.stringify([server.id,server.startedAt,server.mission,server.pbo]);
      },
      launchFingerprint(a) {
        if (!a) return '';
        return JSON.stringify([a.id,a.name,a.pbo,a.profile,a.mode,a.saveInfo,a.addonNames,a.missingAddons,a.serverMods]);
      },
      get confirmationProblem() {
        const c = this.confirmation;
        if (!c) return '';
        const server = this.servers[c.id] || defaultServer(c.id);
        if (!this.canChangeServer) return 'No hay conexión de control disponible.';
        if (this.isBusy(c.id)) return 'Hay una orden en curso para este servidor.';
        if (!this.allowAction(server,c.action)) return 'El estado o los permisos del servidor han cambiado. Revisa la acción.';
        if (c.action !== 'lanzar' && c.serverFingerprint && c.serverFingerprint !== this.serverFingerprint(server)) return 'El servidor ha cambiado desde que se solicitó esta acción. Cancela y vuelve a comprobarlo.';
        if (c.action === 'lanzar' && (!this.canLaunchSelected || this.selectedId !== c.selection || (c.fingerprint && c.fingerprint !== this.launchFingerprint(this.selectedActivity)))) return 'Los datos del evento han cambiado. Cancela y vuelve a revisar el lanzamiento.';
        return '';
      },
      finishConfirm() {
        this.confirmation = null;
        const dialog = this.$refs.serverConfirm;
        if (dialog?.open) dialog.close();
        if (this.confirmationQueue.length) this.openConfirm(this.confirmationQueue.shift());
      },
      // The local panel acts on Academias immediately. Operativos require a detailed confirmation.
      command(kind,id,action) {
        const item = kind === 'academia' ? this.academies.find(a => a.id === id) : this.operation;
        if (!this.allowAction(item,action)) { this.notice('Esta acción no está disponible ahora.','error'); return; }
        if (action === 'lanzar' && !this.canLaunchSelected) { this.notice(this.selectedActivity?.reason || 'Elige una misión preparada.','error'); return; }
        if (kind === 'academia') { this.sendAction({id,action,selection:'',stopAcademies:false,force:false,forceToken:'',serverFingerprint:this.serverFingerprint(item)}); return; }
        if (this.confirmation) return;
        if (action === 'lanzar') {
          const a = this.selectedActivity;
          this.openConfirm({
            id,action,selection:a.id,stopAcademies:this.stopAcademies,force:false,forceToken:'',fingerprint:this.launchFingerprint(a),
            title:'¿Lanzar el evento?',
            message:a.mode === 'Campaña' ? 'Se iniciará la campaña con su progreso guardado en Mission Control.' : 'Se copiará el PBO a la carpeta del servidor y se arrancará con los addons configurados.',
            rows:[['Evento',a.name],['Fecha',a.event || 'Sin evento asociado'],['Archivo',a.pbo || a.fileLabel || '—'],
                  a.mode === 'Campaña' ? ['Campaña',(a.saveInfo || a.name).replace(/^Campaña: /,''),'highlight'] : ['Perfil',a.profile || 'Estándar'],
                  ['Addons',String(a.addons ?? 0)],['Parar las academias',this.stopAcademies ? 'Sí, durante el evento' : 'No'],['Lanzado por',this.actorName || 'Administrador']],
            accept:'Lanzar evento',danger:false
          });
        } else {
          this.openConfirm({
            id,action,selection:'',stopAcademies:false,force:false,forceToken:'',serverFingerprint:this.serverFingerprint(item),
            title:action === 'parar' ? '¿Parar el servidor de operativos?' : '¿Reiniciar el servidor de operativos?',
            message:action === 'parar'
              ? 'El servidor se cerrará y los jugadores saldrán. Las academias detenidas para este evento volverán a arrancar.'
              : 'El servidor se reiniciará con el mismo evento. Todos los jugadores se desconectarán.',
            rows:[['Servidor',item.name],['Evento',item.mission || '—'],['Jugadores conectados',this.number(item.players)],['Lo hace',this.actorName || 'Administrador']],
            accept:action === 'parar' ? 'Sí, parar servidor' : 'Sí, reiniciar servidor',danger:true
          });
        }
      },
      closeConfirm() {
        if (this.confirmation?.force) this.notice('Cancelado: no se ha hecho ningún cambio.', 'info');
        this.finishConfirm();
      },
      async acceptConfirm() {
        const confirmation = this.confirmation;
        if (!confirmation || this.confirmationProblem) return;
        this.finishConfirm();
        await this.sendAction(confirmation);
      },
      async sendAction(command) {
        if (this.isBusy(command.id) || !this.canChangeServer) return;
        if (!this.allowAction(this.servers[command.id] || defaultServer(command.id),command.action)) { this.notice('El estado del servidor ha cambiado. Revisa la acción.','error'); return; }
        if (command.action === 'lanzar' && this.selectedId !== command.selection) { this.notice('La misión seleccionada ha cambiado. Revísala de nuevo.','error'); return; }
        this.busyServer = {...this.busyServer,[command.id]:command.action};
        try {
          const result = await this.$wire.controlServer(command.id,command.action,command.selection || '',!!command.stopAcademies,!!command.force,command.forceToken || '',command.warningToken || '');
          if (result.requiresForce) {
            const n = result.players;
            this.openConfirm({
              ...command,force:true,forceToken:result.forceToken || '',
              title:'Hay jugadores conectados',
              message:result.message || (n == null ? 'Hay jugadores conectados. ¿Seguro?' : `Hay ${n} ${plural(n,'jugador conectado','jugadores conectados')}. ¿Seguro?`) + ' Saldrán del servidor.',
              rows:[['Servidor',(this.servers[command.id] || defaultServer(command.id)).name],['Jugadores conectados',this.number(n)]], warning:'Los jugadores conectados saldrán del servidor.', accept:command.action === 'parar' ? 'Sí, parar' : command.action === 'reiniciar' ? 'Sí, reiniciar' : 'Sí, lanzar',danger:true
            });
          } else if (result.requiresCampaignWarning) {
            const removed = Array.isArray(result.removedAddons) ? result.removedAddons : [];
            this.openConfirm({
              ...command,warningToken:result.warningToken || '',
              title:'Campaña: addons retirados',
              message:result.message || 'El progreso guardado de ALiVE podría necesitar estos addons.',
              rows:[['Evento',this.selectedActivity?.name || '—'],['Addons retirados',removed.join(', ') || '—']],
              warning:'Si continúas, el servidor cargará solamente los addons de esta actividad.',
              accept:'Continuar y lanzar',danger:true
            });
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

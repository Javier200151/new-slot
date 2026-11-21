/* Vista previa local: ningún botón envía solicitudes a Laravel ni al agente. */
(function () {
  'use strict';

  function buildLabel(activity) {
    const syncText = activity.sync === 'ok'
      ? 'PBO + HTML'
      : activity.sync === 'partial'
        ? 'PBO o HTML'
        : 'Sin archivos';

    if (activity.group === 'folder') {
      return 'Carpeta web · ' + activity.fileLabel + ' · ' + syncText;
    }

    return activity.groupLabel + ' · ' + activity.whenLabel + ' · ' + activity.name + ' · ' + syncText;
  }

  function demo() {
    const activities = [
      {
        id: 'pacific_shield',
        group: 'upcoming', status: 'ACTIVO', eventType: 'OPERACIÓN', sortAt: '2026-11-21T22:00:00',
        groupLabel: 'Próximos eventos',
        whenLabel: 'hoy 22:00',
        label: '',
        name: 'Pacific Shield',
        event: 'Evento · vie 20/11 22:30',
        type: 'OPERACIÓN',
        mode: 'Campaña',
        map: 'Mandol (rut_mandol)',
        slots: 27,
        profile: 'Estándar · 3 HC',
        pbo: 'CO@64HC3_PacificShield.Tanoa.pbo',
        html: 'CO@64HC3_PacificShield.Tanoa.html',
        fileLabel: 'CO@64HC3_PacificShield.Tanoa.pbo',
        addons: 25,
        serverMods: ['@SQA_A', '@LAMBS'],
        sync: 'ok',
        saveInfo: 'Campaña: Pacific Shield · progreso guardado sáb 07/11 02:47 (13,9 MB)',
        webLink: 'Ver en la web',
        note: '25 addons, todos en el servidor'
      },
      {
        id: 'cqb_moderna',
        group: 'upcoming', status: 'ACTIVO', eventType: 'OPERACIÓN', sortAt: '2026-11-23T20:00:00',
        groupLabel: 'Próximos eventos',
        whenLabel: 'lun 23/11 20:00',
        label: '',
        name: 'Lunes de CQB (Moderna)',
        event: 'Evento · lun 23/11 20:00',
        type: 'OPERACIÓN',
        mode: 'Operativo',
        map: 'Altis',
        slots: 18,
        profile: 'CQB · 1 HC',
        pbo: 'CQB_LUNES.Altis.pbo',
        html: null,
        fileLabel: 'CQB_LUNES.Altis.pbo',
        addons: 14,
        serverMods: [],
        sync: 'partial',
        saveInfo: null,
        webLink: 'Ver en la web',
        note: 'Solo PBO subido'
      },
      {
        id: 'cimc_kat',
        group: 'upcoming', status: 'ACTIVO', eventType: 'CURSO', sortAt: '2026-11-26T20:00:00',
        groupLabel: 'Próximos eventos',
        whenLabel: 'jue 26/11 20:00',
        label: '',
        name: 'Curso de Instrucción Médico de Combate (CIMC KAT)',
        event: 'Evento · jue 26/11 20:00',
        type: 'OPERACIÓN',
        mode: 'Tutoría',
        map: 'Malden',
        slots: 12,
        profile: 'Curso · 0 HC',
        pbo: null,
        html: null,
        fileLabel: 'CIMC_KAT.Malden.pbo',
        addons: 11,
        serverMods: [],
        sync: 'missing',
        saveInfo: null,
        webLink: 'Ver en la web',
        note: 'Pendiente de preparar archivos'
      },
      {
        id: 'skyfall_plan_b',
        group: 'past', status: 'FINALIZADO', eventType: 'OPERACIÓN', sortAt: '2026-11-20T22:30:00',
        groupLabel: 'Eventos pasados',
        whenLabel: 'ayer 22:30',
        label: '',
        name: 'SKYFALL - PLAN B',
        event: 'Evento · ayer 22:30',
        type: 'OPERACIÓN',
        mode: 'Multiclán',
        map: 'Tanoa',
        slots: 29,
        profile: 'Multiclán · 3 HC',
        pbo: 'SKYFALL_PLAN_B.Tanoa.pbo',
        html: 'SKYFALL_PLAN_B.Tanoa.html',
        fileLabel: 'SKYFALL_PLAN_B.Tanoa.pbo',
        addons: 28,
        serverMods: ['@SQA_A'],
        sync: 'ok',
        saveInfo: null,
        webLink: 'Ver en la web',
        note: 'Lista completa sincronizada'
      },
      {
        id: 'death_valley',
        group: 'past', status: 'FINALIZADO', eventType: 'OPERACIÓN', sortAt: '2026-11-19T22:30:00',
        groupLabel: 'Eventos pasados',
        whenLabel: 'ayer 22:30',
        label: '',
        name: 'DEATH VALLEY',
        event: 'Evento · ayer 22:30',
        type: 'OPERACIÓN',
        mode: 'Evento',
        map: 'Mandol (rut_mandol)',
        slots: 27,
        profile: 'Estándar · 3 HC',
        pbo: 'CO@27HC3_DEATH_VALLEY.rut_mandol.pbo',
        html: 'CO@27HC3_DEATH_VALLEY.rut_mandol.html',
        fileLabel: 'CO@27HC3_DEATH_VALLEY.rut_mandol.pbo',
        addons: 22,
        serverMods: ['@CMods_server'],
        sync: 'ok',
        saveInfo: null,
        webLink: 'Ver en la web',
        note: '22 addons, todos en el servidor'
      },
      {
        id: 'cior',
        group: 'past', status: 'FINALIZADO', eventType: 'TUTORÍA', sortAt: '2026-11-18T20:00:00',
        groupLabel: 'Eventos pasados',
        whenLabel: 'mié 18/11 20:00',
        label: '',
        name: 'Curso de Instrucción de Operador de Radio (CIOR)',
        event: 'Evento · mié 18/11 20:00',
        type: 'OPERACIÓN',
        mode: 'Tutoría',
        map: 'Stratis',
        slots: 10,
        profile: 'Curso · 0 HC',
        pbo: null,
        html: 'CIOR.Stratis.html',
        fileLabel: 'CIOR.Stratis.pbo',
        addons: 10,
        serverMods: [],
        sync: 'partial',
        saveInfo: null,
        webLink: 'Ver en la web',
        note: 'Solo HTML generado'
      },
      {
        id: 'ejemplo_borrador',
        group: 'upcoming', status: 'BORRADOR', eventType: 'OPERACIÓN', sortAt: '2026-11-22T20:00:00',
        groupLabel: 'Próximos eventos', whenLabel: 'dom 22/11 20:00', label: '',
        name: 'Operación sin publicar', event: 'Borrador', type: 'OPERACIÓN', mode: 'Operativo',
        map: 'Altis', slots: 18, profile: 'Estándar · 3 HC', pbo: null, html: null,
        fileLabel: 'BORRADOR.Altis.pbo', addons: 8, serverMods: [], sync: 'missing',
        saveInfo: null, webLink: null, note: 'No debe mostrarse entre los eventos activos'
      },
      {
        id: 'evento_antiguo',
        group: 'past', status: 'FINALIZADO', eventType: 'OPERACIÓN', sortAt: '2026-11-15T20:00:00',
        groupLabel: 'Eventos pasados', whenLabel: 'dom 15/11 20:00', label: '',
        name: 'Evento antiguo', event: 'Evento · dom 15/11 20:00', type: 'OPERACIÓN', mode: 'Operativo',
        map: 'Altis', slots: 18, profile: 'Estándar · 3 HC', pbo: null, html: null,
        fileLabel: 'ANTIGUO.Altis.pbo', addons: 8, serverMods: [], sync: 'missing',
        saveInfo: null, webLink: 'Ver en la web', note: 'No debe superar el límite de tres pasados'
      },
      {
        id: 'evento_pasado_tercero',
        group: 'past', status: 'FINALIZADO', eventType: 'OPERACIÓN', sortAt: '2026-11-18T22:30:00',
        groupLabel: 'Eventos pasados', whenLabel: 'mié 18/11 22:30', label: '',
        name: 'ALFA NOCHE', event: 'Evento · mié 18/11 22:30', type: 'OPERACIÓN', mode: 'Operativo',
        map: 'Stratis', slots: 18, profile: 'Estándar · 3 HC', pbo: 'ALFA_NOCHE.Stratis.pbo', html: null,
        fileLabel: 'ALFA_NOCHE.Stratis.pbo', addons: 8, serverMods: [], sync: 'partial',
        saveInfo: null, webLink: 'Ver en la web', note: 'Solo PBO disponible'
      },
      {
        id: 'oplot_spartansky',
        group: 'folder', linkedActivity: false,
        groupLabel: 'Otras misiones en la carpeta',
        whenLabel: 'sin actividad enlazada',
        label: '',
        name: 'OPLOT SPARTANSKY',
        event: 'Sin actividad asociada',
        type: 'OPERACIÓN',
        mode: 'Carpeta web',
        map: 'tem_anizay',
        slots: 30,
        profile: 'Estándar · 3 HC',
        pbo: 'CO@30_OPLOT_SPART_NW.tem_anizay.pbo',
        html: null,
        fileLabel: 'CO@30_OPLOT_SPART_NW.tem_anizay.pbo',
        addons: 23,
        serverMods: [],
        sync: 'partial',
        saveInfo: null,
        webLink: null,
        note: 'Archivo presente en la carpeta /web'
      },
      {
        id: 'xterminatus',
        group: 'folder', linkedActivity: false,
        groupLabel: 'Otras misiones en la carpeta',
        whenLabel: 'sin actividad enlazada',
        label: '',
        name: 'XTERMINATUS',
        event: 'Sin actividad asociada',
        type: 'OPERACIÓN',
        mode: 'Carpeta web',
        map: 'Altis',
        slots: 24,
        profile: 'Multiclán · 3 HC',
        pbo: 'XTERMINATUS.Altis.pbo',
        html: 'XTERMINATUS.Altis.html',
        fileLabel: 'XTERMINATUS.Altis.pbo',
        addons: 25,
        serverMods: [],
        sync: 'ok',
        saveInfo: null,
        webLink: null,
        note: 'Archivos listos para lanzar manualmente'
      },
      {
        id: 'prueba_rota',
        group: 'folder', linkedActivity: false,
        groupLabel: 'Otras misiones en la carpeta',
        whenLabel: 'sin actividad enlazada',
        label: '',
        name: 'PRUEBA ROTA',
        event: 'Sin actividad asociada',
        type: 'OPERACIÓN',
        mode: 'Carpeta web',
        map: 'Livonia',
        slots: 16,
        profile: 'Estándar · 1 HC',
        pbo: null,
        html: null,
        fileLabel: 'PRUEBA_ROTA.Livonia.pbo',
        addons: 9,
        serverMods: [],
        sync: 'missing',
        saveInfo: null,
        webLink: null,
        note: 'No hay archivos listos todavía'
      }
    ].map(activity => ({ ...activity, label: buildLabel(activity) }));

    return {
      init() {
        this.$watch('activitySearch', () => { this.activeActivityIndex = 0; });
      },
      academies: [
        { id: 'academia', name: 'Arma 3 Academia', mission: 'Academia', port: 2402, state: 'en_marcha', uptime: '3 h 0 min', players: 0, max: 58, auto: true, time: '06:00' },
        { id: 'academia_vietnam', name: 'Academia Vietnam', mission: 'ACADEMIA_VN', port: 2502, state: 'en_marcha', uptime: '3 h 0 min', players: 0, max: 33, auto: false, time: '06:00' },
        { id: 'academia_ww2', name: 'Academia WW2', mission: 'Academia_WW2', port: 2602, state: 'en_marcha', uptime: '3 h 0 min', players: 0, max: 28, auto: true, time: '05:30' }
      ],
      operation: { state: 'detenido', players: 0, max: 0, hc: 0, uptime: '—', sinceText: 'Sin actividad en curso' },
      activities,
      selectedId: 'pacific_shield',
      activityPickerOpen: false,
      activitySearch: '',
      activeActivityIndex: 0,
      activeActivityName: '',
      activePbo: '',
      stopAcademies: true,
      stoppedAcademies: [],
      confirmation: null,
      log: [
        { at: '21/11 14:50', text: 'Demo · Arrancada Academia' },
        { at: '21/11 14:50', text: 'Demo · Arrancada Academia Vietnam' },
        { at: '21/11 14:50', text: 'Demo · Detenida Academia Vietnam' },
        { at: '17/11 22:29', text: 'Sistema · Academias arrancadas al terminar la actividad' },
        { at: '17/11 22:29', text: 'Napster paró Operativos (MARTES)' },
        { at: '17/11 19:47', text: 'Sistema · Academias paradas para la actividad' },
        { at: '17/11 19:45', text: 'Napster lanzó AUTUMN DAWN en Operativos' }
      ],
      get selectedActivity() {
        return this.activities.find(activity => activity.id === this.selectedId) || this.activities[0];
      },
      // La selección se limita a eventos ACTIVO próximos, 3 OPERACIÓN pasados y archivos sin actividad.
      // En esta maqueta se aplica a los registros de ejemplo; la lectura real de la web vendrá después.
      get selectableActivityGroups() {
        const upcoming = this.activities
          .filter(activity => activity.group === 'upcoming' && activity.status === 'ACTIVO')
          .sort((a, b) => a.sortAt.localeCompare(b.sortAt));
        const past = this.activities
          .filter(activity => activity.group === 'past' && activity.eventType === 'OPERACIÓN')
          .sort((a, b) => b.sortAt.localeCompare(a.sortAt))
          .slice(0, 3);
        const folder = this.activities
          .filter(activity => activity.group === 'folder' && activity.linkedActivity === false)
          .sort((a, b) => a.fileLabel.localeCompare(b.fileLabel, 'es'));
        // El grupo de Hoy distingue el primer evento activo del día.
        const referenceDay = '2026-11-21'; // Fecha del ejemplo, no una fecha de producción.
        const today = upcoming.filter(activity => activity.sortAt.slice(0, 10) === referenceDay);
        const later = upcoming.filter(activity => activity.sortAt.slice(0, 10) !== referenceDay);
        return [
          { id: 'today', title: 'Hoy', items: today },
          { id: 'upcoming', title: 'Próximos eventos', items: later },
          { id: 'past', title: 'Eventos pasados', items: past },
          { id: 'folder', title: 'Otras misiones en la carpeta de la web', items: folder }
        ];
      },
      get filteredActivityGroups() {
        const query = this.foldActivityText(this.activitySearch).trim();
        const tokens = query.split(/\s+/).filter(Boolean);
        return this.selectableActivityGroups
          .map(group => ({
            ...group,
            items: group.items.filter(activity => {
              if (tokens.length === 0) return true;
              const searchable = this.foldActivityText([
                activity.name, activity.whenLabel, activity.event, activity.fileLabel,
                activity.pbo, activity.html, activity.map, activity.mode, group.title
              ].filter(Boolean).join(' '));
              return tokens.every(token => searchable.includes(token));
            })
          }))
          .filter(group => group.items.length > 0);
      },
      get filteredActivities() {
        return this.filteredActivityGroups.flatMap(group => group.items);
      },
      foldActivityText(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
      },
      openActivityPicker() {
        if (this.operation.state === 'en_marcha') return;
        this.activityPickerOpen = true;
        this.activitySearch = '';
        this.activeActivityIndex = Math.max(0, this.filteredActivities.findIndex(item => item.id === this.selectedId));
        this.$nextTick(() => this.$refs.activitySearchInput?.focus());
      },
      toggleActivityPicker() {
        if (this.activityPickerOpen) this.closeActivityPicker();
        else this.openActivityPicker();
      },
      closeActivityPicker() {
        this.activityPickerOpen = false;
        this.activitySearch = '';
      },
      activityIndex(id) {
        return this.filteredActivities.findIndex(item => item.id === id);
      },
      moveActivitySelection(direction) {
        const options = this.filteredActivities;
        if (!options.length) return;
        this.activeActivityIndex = (this.activeActivityIndex + direction + options.length) % options.length;
        this.$nextTick(() => {
          const items = this.$root.querySelectorAll('.sqa-server-panel__picker-option');
          items[this.activeActivityIndex]?.scrollIntoView({ block: 'nearest' });
        });
      },
      chooseActiveActivity() {
        const item = this.filteredActivities[this.activeActivityIndex] || this.filteredActivities[0];
        if (item) this.chooseActivity(item);
      },
      chooseActivity(activity) {
        if (this.operation.state === 'en_marcha') return;
        this.selectedId = activity.id;
        this.closeActivityPicker();
        this.$nextTick(() => this.$root.querySelector('#sqa-demo-activity')?.focus());
      },
      get selectedSyncTone() {
        return this.syncTone(this.selectedActivity?.sync);
      },
      statusClass(state) {
        return { 'en_marcha': 'sqa-server-panel__status--running', 'detenido': 'sqa-server-panel__status--stopped', 'arrancando': 'sqa-server-panel__status--starting' }[state] || 'sqa-server-panel__status--stopped';
      },
      statusLabel(state) {
        return { 'en_marcha': 'En marcha', 'detenido': 'Detenido', 'arrancando': 'Arrancando' }[state] || 'Desconocido';
      },
      syncTone(sync) {
        return sync === 'ok' ? 'ok' : (sync === 'partial' ? 'warning' : 'danger');
      },
      syncLabel(sync) {
        return sync === 'ok' ? 'PBO y HTML listos' : (sync === 'partial' ? 'Solo uno de los archivos listo' : 'Sin PBO ni HTML');
      },
      syncHint(activity) {
        if (!activity) return '';
        if (activity.sync === 'ok') return 'PBO y HTML disponibles en Windows.';
        if (activity.sync === 'partial') return 'Solo uno de los dos archivos está preparado.';
        return 'Faltan los archivos necesarios.';
      },
      command(kind, id, action) {
        const item = kind === 'academia' ? this.academies.find(a => a.id === id) : this.operation;
        if (!item) return;
        const name = kind === 'academia' ? item.name : 'Operativos';
        const players = item.state === 'en_marcha' ? item.players : 0;
        const danger = action === 'parar' || action === 'reiniciar';
        const message = action === 'lanzar'
          ? '¿Lanzar ' + this.selectedActivity.name + '? La acción solo cambiará los datos de esta maqueta.'
          : '¿' + ({ arrancar: 'Arrancar', parar: 'Parar', reiniciar: 'Reiniciar' }[action]) + ' ' + name + '?'
            + (danger && players > 0 ? ' Hay ' + players + ' jugadores conectados.' : '')
            + ' Esta acción es una simulación.';
        this.confirmation = {
          kind, id, action,
          title: (action === 'lanzar' ? 'Lanzar actividad' : ({ arrancar: 'Arrancar servidor', parar: 'Parar servidor', reiniciar: 'Reiniciar servidor' }[action])),
          message, danger,
          accept: (action === 'lanzar' ? 'Lanzar (demo)' : ({ arrancar: 'Arrancar (demo)', parar: 'Parar (demo)', reiniciar: 'Reiniciar (demo)' }[action]))
        };
      },
      reloadActivities() {
        this.pushLog('Demo · Recargadas las misiones de la web');
      },
      closeConfirm() { this.confirmation = null; },
      acceptConfirm() {
        const decision = this.confirmation;
        if (!decision) return;
        if (decision.kind === 'academia') {
          const item = this.academies.find(a => a.id === decision.id);
          if (!item) return this.closeConfirm();
          item.state = decision.action === 'parar' ? 'detenido' : 'en_marcha';
          if (decision.action === 'arrancar' || decision.action === 'reiniciar') {
            item.uptime = '0 d 00:00:01'; item.players = 0;
          } else {
            item.players = 0;
          }
          this.pushLog('Demo · ' + ({ arrancar: 'Arrancada', parar: 'Detenida', reiniciar: 'Reiniciada' }[decision.action]) + ' ' + item.name);
        } else {
          if (decision.action === 'lanzar') {
            this.operation.state = 'en_marcha';
            this.operation.uptime = '00:00:01';
            this.operation.players = 0;
            this.operation.max = this.selectedActivity.slots;
            this.operation.hc = 0;
            this.operation.sinceText = 'Actividad cargada · maqueta';
            this.activeActivityName = this.selectedActivity.name;
            this.activePbo = this.selectedActivity.pbo || this.selectedActivity.fileLabel;
            this.stoppedAcademies = [];
            if (this.stopAcademies) {
              this.academies.forEach(a => { if (a.state === 'en_marcha') { this.stoppedAcademies.push(a.id); a.state = 'detenido'; a.players = 0; } });
            }
            this.pushLog('Demo · Lanzado ' + this.activeActivityName + ' en Operativos');
          } else if (decision.action === 'parar') {
            this.operation.state = 'detenido'; this.operation.players = 0; this.operation.max = 0; this.operation.hc = 0; this.operation.sinceText = 'Sin actividad en curso';
            this.stoppedAcademies.forEach(id => { const a = this.academies.find(entry => entry.id === id); if (a) { a.state = 'en_marcha'; a.uptime = '0 d 00:00:01'; } });
            this.stoppedAcademies = [];
            this.activeActivityName = '';
            this.activePbo = '';
            this.pushLog('Demo · Detenido Operativos');
          } else {
            this.operation.uptime = '00:00:01'; this.operation.players = 0;
            this.pushLog('Demo · Reiniciado Operativos');
          }
        }
        this.closeConfirm();
      },
      pushLog(text) {
        const stamp = new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date());
        this.log.unshift({ at: stamp, text });
        this.log = this.log.slice(0, 12);
      }
    };
  }
  window.sqaServersDemo = demo;
})();

(() => {
  const form = document.querySelector('.sqa-stats__form');
  if (!form) return;

  const focus = form.querySelector('[name="focus"]');
  const metric = form.querySelector('[name="metric"]');
  const group = form.querySelector('[name="group"]');
  const period = form.querySelector('[name="period"]');
  const dateFrom = form.querySelector('[name="from"]');
  const dateTo = form.querySelector('[name="to"]');
  const primary = form.querySelector('#stat-main-filter');
  const additional = form.querySelector('#stat-additional-filters');
  const help = form.querySelector('#stat-focus-description');
  const categoryFields = Array.from(form.querySelectorAll('[data-focus-field]'));

  const supported = {
    participations: ['user', 'role', 'month', 'editor', 'platform', 'activity_type', 'map', 'campaign'],
    events: ['editor', 'month', 'platform', 'activity_type', 'map', 'campaign'],
    missions: ['editor', 'month', 'platform', 'activity_type', 'map', 'campaign'],
  };
  const defaults = {
    role: { metric: 'participations', group: 'user', message: 'Elige un tipo de slot para ver qué jugadores lo han utilizado más.' },
    activity_type: { metric: 'events', group: 'activity_type', message: 'Consulta cuántas actividades de cada tipo se han celebrado.' },
    editor: { metric: 'missions', group: 'editor', message: 'Compara las misiones de cada editor o selecciona uno concreto.' },
    user: { metric: 'participations', group: 'user', message: 'Compara jugadores o consulta las estadísticas de uno en particular.' },
  };

  function refreshGroups() {
    const choices = supported[metric.value] || supported.participations;
    for (const option of group.options) {
      option.disabled = !choices.includes(option.value);
      option.hidden = option.disabled;
    }
    if (!choices.includes(group.value)) {
      group.value = defaults[focus.value]?.group && choices.includes(defaults[focus.value].group)
        ? defaults[focus.value].group
        : choices[0];
    }
  }

  function refreshFocus(reset) {
    const current = defaults[focus.value] ? focus.value : 'user';
    // No hay duplicados: cada select conserva su único name y se desplaza de ubicación.
    for (const field of categoryFields) {
      const isPrimary = field.dataset.focusField === current;
      (isPrimary ? primary : additional).appendChild(field);
      field.classList.toggle('sqa-stats__category-field--primary', isPrimary);
      if (reset) field.querySelector('select').value = '';
    }
    help.textContent = defaults[current].message;
    if (reset) {
      metric.value = defaults[current].metric;
      group.value = defaults[current].group;
    }
    refreshGroups();
  }

  function refreshDates() {
    const custom = period.value === 'custom';
    const field = dateFrom.closest('.sqa-stats__field-row');
    if (field) field.hidden = !custom;
    dateFrom.disabled = !custom;
    dateTo.disabled = !custom;
  }

  focus.addEventListener('change', () => refreshFocus(true));
  metric.addEventListener('change', refreshGroups);
  period.addEventListener('change', refreshDates);
  refreshFocus(false);
  refreshDates();
})();

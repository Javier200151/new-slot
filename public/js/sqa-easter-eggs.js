(() => {
  'use strict';
  const root = document.querySelector('[data-sqa-eggs]');
  if (!root || window.__sqaEasterEggsReady) return;
  window.__sqaEasterEggsReady = true;
  const yes = key => root.dataset[key] === '1';
  const ignored = el => el && (el.closest('input, textarea, select, [contenteditable], [role="textbox"], [role="combobox"], .ql-editor, .tiptap, .CodeMirror, .monaco-editor'));
  const grenade = root.querySelector('.sqa-egg-grenade');
  const grenadeCooldownMs = Math.max(1, Math.min(86400, Number(root.dataset.grenadeCooldown) || 120)) * 1000;
  const zeus = root.querySelector('.sqa-egg-zeus');
  const konami = root.querySelector('.sqa-egg-konami');
  let grenadeTimer, zeusTimer, konamiPosition = 0;
  let lastGrenadeWithoutStorage = 0;
  const cheatKeys = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight','b','a'];

  function flashGrenade() {
    if (!yes('grenade') || !grenade) return;
    const now = Date.now();
    let last = lastGrenadeWithoutStorage;
    try { last = Number(sessionStorage.getItem('sqa-grenade-last') || 0); } catch (_) {}
    if (now - last < grenadeCooldownMs) return;
    lastGrenadeWithoutStorage = now;
    try { sessionStorage.setItem('sqa-grenade-last', String(now)); } catch (_) {}
    clearTimeout(grenadeTimer);
    grenade.hidden = false;
    grenadeTimer = setTimeout(() => { grenade.hidden = true; }, 4400);
  }

  let activeZeusAudio = null;
  function showZeus() {
    if (!yes('zeus') || !zeus || !root.dataset.zeusImage) return;
    const eye = zeus.querySelector('img');
    if (!eye) return;

    // Reiniciar exactamente la misma animación cuando se pulsa Y de nuevo.
    clearTimeout(zeusTimer);
    zeus.hidden = true;
    zeus.hidden = false;
    // Reiniciar también la aureola: comparte la misma animación que el ojo.
    zeus.classList.remove('sqa-egg-zeus--playing');
    void zeus.offsetWidth;
    zeus.classList.add('sqa-egg-zeus--playing');

    if (activeZeusAudio) {
      activeZeusAudio.pause();
      try { activeZeusAudio.currentTime = 0; } catch (_) {}
      activeZeusAudio = null;
    }
    const audioUrl = root.dataset.zeusAudio;
    if (audioUrl) {
      const audio = new Audio(audioUrl);
      activeZeusAudio = audio;
      audio.volume = 0.65;
      audio.play().catch(() => {});
    }
    zeusTimer = setTimeout(() => {
      zeus.hidden = true;
      zeus.classList.remove('sqa-egg-zeus--playing');
    }, 2250);
  }

  function setNvg(isOn) {
    document.documentElement.classList.toggle('sqa-nvg', isOn);
    try { sessionStorage.setItem('sqa-nvg-active', isOn ? '1' : '0'); } catch (_) {}
    document.querySelectorAll('[data-sqa-nvg-trigger]').forEach(n => n.setAttribute('aria-pressed', String(isOn)));
  }
  if (yes('nvg')) {
    // Solo los dos títulos aprobados. No se modifica ni reemplaza su Blade original.
    const path = location.pathname.replace(/\/+$/, '') || '/';
    const target = path === '/' ? document.querySelector('.hero-kicker')
      : path === '/eventos' ? document.querySelector('.events-hero h1') : null;
    if (target && target.childElementCount === 0) {
      const original = target.textContent;
      const pieces = original.split(/(n)/gi);
      if ((path === '/' && original.trim().toLowerCase() === 'comunidad de simulación militar')
        || (path === '/eventos' && original.trim() === 'Eventos')) {
        target.replaceChildren();
        pieces.forEach(piece => {
          if (piece.toLowerCase() !== 'n') { target.appendChild(document.createTextNode(piece)); return; }
          const span = document.createElement('span');
          span.textContent = piece;
          span.setAttribute('data-sqa-nvg-trigger', '');
          span.setAttribute('role', 'button');
          span.setAttribute('tabindex', '0');
          span.setAttribute('aria-label', 'Alternar visión nocturna');
          span.title = 'N';
          target.appendChild(span);
        });
      }
    }
    try { setNvg(sessionStorage.getItem('sqa-nvg-active') === '1'); } catch (_) {}
    document.querySelectorAll('[data-sqa-nvg-trigger]').forEach(n => {
      n.addEventListener('click', () => setNvg(!document.documentElement.classList.contains('sqa-nvg')));
      n.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault(); setNvg(!document.documentElement.classList.contains('sqa-nvg'));
        }
      });
    });
  } else {
    document.documentElement.classList.remove('sqa-nvg');
    try { sessionStorage.removeItem('sqa-nvg-active'); } catch (_) {}
  }

  function openKonami() {
    if (!yes('konami') || !konami) return;
    konami.hidden = false;
    const close = konami.querySelector('[data-sqa-egg-close]');
    if (close) close.focus();
  }
  konami?.querySelector('[data-sqa-egg-close]')?.addEventListener('click', () => { konami.hidden = true; });
  konami?.addEventListener('click', e => { if (e.target === konami) konami.hidden = true; });

  document.addEventListener('keydown', e => {
    if (e.defaultPrevented || e.repeat || e.altKey || e.ctrlKey || e.metaKey || ignored(e.target)) return;
    if (e.key === 'Escape' && konami && !konami.hidden) { konami.hidden = true; return; }
    if (e.key.toLowerCase() === 'g') flashGrenade();
    if (e.key.toLowerCase() === 'y') showZeus();
    // Las NVG solo se activan pulsando las N específicas de portada o calendario.
    if (yes('konami')) {
      const pressed = e.key.length === 1 ? e.key.toLowerCase() : e.key;
      if (pressed === cheatKeys[konamiPosition]) {
        konamiPosition++;
        if (konamiPosition === cheatKeys.length) { konamiPosition = 0; openKonami(); }
      } else {
        konamiPosition = pressed === cheatKeys[0] ? 1 : 0;
      }
    }
  });
})();

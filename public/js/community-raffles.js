/* Ruleta de premios de Squad ALPHA: lectura sincronizada por polling.
   El ganador lo elige exclusivamente el servidor y queda registrado en BD. */
(() => {
  const root = document.querySelector('[data-community-raffle]');
  if (!root) return;
  const stateUrl = root.dataset.stateUrl;
  const canvas = root.querySelector('[data-raffle-wheel]');
  const context = canvas?.getContext('2d');
  const spinButton = root.querySelector('[data-raffle-spin]');
  const prizeSelect = root.querySelector('[data-raffle-prize-select]');
  const spinError = root.querySelector('[data-raffle-spin-error]');
  let spinRequestPending = false;
  let current = null;
  let currentSpinId = null;
  let phase = 'idle';
  let serverOffset = 0;
  let animationId = null;
  let currentRotation = 0;
  const theme = ['#ef9c18','#1c3552','#b86e18','#213b38','#394c70','#805436','#365b56','#55405e'];
  let visibleSegments = [];
  const wheelWrapper = canvas.closest('.sqa-raffle-wheel-wrap') || canvas.parentElement;
  const tooltip = document.createElement('div');
  tooltip.setAttribute('role', 'tooltip');
  tooltip.setAttribute('aria-hidden', 'true');
  Object.assign(tooltip.style, {
      position: 'absolute', zIndex: '30', pointerEvents: 'none',
      padding: '9px 13px', borderRadius: '9px',
      border: '1px solid rgba(255, 184, 76, .75)',
      background: '#0c131e', color: '#fff',
      font: '800 15px/1.25 system-ui, sans-serif',
      boxShadow: '0 8px 24px rgba(0,0,0,.65)',
      whiteSpace: 'nowrap', maxWidth: 'min(240px, 85vw)',
      overflow: 'hidden', textOverflow: 'ellipsis',
      opacity: '0', visibility: 'hidden',
      transition: 'opacity .12s ease'
  });
  wheelWrapper.appendChild(tooltip);

  function wheelUsers(state) {
    return state?.spin?.status === 'spinning' ? (state.spin.wheel || []) : (state?.wheel || []);
  }

  const viewerId = Number(root.dataset.viewerId || 0);
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let visibleWheel = [];
  let lastHighlightFrame = 0;
  // Azul hielo y blanco: se distinguen incluso de las casillas naranjas.
  function highlightPulse() {
    return reducedMotion.matches ? .45 : (.5 + .5 * Math.sin(performance.now() * Math.PI * 2 / 6000));
  }
  function animateMySegment(timestamp) {
    if (viewerId && !document.hidden && !reducedMotion.matches &&
        timestamp - lastHighlightFrame >= 90 && visibleWheel.some(u => Number(u.id) === viewerId)) {
      lastHighlightFrame = timestamp;
      drawWheel(visibleWheel);
    }
    requestAnimationFrame(animateMySegment);
  }
  if (viewerId) requestAnimationFrame(animateMySegment);

  function hideTooltip() {
    tooltip.style.opacity = '0';
    tooltip.style.visibility = 'hidden';
  }

  function showTooltip(event, label) {
    const rect = wheelWrapper.getBoundingClientRect();
    tooltip.textContent = label;
    tooltip.style.visibility = 'visible';
    tooltip.style.opacity = '1';
    const x = event.clientX - rect.left + 14;
    const y = event.clientY - rect.top - 42;
    tooltip.style.left = `${Math.max(4, Math.min(x, rect.width - tooltip.offsetWidth - 4))}px`;
    tooltip.style.top = `${Math.max(4, Math.min(y, rect.height - tooltip.offsetHeight - 4))}px`;
  }

  function findSegmentFromPointer(event) {
    if (!visibleSegments.length) return null;
    // El canvas rota, pero la circunferencia conserva su centro visual.
    const rect = canvas.getBoundingClientRect();
    const dx = event.clientX - (rect.left + rect.width / 2);
    const dy = event.clientY - (rect.top + rect.height / 2);
    const distance = Math.hypot(dx, dy);
    if (distance > rect.width * (canvas.width / 2 - 11) / canvas.width || distance < rect.width * 0.068) return null;
    const full = Math.PI * 2;
    let angle = Math.atan2(dy, dx) - currentRotation * Math.PI / 180;
    angle = ((angle + Math.PI / 2) % full + full) % full - Math.PI / 2;
    return visibleSegments.find(segment => angle >= segment.begin && angle < segment.end) || null;
  }

  function drawWheel(users) {
    if (!context) return;
    visibleWheel = users;
    const n = users.length;
    const size = canvas.width;
    const center = size / 2;
    const radius = center - 11;
    context.clearRect(0, 0, size, size);
    if (!n) {
      visibleSegments = [];
      context.fillStyle = '#111b28';
      context.beginPath(); context.arc(center, center, radius, 0, Math.PI * 2); context.fill();
      context.fillStyle = '#9aabc3'; context.font = 'bold 30px system-ui';
      context.textAlign = 'center'; context.fillText('SIN PARTICIPANTES', center, center - 100);
      return;
    }
    const sweep = 2 * Math.PI / n;
    const textRadius = radius * .68;
    visibleSegments = [];
    users.forEach((member, index) => {
      const begin = -Math.PI / 2 + index * sweep;
      const end = begin + sweep;
      const isMine = viewerId > 0 && Number(member.id) === viewerId;
      const pulse = isMine ? highlightPulse() : 0;
      context.save();
      if (isMine) {
        context.shadowColor = '#9cfbff';
        context.shadowBlur = 5 + 9 * pulse;
      }
      context.beginPath(); context.moveTo(center, center);
      context.arc(center, center, radius, begin, end); context.closePath();
      context.fillStyle = theme[index % theme.length];
      context.fill();
      if (isMine) {
        // Respiración luminosa y contorno para destacar la porción del usuario.
        context.fillStyle = `rgba(167, 247, 255, ${0.09 + pulse * 0.22})`;
        context.fill();
        context.shadowColor = '#a2faff';
        context.shadowBlur = 6 + pulse * 11;
        context.lineWidth = 2 + 1.5 * pulse;
        context.strokeStyle = '#62dff1';
        context.stroke();
      }
      context.restore();
      if (!isMine) {
        context.lineWidth = 1.5;
        context.strokeStyle = '#091019';
        context.stroke();
      }


      // Cada papeleta tiene nombre, incluso cuando hay docenas de participantes.
      // Reducimos el tamaño para que quepa dentro de su propia porción.
      const mid = begin + sweep / 2;
      const availableWidth = Math.max(5, 2 * textRadius * Math.sin(sweep / 2) * .86);
      const fontSize = Math.max(7, Math.min(21, n > 36 ? 10 : n > 20 ? 13 : 21, availableWidth * .9));
      context.save();
      context.translate(center, center);
      context.rotate(mid);
      context.textAlign = 'right';
      context.textBaseline = 'middle';
      context.font = `${isMine ? 900 : 800} ${fontSize}px system-ui, sans-serif`;
      context.fillStyle = '#fff';
      context.shadowColor = isMine ? '#00eaff' : '#000';
      context.shadowBlur = isMine ? 3 + 4 * pulse : 3;
      context.fillText(String(member.nick || 'Jugador'), radius - 24, 0, radius * .78);
      context.restore();
      visibleSegments.push({ begin, end, label: String(member.nick || 'Jugador') });
    });
    context.beginPath(); context.arc(center, center, radius, 0, Math.PI * 2);
    context.lineWidth = 6; context.strokeStyle = '#f0a32a'; context.stroke();
  }

  function setRotation(value) {
    currentRotation = Number(value) || 0;
    canvas.style.transform = `rotate(${currentRotation}deg)`;
  }

  function animate(spin) {
    if (animationId !== null) cancelAnimationFrame(animationId);
    const total = Math.max(1, spin.ends_at - spin.started_at);
    const segments = Math.max(1,spin.wheel?.length || 1);
    const target = 360*(8+(spin.id%3)) - ((spin.winner_index+.5)*360/segments);
    const tick = () => {
      const progress = Math.max(0,Math.min(1,(Date.now()+serverOffset-spin.started_at)/total));
      const eased = 1-Math.pow(1-progress,4.5);
      setRotation(target*eased);
      if (progress < 1) animationId=requestAnimationFrame(tick);
      else animationId=null;
    };
    tick();
  }

  function safeText(selector, value) {
    root.querySelectorAll(selector).forEach(el => { el.textContent = value; });
  }

  function renderPrizeCards(state) {
    const list=root.querySelector('[data-raffle-prizes]');
    if (!list) return;
    list.replaceChildren();
    state.prizes.forEach(prize => {
      const card=document.createElement('article');card.className='sqa-raffle-prize';
      if (prize.image_url) {
        const img=document.createElement('img');img.src=prize.image_url;img.alt=prize.name;img.loading='lazy';card.append(img);
      }
      const div=document.createElement('div');
      const heading=document.createElement('strong');heading.textContent=prize.name;
      const meta=document.createElement('small');meta.textContent=`${prize.remaining} de ${prize.quantity} disponibles`;
      div.append(heading,meta);card.append(div);list.append(card);
    });
    if (prizeSelect) {
      const choice=prizeSelect.value;
      prizeSelect.replaceChildren();
      state.prizes.forEach(prize => {
        const option=document.createElement('option');option.value=prize.id;
        option.textContent=`${prize.name} · ${prize.remaining} restantes`;
        option.disabled=prize.remaining<=0;prizeSelect.append(option);
      });
      if (state.prizes.some(p=>String(p.id)===choice&&p.remaining>0)) prizeSelect.value=choice;
      else if(state.prizes.some(p=>p.remaining>0)) prizeSelect.value=String(state.prizes.find(p=>p.remaining>0).id);
    }
  }

  function renderWinners(state) {
    const wrap=root.querySelector('[data-raffle-winners]');if (!wrap) return;
    wrap.replaceChildren();
    if (!state.winners.length) {
      const p=document.createElement('p');p.className='sqa-raffle-empty';
      p.textContent=state.status==='closed'?'Este sorteo se ha cerrado sin ganadores.':'Los ganadores aparecerán aquí al terminar cada tirada.';
      wrap.append(p);return;
    }
    state.winners.forEach(winner=>{
      const article=document.createElement('article');article.className='sqa-raffle-winner-card';
      const star=document.createElement('span');star.textContent='🏆';star.className='sqa-raffle-winner-card__icon';
      const info=document.createElement('div');
      const label=document.createElement('strong');label.textContent=winner.nick;
      const prize=document.createElement('span');prize.textContent=winner.prize;
      const phrase=document.createElement('small');phrase.textContent=winner.phrase||'';
      info.append(label,prize,phrase);
      if (state.can_manage && ['active','closed'].includes(state.status) && root.dataset.rerollBase) {
        const form=document.createElement('form');
        form.method='POST';form.action=`${root.dataset.rerollBase}/${winner.id}`;
        form.dataset.sqaConfirm='¿Anular este resultado y volver a girar la ruleta? El ganador anterior recibirá un aviso.';
        const csrf=document.createElement('input');csrf.type='hidden';csrf.name='_token';csrf.value=root.dataset.csrf;
        const button=document.createElement('button');button.type='submit';button.className='sqa-raffle-reroll';button.textContent='Repetir ruleta';
        form.append(csrf,button);info.append(form);
        const available=state.prizes.filter(p=>p.id!==winner.prize_id&&p.remaining>0);
        if (available.length && root.dataset.changePrizeBase) {
          const changeForm=document.createElement('form');changeForm.method='POST';
          changeForm.action=`${root.dataset.changePrizeBase}/${winner.id}`;
          changeForm.className='sqa-raffle-change-prize';
          const token=document.createElement('input');token.type='hidden';token.name='_token';token.value=root.dataset.csrf;
          const select=document.createElement('select');select.name='prize_id';select.required=true;
          select.setAttribute('aria-label',`Nuevo premio para ${winner.nick}`);
          const placeholder=document.createElement('option');placeholder.value='';placeholder.textContent='Elegir otro premio';placeholder.disabled=true;placeholder.selected=true;select.append(placeholder);
          available.forEach(p=>{const option=document.createElement('option');option.value=p.id;option.textContent=`${p.name} (${p.remaining} disponibles)`;select.append(option);});
          const apply=document.createElement('button');apply.type='submit';apply.className='sqa-raffle-reroll';apply.textContent='Cambiar premio';
          changeForm.dataset.sqaConfirm=`¿Cambiar el premio de ${winner.nick}? Recibirá una notificación.`;
          changeForm.append(token,select,apply);info.append(changeForm);
        }
      }
      article.append(star,info);wrap.append(article);
    });
  }

  function render(state) {
    current=state;
    serverOffset=(state.server_now_ms||Date.now())-Date.now();
    const currentSpin=state.spin;
    const spinning=currentSpin?.status==='spinning';
    const users=wheelUsers(state);
    const spinChanged=spinning&&(currentSpinId!==currentSpin.id||phase!=='spinning');
    const rosterKey=JSON.stringify(users.map(u=>u.id));
    if (root.dataset.rosterKey!==rosterKey) {
      root.dataset.rosterKey=rosterKey;
      drawWheel(users);
      if (!spinning) setRotation(0);
    }
    if (spinChanged) {
      phase='spinning'; currentSpinId=currentSpin.id;
      drawWheel(users);animate(currentSpin);
    }
    if (!spinning) {
      if (animationId!==null) {cancelAnimationFrame(animationId);animationId=null;}
      phase='idle';
      // Conservamos el ángulo del último resultado al terminar una tirada.
      if (currentSpin && (currentSpin.id===currentSpinId || currentSpinId===null)) {
        const segments=Math.max(1,currentSpin.wheel?.length||1);
        setRotation(360*(8+(currentSpin.id%3))-((currentSpin.winner_index+.5)*360/segments));
      }
    }
    safeText('[data-raffle-participants-count]',state.participants_count);
    safeText('[data-raffle-status]',state.status==='active'?'En directo':state.status==='closed'?'Finalizado':'En preparación');
    if (spinning) {
      safeText('[data-raffle-result-title]','La ruleta está girando…');
      safeText('[data-raffle-result-message]',`Premio: ${currentSpin.prize_name}. ¡Suerte!`);
    } else if (currentSpin?.status==='completed') {
      safeText('[data-raffle-result-title]',`🏆 ${currentSpin.winner_nick} · ${currentSpin.prize_name}`);
      safeText('[data-raffle-result-message]',currentSpin.phrase||'¡Enhorabuena!');
    } else {
      safeText('[data-raffle-result-title]',state.status==='closed'?'Sorteo finalizado':'Preparados para el sorteo');
      safeText('[data-raffle-result-message]',state.status==='closed'?'Consulta los ganadores y cómo reclamar los premios.':`${state.participants_count} personas en la lista de participantes.`);
    }
    if (spinButton) spinButton.disabled=spinRequestPending||spinning||state.status!=='active'||!state.prizes.some(p=>p.remaining>0)||!state.eligible_count;
    renderPrizeCards(state);
    // No reconstruir los formularios de las tarjetas en cada actualización:
    // el gestor debe poder elegir un premio sin perder su selección.
    const winnersKey=JSON.stringify([state.status,state.can_manage,state.winners,state.prizes.map(p=>[p.id,p.remaining])]);
    if (root.dataset.winnersKey!==winnersKey) {
      root.dataset.winnersKey=winnersKey;
      renderWinners(state);
    }
  }

  async function poll() {
    if (document.hidden) return;
    try {
      const response=await fetch(stateUrl,{credentials:'same-origin',headers:{'Accept':'application/json'},cache:'no-store'});
      if (response.ok) render(await response.json());
    } catch (_) { /* Una interrupción de red no cambia el resultado del servidor. */ }
  }

  // Una sesión caducada o un error del servidor puede devolver HTML.
  // Nunca se debe intentar tratar ese HTML como el estado de la ruleta.
  async function readSpinResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.toLowerCase().includes('application/json')) {
      if (response.status === 419) throw new Error('Tu sesión ha caducado. Actualiza la página e inténtalo de nuevo.');
      if (response.status === 401 || response.status === 403 || response.redirected) throw new Error('No se pudo confirmar tu sesión. Actualiza la página e inicia sesión si es necesario.');
      if (response.status === 429) throw new Error('Se han realizado demasiadas solicitudes. Inténtalo de nuevo en un momento.');
      throw new Error(`No se ha podido completar la tirada (error ${response.status}). No se ha recibido una respuesta válida del servidor. Consulta el registro de Laravel.`);
    }
    let payload;
    try { payload = await response.json(); }
    catch (_) { throw new Error('El servidor ha enviado una respuesta incompleta. Actualiza la página antes de volver a girar.'); }
    if (!response.ok) {
      const detail = Object.values(payload?.errors || {}).flat().find(Boolean);
      throw new Error(detail || payload?.message || `No se pudo realizar la tirada (error ${response.status}).`);
    }
    if (!payload || !Array.isArray(payload.prizes) || !Array.isArray(payload.winners) || !('status' in payload)) {
      throw new Error('El resultado recibido no es válido. Actualiza la página antes de volver a girar.');
    }
    return payload;
  }

  spinButton?.addEventListener('click', async () => {
    if (spinRequestPending || !current || !prizeSelect?.value || spinButton.disabled) return;
    spinRequestPending = true;
    spinButton.disabled = true;
    if (spinError) { spinError.hidden = true; spinError.textContent = ''; }
    try {
      const response = await fetch(root.dataset.spinUrl, {
        method: 'POST', credentials: 'same-origin', redirect: 'manual',
        headers: {'X-CSRF-TOKEN': root.dataset.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({prize_id: Number(prizeSelect.value)})
      });
      render(await readSpinResponse(response));
    } catch (error) {
      if (spinError) { spinError.textContent = error.message || 'No se pudo girar la ruleta.'; spinError.hidden = false; }
      else await window.SqaDialog.alert(error.message || 'No se pudo girar la ruleta.');
    } finally {
      spinRequestPending = false;
      await poll();
      if (current && spinButton) spinButton.disabled = current.spin?.status === 'spinning' || current.status !== 'active' || !current.prizes.some(p => p.remaining > 0) || !current.eligible_count;
    }
  });

  const manager=root.querySelector('[data-raffle-manager]');
  if (manager) {
    const rows=[...manager.querySelectorAll('[data-raffle-person]')];
    const count=manager.querySelector('[data-raffle-selected-count]');
    const searchAll=manager.querySelector('[data-raffle-search-all]');
    const searchSelected=manager.querySelector('[data-raffle-search-selected]');
    function recalc() {
      const name=(searchAll?.value||'').trim().toLocaleLowerCase();
      const selected=(searchSelected?.value||'').trim().toLocaleLowerCase();
      let total=0;
      rows.forEach(row=>{
        const checked=row.querySelector('input').checked;
        if (checked) total++;
        row.hidden=!row.dataset.name.includes(name)||(selected!==''&&(!checked||!row.dataset.name.includes(selected)));
      });
      if (count) count.textContent=String(total);
    }
    rows.forEach(row=>row.querySelector('input').addEventListener('change',recalc));
    manager.querySelectorAll('[data-raffle-select-status]').forEach(button=>button.addEventListener('click',()=>{
      rows.forEach(row=>{if(row.dataset.status===button.dataset.raffleSelectStatus)row.querySelector('input').checked=true;});recalc();
    }));
    manager.querySelector('[data-raffle-clear]')?.addEventListener('click',()=>{rows.forEach(r=>r.querySelector('input').checked=false);recalc();});
    searchAll?.addEventListener('input',recalc);
    searchSelected?.addEventListener('input',recalc);
    recalc();
  }

  wheelWrapper.addEventListener('pointermove', event => {
    const segment = findSegmentFromPointer(event);
    if (!segment) { hideTooltip(); return; }
    showTooltip(event, segment.label);
  });
  wheelWrapper.addEventListener('pointerleave', hideTooltip);
  window.addEventListener('scroll', hideTooltip, { passive: true });

  const initial=document.querySelector('[data-raffle-initial]');
  if (initial) {try{render(JSON.parse(initial.textContent));}catch(_){/* ignorar */}}
  window.setInterval(poll,1800);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
})();

(() => {
  const modal = document.querySelector('[data-clip-viewer]');
  const dataNode = document.getElementById('sqa-clip-data');
  if (!modal || !dataNode) return;
  let clips;
  try { clips = JSON.parse(dataNode.textContent); } catch { return; }
  if (!Array.isArray(clips)) return;
  // El visor no debe quedar atrapado en el contexto de apilamiento de <main>.
  // Así se superpone al footer, la cabecera y cualquier otro elemento flotante.
  if (modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }
  const frame = modal.querySelector('[data-clip-frame]');
  const stage = modal.querySelector('[data-clip-stage]');
  const preview = modal.querySelector('[data-clip-preview]');
  const previewImage = modal.querySelector('[data-clip-preview-image]');
  const previewTitle = modal.querySelector('[data-clip-preview-title]');
  const title = modal.querySelector('[data-clip-title]');
  const member = modal.querySelector('[data-clip-member]');
  const link = modal.querySelector('[data-clip-original]');
  const context = modal.querySelector('[data-clip-context]');
  const help = modal.querySelector('[data-clip-tutorial]');
  const voteForm = modal.querySelector('[data-clip-vote-form]');
  const voteButton = modal.querySelector('[data-clip-vote-open]');
  const voteLabel = modal.querySelector('[data-clip-vote-label]');
  const voteClose = modal.querySelector('[data-clip-vote-close]');
  const voteHeading = modal.querySelector('[data-clip-vote-heading]');
  const voteInfo = modal.querySelector('[data-clip-vote-info]');
  const voteList = modal.querySelector('[data-clip-skull-list]');
  const voteSubmit = modal.querySelector('[data-clip-vote-submit]');
  const voteSaved = modal.querySelector('[data-clip-vote-saved]');
  const voteRadios = [...modal.querySelectorAll('[data-clip-vote-form] input[name="skulls"]')];
  const closeVote = () => {
    if (!voteForm) return;
    voteForm.hidden = true;
    voteButton?.setAttribute('aria-expanded', 'false');
  };
  const renderSkulls = () => {
    const selected = Number(voteRadios.find(radio => radio.checked)?.value || 0);
    voteRadios.forEach(radio => {
      radio.parentElement.classList.toggle('is-active', Number(radio.value) <= selected);
    });
  };
  const showVote = () => {
    const candidate = clips[index]?.candidate;
    if (!candidate || !voteForm) return;
    if (!voteForm.hidden) { closeVote(); return; }
    hideComments();
    voteForm.hidden = false;
    voteButton.setAttribute('aria-expanded', 'true');
    voteHeading.textContent = `Clip del año ${candidate.year}`;
    voteList.hidden = !candidate.can_vote;
    voteSubmit.hidden = !candidate.can_vote;
    voteSaved.hidden = true;
    if (candidate.phase === 'nominations') {
      voteInfo.textContent = `Las votaciones comienzan el ${candidate.vote_starts}. Durante esa fase podrás valorar este clip de 1 a 5 calaveras.`;
    } else if (candidate.phase !== 'voting') {
      voteInfo.textContent = 'La votación de esta edición ya ha finalizado.';
    } else if (!canInteract) {
      voteInfo.textContent = 'Inicia sesión para participar en la votación.';
    } else if (candidate.own) {
      voteInfo.textContent = 'No puedes votar tu propio candidato.';
    } else if (!candidate.eligible) {
      voteInfo.textContent = 'Tu estado no está habilitado para votar en esta edición.';
    } else {
      voteInfo.textContent = candidate.previous_vote
        ? `Tu valoración actual es ${candidate.previous_vote} de 5 calaveras. Puedes cambiarla hasta el cierre.`
        : 'Elige de 1 a 5 calaveras. Cinco es la máxima valoración.';
    }
    const selected = voteRadios.find(radio => radio.checked);
    (selected || voteClose)?.focus();
  };
  voteButton?.addEventListener('click', showVote);
  voteClose?.addEventListener('click', closeVote);
  voteRadios.forEach(radio => radio.addEventListener('change', renderSkulls));
  voteForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const clip = clips[index];
    if (!clip.candidate?.can_vote) return;
    const value = Number(voteRadios.find(radio => radio.checked)?.value);
    if (value < 1 || value > 5) return;
    voteSubmit.disabled = true;
    try {
      const result = await request(clip.candidate.vote_url, 'POST', {skulls: value});
      clip.candidate.previous_vote = Number(result.skulls);
      if (clip === clips[index]) {
        voteLabel.textContent = `${result.skulls}/5`;
        voteSaved.hidden = false;
        voteSaved.textContent = 'Valoración guardada. Puedes cambiarla hasta el cierre.';
        voteInfo.textContent = `Has dado ${result.skulls} de 5 calaveras a este clip.`;
        announce('Valoración guardada.');
        closeVote();
      }
    } catch (error) { announce(error.message); }
    finally { voteSubmit.disabled = false; }
  });
  // Reacciones y comentarios del propio clip (la reproducción sigue alojada fuera).
  const likeButton = modal.querySelector('[data-clip-like]');
  const likesNumber = modal.querySelector('[data-clip-likes]');
  const commentsButton = modal.querySelector('[data-clip-comments-open]');
  const commentsNumber = modal.querySelector('[data-clip-comments-count]');
  const commentPanel = modal.querySelector('[data-clip-comments-panel]');
  const commentList = modal.querySelector('[data-clip-comments-list]');
  const commentTotal = modal.querySelector('[data-clip-comments-total]');
  const commentMore = modal.querySelector('[data-clip-comments-more]');
  const commentForm = modal.querySelector('[data-clip-comment-form]');
  const shareButton = modal.querySelector('[data-clip-share]');
  const feedback = modal.querySelector('[data-clip-feedback]');
  const canInteract = modal.dataset.loggedIn === '1';
  const csrf = modal.dataset.csrf;
  let nextCommentsPage = null;
  let currentCommentRequest = null;
  const announce = message => {
    feedback.hidden = false;
    feedback.textContent = message;
    setTimeout(() => { feedback.hidden = true; }, 2800);
  };
  const request = async (url, method = 'GET', data = null) => {
    const response = await fetch(url, {
      method, credentials:'same-origin',
      headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
      ...(data === null ? {} : {body:JSON.stringify(data)})
    });
    if (!response.ok) {
      if (response.status === 401) throw new Error('Inicia sesión para participar.');
      const result = await response.json().catch(() => ({}));
      throw new Error(result.message || 'No se ha podido completar la acción.');
    }
    return response.json();
  };
  const makeComment = item => {
    const row = document.createElement('article');
    row.className = 'sqa-clip-comment';
    const heading = document.createElement('div');
    const user = document.createElement('strong'); user.textContent = item.member;
    const date = document.createElement('small'); date.textContent = item.date || '';
    heading.append(user,date);
    const text = document.createElement('p'); text.textContent = item.body;
    row.append(heading,text);
    if (item.can_delete) {
      const remove = document.createElement('button'); remove.type = 'button';
      remove.textContent = 'Eliminar'; remove.setAttribute('aria-label','Eliminar comentario');
      remove.addEventListener('click', async () => {
        if (!await window.SqaDialog.confirm('¿Eliminar este comentario?')) return;
        try {
          const clip = clips[index];
          const data = await request(`${clip.comments_url}/${item.id}`, 'DELETE');
          row.remove(); clip.comments = data.count;
          commentsNumber.textContent = data.count; commentTotal.textContent = `(${data.count})`;
        } catch (err) { announce(err.message); }
      });
      row.append(remove);
    }
    return row;
  };
  const loadComments = async (page = 1) => {
    const clip = clips[index];
    const expectedId = clip.id;
    const response = await request(clip.comments_url + '?page=' + page);
    if (modal.hidden || clips[index].id !== expectedId || commentPanel.hidden) return;
    if (page === 1) commentList.replaceChildren();
    response.items.forEach(item => commentList.append(makeComment(item)));
    if (page === 1 && !response.items.length) commentList.textContent = 'Todavía no hay comentarios.';
    nextCommentsPage = response.next_page;
    commentMore.hidden = !nextCommentsPage;
    commentsNumber.textContent = response.count;
    commentTotal.textContent = `(${response.count})`;
  };
  const hideComments = () => { commentPanel.hidden = true; };
  commentPanel.querySelector('[data-clip-comments-close]').addEventListener('click', hideComments);
  commentsButton.addEventListener('click', () => {
    closeVote();
    commentPanel.hidden = !commentPanel.hidden;
    if (!commentPanel.hidden) {
      commentList.textContent = 'Cargando comentarios…';
      loadComments().catch(err => { commentList.textContent = err.message; });
    }
  });
  commentMore.addEventListener('click', () => {
    if (nextCommentsPage) loadComments(nextCommentsPage).catch(err => announce(err.message));
  });
  commentForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const body = commentForm.querySelector('textarea').value.trim();
    if (!body) return;
    const button = commentForm.querySelector('button[type="submit"]'); button.disabled = true;
    try {
      const clip = clips[index];
      const data = await request(clip.comment_create_url, 'POST', {body});
      if (clip !== clips[index]) return;
      if (commentList.textContent === 'Todavía no hay comentarios.') commentList.replaceChildren();
      commentList.prepend(makeComment(data.item));
      clip.comments = data.count;
      commentsNumber.textContent = data.count; commentTotal.textContent = `(${data.count})`;
      commentForm.reset();
    } catch(err) { announce(err.message); } finally { button.disabled = false; }
  });
  likeButton.addEventListener('click', async () => {
    if (!canInteract) { announce('Inicia sesión para dar me gusta.'); return; }
    likeButton.disabled = true;
    const clip = clips[index];
    try {
      const data = await request(clip.like_url, 'POST');
      clip.liked = data.liked; clip.likes = data.count;
      if (clip === clips[index]) {
        likeButton.setAttribute('aria-pressed', String(data.liked));
        likesNumber.textContent = data.count;
      }
    } catch(err) { announce(err.message); } finally { likeButton.disabled = false; }
  });
  shareButton.addEventListener('click', async () => {
    const url = clips[index].share;
    try {
      if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(url);
      else {
        const input = document.createElement('textarea'); input.value = url;
        document.body.append(input); input.select();
        if (!document.execCommand('copy')) throw new Error('No se pudo copiar');
        input.remove();
      }
      announce('Enlace del clip copiado.');
    } catch { announce('No se pudo copiar el enlace.'); }
  });

  let helpTimeout;
  const openButtons = document.querySelectorAll('[data-clip-open]');
  let index = 0;
  let touchStart = null;
  let lastActive = null;
  let lastWheel = 0;
  let sliding = false;
  let slideTimer = null;
  const mobile = () => window.matchMedia('(max-width:767px)').matches;
  const reducedMotion = () => window.matchMedia('(prefers-reduced-motion:reduce)').matches;
  const resetSlide = () => { clearTimeout(slideTimer); sliding = false; touchStart = null; stage.style.transition = ''; stage.style.transform = ''; stage.style.opacity = ''; };
  const paintPreview = offset => {
    if (!preview || clips.length < 2) return;
    const target = clips[(index + offset + clips.length) % clips.length];
    previewTitle.textContent = target.title || 'Siguiente clip';
    if (target.thumbnail) { previewImage.src = target.thumbnail; previewImage.hidden = false; }
    else { previewImage.removeAttribute('src'); previewImage.hidden = true; }
  };
  const slideTo = offset => {
    if (sliding || clips.length < 2) return;
    if (!mobile() || reducedMotion()) { move(offset); return; }
    sliding = true; touchStart = null; paintPreview(offset);
    const amount = Math.max(stage.clientHeight, window.innerHeight);
    stage.style.transition = 'transform 175ms cubic-bezier(.22,.7,.26,1)';
    stage.style.transform = `translate3d(0, ${offset > 0 ? amount : -amount}px, 0)`;
    slideTimer = setTimeout(() => {
      index = (index + offset + clips.length) % clips.length;
      paint();
      stage.style.transition = 'none';
      stage.style.transform = `translate3d(0, ${offset > 0 ? -amount : amount}px, 0)`;
      void stage.offsetHeight;
      stage.style.transition = 'transform 225ms cubic-bezier(.2,.75,.2,1)';
      stage.style.transform = 'translate3d(0,0,0)';
      slideTimer = setTimeout(resetSlide, 240);
    }, 185);
  };
  const paint = () => {
    const clip = clips[index];
    hideComments();
    likesNumber.textContent = clip.likes || 0;
    commentsNumber.textContent = clip.comments || 0;
    likeButton.setAttribute('aria-pressed', String(Boolean(clip.liked)));
    const candidate = clip.candidate;
    likeButton.hidden = Boolean(candidate);
    voteButton.hidden = !candidate;
    closeVote();
    if (candidate) {
      voteLabel.textContent = candidate.previous_vote && candidate.phase === 'voting'
        ? `${candidate.previous_vote}/5` : (candidate.phase === 'voting' ? 'Votar' : 'Calaveras');
      voteForm.action = candidate.vote_url;
      voteRadios.forEach(radio => { radio.checked = Number(radio.value) === Number(candidate.previous_vote); });
      renderSkulls();
    }
    feedback.hidden = true;
    frame.removeAttribute('src'); // Stop the previous clip before opening the next.
    title.textContent = clip.title || 'Clip';
    member.textContent = clip.member || 'Squad ALPHA';
    link.href = clip.url;
    if (context) context.textContent = [clip.event, clip.protagonists?.length ? `Protagonistas: ${clip.protagonists.join(', ')}` : null].filter(Boolean).join(' · ');
    frame.title = clip.title || 'Clip de la comunidad';
    frame.src = clip.embed;
    modal.querySelector('[data-clip-prev]').disabled = clips.length < 2;
    modal.querySelector('[data-clip-next]').disabled = clips.length < 2;
  };
  const move = offset => { if (clips.length < 2) return; index = (index + offset + clips.length) % clips.length; paint(); };
  const close = () => { hideComments(); closeVote(); clearTimeout(helpTimeout); resetSlide(); modal.hidden = true; frame.removeAttribute('src'); document.body.classList.remove('sqa-clip-lock'); lastActive?.focus?.(); };
  const openClip = (clipId, trigger) => {
    const next = clips.findIndex(clip => String(clip.id) === String(clipId));
    if (next < 0) return;
    lastActive = trigger || document.activeElement;
    resetSlide();
    index = next;
    modal.hidden = false;
    document.body.classList.add('sqa-clip-lock');
    paint();
    if (help) {
      let seen = false;
      try { seen = sessionStorage.getItem('sqa-clip-swipe-seen') === '1'; } catch {}
      help.classList.toggle('is-visible', window.matchMedia('(max-width:767px)').matches && !seen);
      if (help.classList.contains('is-visible')) {
        try { sessionStorage.setItem('sqa-clip-swipe-seen', '1'); } catch {}
        helpTimeout = setTimeout(() => help.classList.remove('is-visible'), 2500);
      }
    }
    modal.querySelector('[data-clip-close]').focus();
  };
  openButtons.forEach(button => button.addEventListener('click', () => openClip(button.dataset.clipOpen, button)));
  if (modal.dataset.autoClip) openClip(modal.dataset.autoClip);
  modal.querySelector('[data-clip-close]').addEventListener('click', close);
  modal.querySelector('[data-clip-prev]').addEventListener('click', () => move(-1));
  modal.querySelector('[data-clip-next]').addEventListener('click', () => move(1));
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
  document.addEventListener('keydown', event => {
    if (modal.hidden) return;
    if (event.key === 'Escape') { if (!voteForm.hidden) { closeVote(); voteButton.focus(); } else close(); return; }
    if (event.target.closest('textarea, input, button, [data-clip-comments-panel], [data-clip-vote-form]')) return;
    if (['ArrowRight', 'ArrowDown'].includes(event.key)) { event.preventDefault(); move(1); }
    if (['ArrowLeft', 'ArrowUp'].includes(event.key)) { event.preventDefault(); move(-1); }
    if (event.key === 'Tab') {
      const nodes = [...modal.querySelectorAll('button:not([disabled]), a')];
      const current = nodes.indexOf(document.activeElement);
      if (event.shiftKey && current === 0) { event.preventDefault(); nodes[nodes.length - 1]?.focus(); }
      if (!event.shiftKey && current === nodes.length - 1) { event.preventDefault(); nodes[0]?.focus(); }
    }
  });
  // Los iframes externos interceptan gestos sobre sus controles. Las bandas
  // laterales y las zonas libres permiten deslizar sin bloquear la reproducción.
  const canSwipe = target => mobile() && !sliding && commentPanel.hidden && voteForm.hidden
    && !target.closest('button, a, input, textarea, label, [data-clip-vote-form]');
  modal.addEventListener('touchstart', event => {
    if (modal.hidden || event.touches.length !== 1 || !canSwipe(event.target)) { touchStart = null; return; }
    const touch = event.touches[0];
    touchStart = { y:touch.clientY, x:touch.clientX, at:performance.now(), delta:0 };
    stage.style.transition = 'none';
  }, { passive:true });
  modal.addEventListener('touchmove', event => {
    if (!touchStart || event.touches.length !== 1 || clips.length < 2) return;
    const touch = event.touches[0];
    const dy = touch.clientY - touchStart.y;
    const dx = touch.clientX - touchStart.x;
    if (Math.abs(dx) > Math.abs(dy) * 1.15) return;
    if (Math.abs(dy) > 9) {
      event.preventDefault();
      const limit = stage.clientHeight * .85;
      const dragged = Math.max(-limit, Math.min(limit, dy));
      touchStart.delta = dragged;
      paintPreview(dragged >= 0 ? 1 : -1);
      stage.style.transform = `translate3d(0, ${dragged}px, 0)`;
    }
  }, { passive:false });
  const finishSwipe = (event, cancelled = false) => {
    if (!touchStart) return;
    const start = touchStart;
    touchStart = null;
    const finalTouch = event.changedTouches?.[0];
    const delta = start.delta || (finalTouch ? finalTouch.clientY - start.y : 0);
    const elapsed = Math.max(50, performance.now() - start.at);
    const verticalEnough = Math.abs(delta) > Math.max(56, stage.clientHeight * .11);
    const flick = Math.abs(delta) > 28 && Math.abs(delta / elapsed) > .48;
    if (!cancelled && clips.length > 1 && (verticalEnough || flick)) {
      // Arrastrar hacia abajo: siguiente. Hacia arriba: anterior.
      slideTo(delta > 0 ? 1 : -1);
    } else {
      stage.style.transition = reducedMotion() ? 'none' : 'transform 180ms ease-out';
      stage.style.transform = 'translate3d(0,0,0)';
      clearTimeout(slideTimer);
      slideTimer = setTimeout(resetSlide, 195);
    }
  };
  modal.addEventListener('touchend', event => finishSwipe(event), { passive:true });
  modal.addEventListener('touchcancel', event => finishSwipe(event, true), { passive:true });
  modal.addEventListener('wheel', event => {
    if (modal.hidden || !commentPanel.hidden || !voteForm.hidden || !mobile() || Math.abs(event.deltaY) < 30) return;
    event.preventDefault();
    if (Date.now() - lastWheel < 650 || sliding) return;
    lastWheel = Date.now();
    slideTo(event.deltaY > 0 ? 1 : -1);
  }, {passive:false});
  document.querySelectorAll('[data-clip-lookup]').forEach(root => {
    const input = root.querySelector('input[type="text"]');
    const hidden = root.querySelector('input[type="hidden"]');
    const results = root.querySelector('[role="listbox"]');
    let timer, controller;
    input.addEventListener('input', () => {
      hidden.value = '';
      clearTimeout(timer); controller?.abort();
      results.replaceChildren(); results.hidden = true; input.setAttribute('aria-expanded', 'false');
      const q = input.value.trim();
      if (q.length < 2) return;
      timer = setTimeout(async () => {
        controller = new AbortController();
        try {
          const response = await fetch(root.dataset.url + '?' + new URLSearchParams({kind:root.dataset.clipLookup,q}), {credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});
          if (!response.ok) return;
          const items = await response.json();
          if (q !== input.value.trim()) return;
          items.forEach(item => {
            const option = document.createElement('button');
            option.type = 'button'; option.setAttribute('role','option');
            option.textContent = item.label;
            option.addEventListener('click', () => {
              hidden.value = item.id; input.value = item.label;
              results.hidden = true; input.setAttribute('aria-expanded','false');
            });
            results.append(option);
          });
          results.hidden = !items.length; input.setAttribute('aria-expanded',String(Boolean(items.length)));
        } catch (error) { if (error.name !== 'AbortError') results.hidden = true; }
      }, 250);
    });
    input.addEventListener('keydown', event => { if (event.key === 'Escape') { results.hidden=true; input.setAttribute('aria-expanded','false'); } });
    document.addEventListener('click', event => { if (!root.contains(event.target)) { results.hidden=true; input.setAttribute('aria-expanded','false'); } });
  });
})();


// Búsqueda de protagonistas múltiples; la selección se envía como IDs validados en el servidor.
document.querySelectorAll('[data-clip-multi-lookup]').forEach(root => {
  const input = root.querySelector('input[type="text"]');
  const hidden = root.querySelector('input[type="hidden"]');
  const choices = root.querySelector('[role="listbox"]');
  const selected = root.querySelector('[data-clip-selected-users]');
  const ids = new Map();
  let timer = null;
  let controller = null;
  function render() {
    hidden.value = [...ids.keys()].join(',');
    selected.replaceChildren();
    ids.forEach((nick,id) => {
      const chip = document.createElement('button'); chip.type = 'button';
      chip.className = 'sqa-clip-user-chip';
      chip.textContent = `${nick} ×`;
      chip.setAttribute('aria-label', `Quitar ${nick}`);
      chip.addEventListener('click', () => { ids.delete(id); render(); });
      selected.append(chip);
    });
  }
  input.addEventListener('input', () => {
    clearTimeout(timer); controller?.abort();
    choices.replaceChildren(); choices.hidden = true;
    const q = input.value.trim();
    if (q.length < 2) return;
    timer = setTimeout(async () => {
      controller = new AbortController();
      try {
        const response = await fetch(root.dataset.url + '?' + new URLSearchParams({kind:'users',q}),
          {credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});
        if (!response.ok || q !== input.value.trim()) return;
        const items = await response.json();
        items.forEach(item => {
          if (ids.has(String(item.id))) return;
          const option = document.createElement('button'); option.type='button';
          option.setAttribute('role','option'); option.textContent=item.label;
          option.addEventListener('click', () => {
            if (ids.size >= 12) return;
            ids.set(String(item.id),item.label); render(); input.value=''; choices.hidden=true;
          });
          choices.append(option);
        });
        choices.hidden = choices.childElementCount === 0;
      } catch (err) { if (err.name !== 'AbortError') choices.hidden = true; }
    }, 200);
  });
});

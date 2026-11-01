(() => {
  const modal = document.querySelector('[data-clip-viewer]');
  const dataNode = document.getElementById('sqa-clip-data');
  if (!modal || !dataNode) return;
  let clips;
  try { clips = JSON.parse(dataNode.textContent); } catch { return; }
  if (!Array.isArray(clips)) return;
  const frame = modal.querySelector('[data-clip-frame]');
  const title = modal.querySelector('[data-clip-title]');
  const member = modal.querySelector('[data-clip-member]');
  const link = modal.querySelector('[data-clip-original]');
  const context = modal.querySelector('[data-clip-context]');
  const help = modal.querySelector('[data-clip-tutorial]');
  const voteForm = modal.querySelector('[data-clip-vote-form]');
  const voteDataNode = document.getElementById('sqa-clip-vote-data');
  let voteUrls = {};
  try { if (voteDataNode) voteUrls = JSON.parse(voteDataNode.textContent); } catch {}
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
        if (!confirm('¿Eliminar este comentario?')) return;
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
  const paint = () => {
    const clip = clips[index];
    hideComments();
    likesNumber.textContent = clip.likes || 0;
    commentsNumber.textContent = clip.comments || 0;
    likeButton.setAttribute('aria-pressed', String(Boolean(clip.liked)));
    feedback.hidden = true;
    frame.removeAttribute('src'); // Stop the previous clip before opening the next.
    title.textContent = clip.title || 'Clip';
    member.textContent = clip.member || 'Squad ALPHA';
    link.href = clip.url;
    if (voteForm) {
      const voteUrl = voteUrls[String(clip.id)];
      voteForm.hidden = !voteUrl;
      if (voteUrl) { voteForm.action = voteUrl; voteForm.querySelectorAll('input[name="skulls"]').forEach(el => {el.checked = false;}); }
    }
    if (context) context.textContent = [clip.event, clip.protagonist ? `Protagonista: ${clip.protagonist}` : null].filter(Boolean).join(' · ');
    frame.title = clip.title || 'Clip de la comunidad';
    frame.src = clip.embed;
    modal.querySelector('[data-clip-prev]').disabled = clips.length < 2;
    modal.querySelector('[data-clip-next]').disabled = clips.length < 2;
  };
  const move = offset => { if (clips.length < 2) return; index = (index + offset + clips.length) % clips.length; paint(); };
  const close = () => { hideComments(); clearTimeout(helpTimeout); modal.hidden = true; frame.removeAttribute('src'); document.body.classList.remove('sqa-clip-lock'); lastActive?.focus?.(); };
  const openClip = (clipId, trigger) => {
    const next = clips.findIndex(clip => String(clip.id) === String(clipId));
    if (next < 0) return;
    lastActive = trigger || document.activeElement;
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
    if (event.key === 'Escape') close();
    if (event.target.closest('textarea, input, [data-clip-comments-panel]')) return;
    if (['ArrowRight', 'ArrowDown'].includes(event.key)) { event.preventDefault(); move(1); }
    if (['ArrowLeft', 'ArrowUp'].includes(event.key)) { event.preventDefault(); move(-1); }
    if (event.key === 'Tab') {
      const nodes = [...modal.querySelectorAll('button:not([disabled]), a')];
      const current = nodes.indexOf(document.activeElement);
      if (event.shiftKey && current === 0) { event.preventDefault(); nodes[nodes.length - 1]?.focus(); }
      if (!event.shiftKey && current === nodes.length - 1) { event.preventDefault(); nodes[0]?.focus(); }
    }
  });
  modal.addEventListener('touchstart', event => {
    if (event.target.closest('[data-clip-comments-panel]')) return;
    if (event.touches.length === 1) touchStart = {y:event.touches[0].clientY, x:event.touches[0].clientX};
  }, {passive:true});
  modal.addEventListener('touchend', event => {
    if (event.target.closest('[data-clip-comments-panel]')) return;
    if (!touchStart || !event.changedTouches.length) return;
    const delta = event.changedTouches[0].clientY - touchStart.y;
    const sideways = Math.abs(event.changedTouches[0].clientX - touchStart.x);
    touchStart = null;
    if (Math.abs(delta) > 65 && Math.abs(delta) > sideways * 1.3) move(delta < 0 ? 1 : -1);
  }, {passive:true});
  modal.addEventListener('wheel', event => {
    if (event.target.closest('[data-clip-comments-panel]') || window.innerWidth > 767 || Math.abs(event.deltaY) < 30) return;
    event.preventDefault();
    if (Date.now() - lastWheel < 650) return;
    lastWheel = Date.now();
    move(event.deltaY > 0 ? 1 : -1);
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

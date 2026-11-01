(() => {
  const root = document.querySelector('[data-vod-event-lookup]');
  if (!root) return;
  const input = root.querySelector('input[type="search"]');
  const hidden = root.querySelector('input[type="hidden"]');
  const results = root.querySelector('[role="listbox"]');
  let timer, controller;
  input.addEventListener('input', () => {
    hidden.value = '';
    clearTimeout(timer); controller?.abort();
    results.replaceChildren(); results.hidden = true;
    input.setAttribute('aria-expanded','false');
    const q = input.value.trim();
    if (q.length < 2) return;
    timer = setTimeout(async () => {
      controller = new AbortController();
      try {
        const res = await fetch(root.dataset.url+'?'+new URLSearchParams({q}), {
          credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}
        });
        if (!res.ok) return;
        const items = await res.json();
        if (q !== input.value.trim()) return;
        for (const item of items) {
          const option = document.createElement('button'); option.type='button';
          option.setAttribute('role','option'); option.textContent=item.label;
          option.addEventListener('click', () => {
            input.value=item.label; hidden.value=item.id;
            results.hidden=true; input.setAttribute('aria-expanded','false');
          });
          results.append(option);
        }
        results.hidden = items.length===0;
        input.setAttribute('aria-expanded',String(items.length>0));
      } catch(err) { if (err.name!=='AbortError') results.hidden=true; }
    },250);
  });
  document.addEventListener('click', e => {
    if (!root.contains(e.target)) {results.hidden=true;input.setAttribute('aria-expanded','false');}
  });
})();

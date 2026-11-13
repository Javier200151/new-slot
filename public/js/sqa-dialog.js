/* Confirmaciones públicas de Squad ALPHA. Sin sobrescribir diálogos nativos de otros componentes. */
(() => {
  'use strict';
  const ready = () => {
    if (window.SqaDialog) return;
    const modal = document.createElement('div');
    modal.className = 'sqa-dialog';
    modal.hidden = true;
    modal.innerHTML = `<div class="sqa-dialog__backdrop" data-sqa-dismiss></div>
      <section class="sqa-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="sqa-dialog-title" aria-describedby="sqa-dialog-message" tabindex="-1">
        <div class="sqa-dialog__header"><span class="sqa-dialog__icon" aria-hidden="true">?</span><h2 id="sqa-dialog-title">¿Confirmar acción?</h2></div>
        <p id="sqa-dialog-message"></p>
        <div class="sqa-dialog__actions"><button class="sqa-dialog__cancel" type="button" data-sqa-cancel>Cancelar</button><button class="sqa-dialog__confirm" type="button" data-sqa-accept>Confirmar</button></div>
      </section>`;
    document.body.append(modal);
    const panel = modal.querySelector('.sqa-dialog__panel');
    const messageNode = modal.querySelector('#sqa-dialog-message');
    const titleNode = modal.querySelector('#sqa-dialog-title');
    const cancel = modal.querySelector('[data-sqa-cancel]');
    const accept = modal.querySelector('[data-sqa-accept]');
    let pending = null;
    const finish = value => {
      if (!pending) return;
      const previous = pending;
      pending = null;
      modal.hidden = true;
      document.body.classList.remove('sqa-dialog-open');
      document.removeEventListener('keydown', onKeys, true);
      previous.resolve(value);
      if (previous.focus?.isConnected) previous.focus.focus({preventScroll:true});
    };
    const onKeys = event => {
      if (event.key === 'Escape') { event.preventDefault(); finish(false); return; }
      if (event.key !== 'Tab') return;
      const buttons = [cancel, accept].filter(el => !el.hidden);
      const active = document.activeElement;
      if (event.shiftKey && active === buttons[0]) { event.preventDefault(); buttons.at(-1).focus(); }
      if (!event.shiftKey && active === buttons.at(-1)) { event.preventDefault(); buttons[0].focus(); }
    };
    cancel.addEventListener('click', () => finish(false));
    accept.addEventListener('click', () => finish(true));
    modal.querySelector('[data-sqa-dismiss]').addEventListener('click', () => finish(false));
    const open = (message, options={}) => {
      if (pending) return Promise.resolve(false);
      return new Promise(resolve => {
        pending={resolve, focus: document.activeElement};
        titleNode.textContent=options.title || '¿Confirmar acción?';
        messageNode.textContent=String(message || '¿Quieres continuar?');
        accept.textContent=options.acceptLabel || 'Confirmar';
        cancel.hidden=Boolean(options.notice);
        accept.classList.toggle('sqa-dialog__confirm--danger', Boolean(options.danger));
        modal.hidden=false;
        document.body.classList.add('sqa-dialog-open');
        document.addEventListener('keydown', onKeys, true);
        (options.notice ? accept : cancel).focus({preventScroll:true});
      });
    };
    window.SqaDialog={ confirm:(message,options={})=>open(message,options), alert:(message)=>open(message,{title:'Aviso',acceptLabel:'Aceptar',notice:true}) };
    // Intercepción de formularios, incluidos los creados posteriormente mediante JS.
    const approved = new WeakSet();
    document.addEventListener('submit', async event => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || approved.has(form)) return;
      const message = form.dataset.sqaConfirm ||
        (form.dataset.sqaConfirmFrom ? form.dataset[form.dataset.sqaConfirmFrom] : null);
      if (!message) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      const submitter = event.submitter;
      if (!await window.SqaDialog.confirm(message,{danger:/eliminar|retirar|desvincular|anular|cerrar/i.test(message)})) return;
      if (!form.isConnected) return;
      approved.add(form);
      try {
        if (form.requestSubmit) form.requestSubmit(submitter?.isConnected ? submitter : undefined);
        else form.submit();
      } finally { approved.delete(form); }
    }, true);
    // Confirmaciones que estaban asociadas a un botón y no al formulario.
    document.addEventListener('click', async event => {
      const button = event.target.closest?.('button[data-sqa-confirm]');
      if (!button || button.closest('form')?.dataset.sqaConfirm) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      const message=button.dataset.sqaConfirm;
      if (!await window.SqaDialog.confirm(message,{danger:/eliminar|retirar|desvincular|anular|cerrar/i.test(message)})) return;
      const form=button.form;
      if (form) {
        approved.add(form);
        try { if(form.requestSubmit) form.requestSubmit(button); else form.submit(); }
        finally { approved.delete(form); }
      }
    }, true);
  };
  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',ready,{once:true});
  else ready();
})();

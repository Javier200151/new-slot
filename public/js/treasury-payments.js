(() => {
  'use strict';

  const trigger = document.querySelector('[data-treasury-payment-open]');
  const dialog = document.getElementById('treasury-payment-dialog');
  if (!trigger || !dialog) return;

  const close = dialog.querySelector('[data-treasury-payment-close]');
  const copyButtons = dialog.querySelectorAll('[data-treasury-copy-iban], [data-treasury-copy-beneficiary]');
  const feedback = dialog.querySelector('[data-treasury-payment-feedback]');

  trigger.addEventListener('click', () => {
    feedback.textContent = '';
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  });

  close?.addEventListener('click', () => {
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  });

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog && typeof dialog.close === 'function') dialog.close();
  });

  const copyText = async (value) => {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value);
      return;
    }

    const input = document.createElement('textarea');
    input.value = value;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    dialog.append(input);
    input.select();
    const copied = document.execCommand('copy');
    input.remove();
    if (!copied) throw new Error('No se ha podido copiar');
  };

  copyButtons.forEach(button => button.addEventListener('click', async () => {
    const isIban = button.hasAttribute('data-treasury-copy-iban');
    const value = isIban ? button.dataset.iban : button.dataset.beneficiary;
    if (!value) return;

    try {
      await copyText(value);
      feedback.textContent = isIban ? 'IBAN copiado al portapapeles.' : 'Destinatario copiado al portapapeles.';
    } catch {
      feedback.textContent = 'No se ha podido copiar. Selecciona el texto para copiarlo manualmente.';
    }
  }));
})();

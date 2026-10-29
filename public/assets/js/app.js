document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('#primary-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      nav.classList.toggle('is-open', open);
    });
  }

  const address = document.querySelector('#address-field');
  const fulfillmentInputs = document.querySelectorAll('input[name="fulfillment_method"]');
  const syncAddress = () => {
    if (!address || !fulfillmentInputs.length) return;
    const delivery = document.querySelector('input[name="fulfillment_method"]:checked')?.value === 'delivery';
    address.hidden = !delivery;
    const textarea = address.querySelector('textarea');
    if (textarea) textarea.required = delivery;
  };
  fulfillmentInputs.forEach((input) => input.addEventListener('change', syncAddress));
  syncAddress();

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.dataset.copy || '';
      try {
        await navigator.clipboard.writeText(value);
      } catch (_) {
        const field = document.createElement('textarea');
        field.value = value;
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.append(field);
        field.select();
        document.execCommand('copy');
        field.remove();
      }
      button.textContent = 'Disalin';
      window.setTimeout(() => { button.textContent = 'Salin'; }, 1500);
    });
  });
});

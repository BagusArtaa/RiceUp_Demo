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

  // Payment method -> channel section toggle (checkout page)
  const paymentMethodInputs = document.querySelectorAll('input[name="payment_method"]');
  const channelBankSection = document.querySelector('#channels-bank_transfer');
  const channelQrisSection = document.querySelector('#channels-qris');
  const syncChannels = (clearInactive = false) => {
    const selected = document.querySelector('input[name="payment_method"]:checked')?.value;
    if (channelBankSection) {
      const showBank = selected === 'bank_transfer';
      channelBankSection.hidden = !showBank;
      channelBankSection.querySelectorAll('input[name="payment_channel_id"]').forEach((r) => {
        r.disabled = !showBank;
        if (!showBank && clearInactive) r.checked = false;
      });
    }
    if (channelQrisSection) {
      const showQris = selected === 'qris';
      channelQrisSection.hidden = !showQris;
      const qrisRadios = channelQrisSection.querySelectorAll('input[name="payment_channel_id"]');
      qrisRadios.forEach((r) => {
        r.disabled = !showQris;
        if (!showQris && clearInactive) r.checked = false;
      });
      if (showQris && qrisRadios.length === 1 && !Array.from(qrisRadios).some((r) => r.checked)) {
        qrisRadios[0].checked = true;
      }
    }
  };
  paymentMethodInputs.forEach((input) => input.addEventListener('change', () => syncChannels(true)));
  if (paymentMethodInputs.length) syncChannels(false);

  // Admin: channel type toggle (payment channels form)
  const channelTypeSelect = document.querySelector('#channel-type-select');
  const bankFields = document.querySelector('#bank-fields');
  const qrisFields = document.querySelector('#qris-fields');
  const syncChannelType = () => {
    if (!channelTypeSelect || !bankFields || !qrisFields) return;
    const isQris = channelTypeSelect.value === 'qris';
    bankFields.hidden = isQris;
    qrisFields.hidden = !isQris;
    const bankName = bankFields.querySelector('input[name="bank_name"]');
    const accountNum = bankFields.querySelector('input[name="account_number"]');
    const accountHolder = bankFields.querySelector('input[name="account_holder"]');
    const qrisImage = qrisFields.querySelector('input[name="qris_image"]');
    if (bankName) bankName.required = !isQris;
    if (accountNum) accountNum.required = !isQris;
    if (accountHolder) accountHolder.required = !isQris;
    if (qrisImage) qrisImage.required = isQris;
  };
  if (channelTypeSelect) {
    channelTypeSelect.addEventListener('change', syncChannelType);
    syncChannelType();
  }
});

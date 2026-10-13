'use strict';
(() => {
  const form = document.querySelector('[data-game-admin]');
  if (!form) return;
  const list = form.querySelector('[data-prize-list]');
  const template = form.querySelector('[data-prize-template]');
  const add = form.querySelector('[data-prize-add]');
  const totalElement = form.querySelector('[data-prize-total-number]');
  const errorElement = form.querySelector('[data-prize-error]');
  let nextIndex = Math.max(-1, ...[...list.querySelectorAll('[name]')].map(el => Number(el.name.match(/^prizes\[(\d+)\]/)?.[1] ?? -1))) + 1;
  const rows = () => [...list.querySelectorAll('[data-prize]')];
  const percent = value => {
    const match = value.match(/^(\d{1,3})(?:\.(\d{1,2}))?$/);
    if (!match) return null;
    const valueInBasisPoints = Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0'));
    return valueInBasisPoints <= 10000 ? valueInBasisPoints : null;
  };

  function updateTotal() {
    let total = 0;
    rows().forEach((row, index) => {
      row.querySelector('[data-prize-number]').textContent = String(index + 1);
      const enabled = row.querySelector('[data-prize-enabled]').checked;
      const weight = row.querySelector('[data-prize-weight]');
      const basisPoints = percent(weight.value);
      row.classList.toggle('hgra-inactive', !enabled);
      weight.setCustomValidity(basisPoints === null ? form.dataset.weightError : '');
      if (enabled && basisPoints !== null) total += basisPoints;
      row.querySelector('[data-prize-delete]').disabled = rows().length < 2;
    });
    totalElement.textContent = `${(total / 100).toFixed(2)}%`;
    totalElement.parentElement.classList.toggle('hgra-invalid-total', total !== 10000);
    totalElement.parentElement.setAttribute('aria-live', 'polite');
    add.disabled = rows().length >= 12;
    if (total === 10000) errorElement.hidden = true;
    return total;
  }

  function bindPrize(row) {
    const kind = row.querySelector('[data-prize-kind]');
    const item = row.querySelector('[data-prize-item]');
    const size = row.querySelector('[data-prize-size]');
    const amount = row.querySelector('[name$="[amount]"]');
    const itemOptions = [...item.options].filter(option => option.dataset.kind).map(option => ({
      kind: option.dataset.kind, value: option.value, text: option.textContent, sizes: option.dataset.sizes
    }));
    let discountAmount = amount.value || '5000';
    function validateItem() {
      item.setCustomValidity(item.required && item.value === '0' ? form.dataset.select : '');
    }
    function rebuildSizes(keep = size.value) {
      const selected = item.selectedOptions[0];
      let choices = [''];
      if (kind.value === 'tea') {
        try { choices = JSON.parse(selected?.dataset.sizes || '[""]'); } catch (_) { choices = ['']; }
      }
      size.replaceChildren();
      choices.forEach(value => { const option = new Option(value || form.dataset.single, value); option.selected = value === keep; size.add(option); });
      if (!choices.includes(keep)) size.value = choices[0] || '';
      const sizeField = row.querySelector('[data-prize-size-field]');
      sizeField.hidden = kind.value !== 'tea' || choices.length === 1 && choices[0] === '';
    }
    function updateKind(initial = false) {
      const selected = initial ? item.dataset.selected : '0';
      item.replaceChildren(new Option(form.dataset.select, '0'));
      itemOptions.filter(option => option.kind === kind.value).forEach(record => {
        const option = new Option(record.text, record.value);
        option.dataset.sizes = record.sizes;
        option.selected = record.value === selected;
        item.add(option);
      });
      row.querySelector('[data-prize-item-field]').hidden = kind.value === 'discount';
      row.querySelector('[data-prize-amount-field]').hidden = kind.value !== 'discount';
      item.required = kind.value !== 'discount' && row.querySelector('[data-prize-enabled]').checked;
      if (kind.value !== 'discount') { if (Number(amount.value) > 0) discountAmount = amount.value; amount.value = '0'; }
      else { amount.value = discountAmount; item.value = '0'; }
      rebuildSizes(initial ? size.dataset.selected : '');
      validateItem();
    }
    kind.addEventListener('change', () => updateKind());
    item.addEventListener('change', () => { rebuildSizes(''); validateItem(); });
    amount.addEventListener('input', () => { if (kind.value === 'discount') discountAmount = amount.value; });
    row.querySelector('[data-prize-weight]').addEventListener('input', updateTotal);
    row.querySelector('[data-prize-enabled]').addEventListener('change', () => {
      item.required = kind.value !== 'discount' && row.querySelector('[data-prize-enabled]').checked;
      validateItem();
      updateTotal();
    });
    row.querySelector('[data-prize-delete]').addEventListener('click', () => { if (rows().length > 1) { row.remove(); updateTotal(); } });
    updateKind(true);
  }

  rows().forEach(bindPrize);
  add.addEventListener('click', () => {
    if (rows().length >= 12) { errorElement.textContent = form.dataset.limitError; errorElement.hidden = false; return; }
    const fragment = template.content.cloneNode(true);
    fragment.querySelectorAll('[name]').forEach(element => { element.name = element.name.replace('__INDEX__', String(nextIndex)); });
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    const id = 'prize_' + [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
    fragment.querySelector('[name$="[id]"]').value = id;
    const row = fragment.querySelector('[data-prize]');
    list.append(fragment); nextIndex++;
    bindPrize(row); updateTotal();
    row.querySelector('[data-prize-kind]').focus();
  });

  form.querySelectorAll('[data-media-field]').forEach(field => {
    const upload = field.querySelector('[data-media-upload]');
    const remove = field.querySelector('[data-media-remove]');
    const image = field.querySelector('[data-media-preview]');
    const fallback = field.querySelector('[data-media-default]');
    const error = field.querySelector('[data-media-error]');
    const original = image.getAttribute('src') || '';
    let objectUrl = '';
    const clearBlob = () => { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = ''; };
    const showOriginal = () => {
      const show = !!original && !remove.checked;
      if (show) image.src = original;
      else image.removeAttribute('src');
      image.hidden = !show; fallback.hidden = show;
    };
    upload.addEventListener('change', () => {
      clearBlob(); error.textContent = '';
      const file = upload.files[0];
      if (!file) { showOriginal(); return; }
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size < 1 || file.size > 2 * 1024 * 1024) {
        upload.value = ''; error.textContent = form.dataset.fileError; showOriginal(); return;
      }
      objectUrl = URL.createObjectURL(file);
      const current = objectUrl;
      const check = new Image();
      check.onload = () => {
        if (current !== objectUrl) return;
        if (check.naturalWidth < 1 || check.naturalHeight < 1 || check.naturalWidth > 4096 || check.naturalHeight > 4096) {
          clearBlob(); upload.value = ''; error.textContent = form.dataset.fileError; showOriginal(); return;
        }
        remove.checked = false; image.src = objectUrl; image.hidden = false; fallback.hidden = true;
      };
      check.onerror = () => { if (current === objectUrl) { clearBlob(); upload.value = ''; error.textContent = form.dataset.fileError; showOriginal(); } };
      check.src = objectUrl;
    });
    remove.addEventListener('change', () => { clearBlob(); upload.value = ''; error.textContent = ''; showOriginal(); });
    window.addEventListener('pagehide', clearBlob);
  });
  function updateTheme() {
    const selected = form.querySelector('[name="theme"]:checked');
    form.dataset.theme = selected?.value || 'memory';
    form.querySelectorAll('.hgra-theme').forEach(label => label.classList.toggle('hgra-selected', label.querySelector('input').checked));
  }
  form.querySelectorAll('[name="theme"]').forEach(input => input.addEventListener('change', updateTheme));
  form.addEventListener('submit', event => {
    if (updateTotal() !== 10000) { event.preventDefault(); errorElement.textContent = form.dataset.totalError || form.dataset.weightError; errorElement.hidden = false; errorElement.scrollIntoView({ block:'center', behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); }
  });
  updateTheme(); updateTotal();
})();

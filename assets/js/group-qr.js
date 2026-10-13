/* v19.2.2 — local group-invitation QR preview / PNG / native share.
 * No invitation is uploaded to a QR service. Only the host receives this panel.
 */
(() => {
  'use strict';
  let config = {};
  try { config = JSON.parse(document.getElementById('hyg-config')?.textContent || '{}'); } catch (_) {}
  const previews = new WeakMap();
  let serverOffset = Number.isFinite(Number(config.serverTime)) ? Number(config.serverTime) * 1000 - Date.now() : 0;
  let expiryTimer;
  const now = () => Date.now() + serverOffset;
  const feedback = (section, text) => {
    const node = section?.querySelector('[data-share-feedback]');
    if (node) node.textContent = text;
  };
  function active(section) {
    if (!section || !section.isConnected) return false;
    const expiry = Number(section.dataset.expires) * 1000;
    if (!Number.isFinite(expiry) || expiry <= now()) {
      section.hidden = true;
      previews.delete(section);
      const link = section.querySelector('[data-qr-download]');
      if (link) { link.removeAttribute('href'); link.setAttribute('aria-disabled', 'true'); }
      const image = section.querySelector('[data-qr-image]');
      if (image) { image.removeAttribute('src'); image.hidden = true; }
      return false;
    }
    return !section.hidden;
  }
  function updateClock(seconds) {
    const value = Number(seconds);
    if (Number.isFinite(value) && value > 0) serverOffset = value * 1000 - Date.now();
  }
  function render(section) {
    section.hidden = false;
    if (!active(section)) return;
    const input = section.querySelector('#hyg-invite');
    const url = input?.value || '';
    if (previews.get(section)?.url === url) return;
    const image = section.querySelector('[data-qr-image]');
    const status = section.querySelector('[data-qr-status]');
    const download = section.querySelector('[data-qr-download]');
    const hint = section.querySelector('[data-qr-save-hint]');
    try {
      const parsed = new URL(url);
      if (!/^https?:$/.test(parsed.protocol) || !/^[a-f0-9]{64}$/.test(parsed.searchParams.get('invite') || '')) throw new Error('invalid_invitation');
      if (!window.HuiYiQR || !image || !download) throw new Error('qr_not_ready');
      const qr = window.HuiYiQR.encode(url);
      const border = 4, modules = qr.size + border * 2;
      // Whole pixels per module and a four-module quiet zone, including in PNG exports.
      const scale = Math.max(4, Math.ceil(960 / modules));
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = modules * scale;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('canvas_unavailable');
      ctx.imageSmoothingEnabled = false;
      ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#000000';
      for (let y = 0; y < qr.size; y++) {
        for (let x = 0; x < qr.size; x++) {
          if (qr.isDark(x, y)) ctx.fillRect((x + border) * scale, (y + border) * scale, scale, scale);
        }
      }
      const dataURL = canvas.toDataURL('image/png');
      if (!dataURL.startsWith('data:image/png;base64,')) throw new Error('png_unavailable');
      const filename = download.dataset.filename || 'huiyitea-group-qr.png';
      image.src = dataURL; image.hidden = false;
      if (status) status.hidden = true;
      if (hint) hint.hidden = false;
      download.href = dataURL; download.download = filename;
      download.removeAttribute('aria-disabled'); download.removeAttribute('tabindex');
      let file = null;
      // Prepare synchronously before the click; navigator.share needs user activation.
      if (typeof File === 'function') {
        const raw = atob(dataURL.slice(dataURL.indexOf(',') + 1));
        const bytes = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
        file = new File([bytes], filename, {type:'image/png'});
      }
      previews.set(section, {url, file});
    } catch (_) {
      previews.delete(section);
      if (image) { image.hidden = true; image.removeAttribute('src'); }
      if (status) { status.textContent = config.qrError || 'QR Code unavailable. Please copy the link.'; status.hidden = false; }
      if (download) { download.removeAttribute('href'); download.setAttribute('aria-disabled','true'); download.tabIndex = -1; }
      if (hint) hint.hidden = true;
    }
  }
  function sync(seconds) {
    if (seconds !== undefined) updateClock(seconds);
    document.querySelectorAll('[data-group-share]').forEach(render);
  }
  async function copyLink(section, url) {
    try {
      if (!navigator.clipboard || !window.isSecureContext) throw new Error('clipboard_unavailable');
      await navigator.clipboard.writeText(url);
      feedback(section, config.copy || 'Copied');
    } catch (_) {
      const input = section.querySelector('#hyg-invite');
      if (input) { input.focus(); input.select(); input.setSelectionRange(0, input.value.length); }
      feedback(section, config.manual || 'Select the link to copy it.');
    }
  }
  document.addEventListener('click', async event => {
    const control = event.target.closest('[data-group-share-button], [data-qr-download], [data-group-share] [data-copy-url]');
    if (!control) return;
    const section = control.closest('[data-group-share]');
    if (!active(section)) { event.preventDefault(); return; }
    if (control.matches('[data-qr-download]')) {
      if (control.getAttribute('aria-disabled') === 'true' || !control.href) event.preventDefault();
      return;
    }
    event.preventDefault();
    const url = section.querySelector('#hyg-invite')?.value || '';
    if (control.matches('[data-copy-url]')) { await copyLink(section, url); return; }
    const title = control.dataset.shareTitle || '';
    let payload = {title, text:title, url};
    const prepared = previews.get(section);
    // Files are optional. Unsupported environments still share the same join URL.
    try {
      if (prepared?.file && navigator.canShare && navigator.canShare({files:[prepared.file]})) {
        payload = {title, text:title + '\n' + url, files:[prepared.file]};
      }
    } catch (_) {}
    if (navigator.share) {
      try { await navigator.share(payload); }
      catch (error) { if (error.name !== 'AbortError') await copyLink(section, url); }
    } else await copyLink(section, url);
  });
  function checkExpiry() { document.querySelectorAll('[data-group-share]').forEach(active); }
  function startTimer() { clearInterval(expiryTimer); expiryTimer = setInterval(checkExpiry, 1000); }
  window.HuiYiGroupQR = Object.freeze({
    sync,
    suspend: () => document.querySelectorAll('[data-group-share]').forEach(section => { section.hidden = true; }),
    expire: checkExpiry
  });
  sync(); startTimer();
  window.addEventListener('pagehide', () => clearInterval(expiryTimer));
  window.addEventListener('pageshow', event => {
    if (event.persisted) { window.HuiYiGroupQR.suspend(); startTimer(); }
    checkExpiry();
  });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) checkExpiry(); });
})();

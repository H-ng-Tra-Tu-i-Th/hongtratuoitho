/* Shared carts v19.3.0: preserve open member cards and active forms while polling. */
(() => {
  'use strict';
  const element = document.getElementById('hyg-config');
  let config = {};
  try { config = element ? JSON.parse(element.textContent) : {}; } catch (_) { }
  const status = document.querySelector('[data-sync-status]');
  let busy = false, lastSignature = '', lastShare = null, pollTimer;
  const notice = (message, error = false) => {
    if (status) { status.textContent = message; status.classList.toggle('is-offline', error); }
    else { const line = document.createElement('div'); line.className = 'cart-toast'; line.role = 'status'; line.textContent = message; document.body.append(line); setTimeout(() => line.remove(), 3500); }
  };
  const copy = async (url) => {
    try {
      if (!navigator.clipboard || !window.isSecureContext) throw new Error('clipboard_unavailable');
      await navigator.clipboard.writeText(url); notice(config.copy || 'Copied');
    } catch (_) {
      const input = document.getElementById('hyg-invite');
      if (input) { input.focus(); input.select(); input.setSelectionRange(0, input.value.length); }
      notice(config.manual || 'Select the link to copy it.');
    }
  };
  document.addEventListener('click', async (event) => {
    // The QR component owns these controls, including expiry checks and file sharing.
    if (window.HuiYiGroupQR && event.target.closest('[data-group-share]')) return;
    const share = event.target.closest('[data-share-url]');
    const cp = event.target.closest('[data-copy-url]');
    if (share) {
      if (navigator.share) {
        try { await navigator.share({ title: share.dataset.shareTitle, text: share.dataset.shareTitle, url: share.dataset.shareUrl }); }
        catch (error) { if (error.name !== 'AbortError') await copy(share.dataset.shareUrl); }
      } else await copy(share.dataset.shareUrl);
    }
    if (cp) await copy(cp.dataset.copyUrl);
    if (event.target.closest('[data-jump-lock]')) jumpToLock();
    if (event.target.closest('[data-group-refresh]')) { if (document.activeElement) document.activeElement.blur(); await refresh(true); }
  });
  document.addEventListener('submit', (event) => {
    const form = event.target;
    const confirmText = event.submitter?.dataset.confirm || form.dataset.confirm;
    if (confirmText && !window.confirm(confirmText)) { event.preventDefault(); return; }
    if (form.closest('.hyg-page') && form.dataset.submitting) { event.preventDefault(); return; }
    if (form.closest('.hyg-page')) form.dataset.submitting = '1';
  });
  // Step guide (2 → 3 once the group has a drink); server-rendered and escaped like the share card.
  function updateSteps(data) {
    const slot = document.getElementById('hyg-steps-slot');
    if (!slot || typeof data.steps_html !== 'string' || slot.innerHTML.trim() === data.steps_html.trim()) {
      return;
    }
    const template = document.createElement('template');
    template.innerHTML = data.steps_html;
    slot.replaceChildren(template.content);
  }

  function updateShare(data) {
    const slot = document.getElementById('hyg-share-slot');
    if (!slot || typeof data.share_html !== 'string') return;
    if (data.share_html !== lastShare) {
      const template = document.createElement('template');
      // Server-authenticated, escaped host view. Members never receive an invite token.
      template.innerHTML = data.share_html;
      const wasOpen = Boolean(slot.querySelector('details[open]'));
      slot.replaceChildren(template.content);
      if (wasOpen && slot.querySelector('details')) slot.querySelector('details').open = true;
      lastShare = data.share_html;
    }
    window.HuiYiGroupQR?.sync(data.server_time);
  }
  async function refresh(force = false) {
    if (!config.snapshot || busy || document.hidden) return;
    busy = true;
    try {
      const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 12000);
      let response;
      try { response = await fetch(config.snapshot, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal, headers: { 'Accept': 'application/json' } }); }
      finally { clearTimeout(timeout); }
      const data = await response.json();
      if (!response.ok || !data.ok) {
        if (response.status === 401 || response.status === 403) window.HuiYiGroupQR?.suspend();
        throw new Error(data.message || config.offline);
      }
      // Do not defer revoked/rotated invitations even while the host is editing a quantity.
      updateShare(data);
      updateSteps(data);
      const live = document.getElementById('hyg-room-live');
      if (data.signature !== lastSignature) {
        const template = document.createElement('template'); template.innerHTML = data.html;
        const replacement = template.content.querySelector('#hyg-room-live');
        // Keep polling from replacing a focused control before it can be used.
        const interacting = live && live.contains(document.activeElement) && document.activeElement.closest('form, a, button, input, select, textarea');
        const stateChanged = live && replacement && live.dataset.state !== replacement.dataset.state;
        if (interacting && !force && !stateChanged) { notice(config.changed); return; }
        if (live && replacement) {
          // Keep the host's not-yet-submitted offer choice; "Chốt món" sends it.
          const picked = live.querySelector('[data-promotion-selector] input[name="promotion_mode"]:checked');
          const same = picked && replacement.querySelector(`[data-promotion-selector] input[name="promotion_mode"][value="${picked.value}"]:not(:disabled)`);
          if (same) same.checked = true;
          live.replaceWith(replacement); lastSignature = data.signature;
          syncJump();
        }
        const shareBox = document.querySelector('.hyg-share');
        if (shareBox && replacement && replacement.dataset.state !== 'open') shareBox.hidden = true;
      }
      notice(config.sync);
    } catch (_) { notice(config.offline, true); }
    finally { busy = false; }
  }
  // Arrow next to "Chọn đồ uống": shown to the host only while "Chốt món" is off-screen.
  function syncJump() {
    const jump = document.querySelector('[data-jump-lock]');
    if (!jump) {
      return;
    }
    const lock = document.getElementById('hyg-lock-btn');
    let offScreen = false;
    if (lock) {
      const box = lock.getBoundingClientRect();
      offScreen = box.top > window.innerHeight || box.bottom < 0;
    }
    jump.hidden = !offScreen;
  }

  function jumpToLock() {
    const lock = document.getElementById('hyg-lock-btn');
    if (!lock) {
      return;
    }
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    lock.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    lock.classList.remove('is-jump-target');
    void lock.offsetWidth;
    lock.classList.add('is-jump-target');
    setTimeout(() => lock.classList.remove('is-jump-target'), 2600);
    if (!lock.disabled) {
      lock.focus({ preventScroll: true });
    }
  }

  let jumpFrame = 0;
  const queueJump = () => {
    if (jumpFrame) {
      return;
    }
    jumpFrame = requestAnimationFrame(() => {
      jumpFrame = 0;
      syncJump();
    });
  };
  window.addEventListener('scroll', queueJump, { passive: true });
  window.addEventListener('resize', queueJump);
  syncJump();

  window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach(form => delete form.dataset.submitting));
  if (config.snapshot) {
    pollTimer = setInterval(() => refresh(), 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('online', () => refresh());
    window.addEventListener('offline', () => notice(config.offline, true));
    window.addEventListener('pagehide', () => clearInterval(pollTimer));
    window.addEventListener('pageshow', event => { if (event.persisted) { clearInterval(pollTimer); pollTimer = setInterval(() => refresh(), 5000); refresh(); } });
  }
})();

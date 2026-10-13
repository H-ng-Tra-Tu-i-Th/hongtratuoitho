(() => {
  if (!document.querySelector('[data-game-entry]') && !document.getElementById('hyg-room-live')) return;
  let navigating = false;
  const running = new WeakSet();
  const gameUrl = value => {
    try {
      const url = new URL(value, location.href);
      return value && url.origin === location.origin && /\/minigame\/$/.test(url.pathname) && !url.search && !url.hash ? url.href : '';
    } catch (_) { return ''; }
  };
  function openGame(panel) {
    const url = gameUrl(panel.dataset.gameUrl);
    if (panel.dataset.gameState !== 'ready' || !url || navigating || document.hidden) return;
    navigating = true;
    location.replace(url);
  }
  async function refresh(panel) {
    if (!panel.isConnected || running.has(panel) || document.hidden || !panel.dataset.gamePoll) return;
    const endpoint = new URL(panel.dataset.gamePoll, location.href);
    if (endpoint.origin !== location.origin) return;
    running.add(panel);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000);
    try {
      const response = await fetch(endpoint, {credentials:'same-origin', cache:'no-store', signal:controller.signal, headers:{Accept:'application/json'}});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error('unavailable');
      panel.dataset.gameState = data.state;
      panel.dataset.gameUrl = gameUrl(data.url);
      const message = panel.querySelector('[data-game-entry-message]');
      if (message) message.textContent = data.message;
      const link = panel.querySelector('[data-game-entry-link]');
      if (link) { link.hidden = !panel.dataset.gameUrl; if (panel.dataset.gameUrl) link.href = panel.dataset.gameUrl; }
      if (!data.poll) {
        panel.dataset.gamePoll = '';
        panel.querySelector('[data-game-entry-refresh]')?.remove();
      }
      openGame(panel);
    } catch (_) {
      const message = panel.querySelector('[data-game-entry-message]');
      if (message) message.textContent = panel.dataset.gameOffline;
    } finally { clearTimeout(timeout); running.delete(panel); }
  }
  function scan(refreshNow = false) {
    document.querySelectorAll('[data-game-entry]').forEach(panel => { openGame(panel); if (refreshNow) refresh(panel); });
  }
  document.addEventListener('click', event => { const button = event.target.closest('[data-game-entry-refresh]'); if (button) refresh(button.closest('[data-game-entry]')); });
  new MutationObserver(() => scan()).observe(document.body, {childList:true, subtree:true});
  setInterval(() => scan(true), 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) scan(true); });
  window.addEventListener('online', () => scan(true));
  window.addEventListener('pageshow', () => scan(true));
  scan();
})();

/* Shared background handler v19.1; never depends on an open tab or admin session. */
(() => {
  'use strict';
  const config = self.HQ_PUSH_CONFIG;
  const appScope = new URL(config.appScope);
  const base = new URL(config.base);
  const stateKey = self.registration.scope;
  const safeURL = (value, fallback, prefix = base.pathname) => {
    try {
      const u = new URL(value, base);
      return u.origin === base.origin && u.pathname.startsWith(prefix) ? u.href : fallback;
    } catch (_) { return fallback; }
  };
  const stateDB = () => new Promise((resolve, reject) => {
    const req = indexedDB.open('huiyitea-push-state', 1);
    req.onupgradeneeded = () => req.result.createObjectStore('state');
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
  async function getState() {
    const db = await stateDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('state', 'readonly'), req = tx.objectStore('state').get(stateKey);
      req.onsuccess = () => resolve(req.result || null); req.onerror = () => reject(req.error);
      tx.oncomplete = () => db.close();
    });
  }
  async function setState(state) {
    const db = await stateDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('state', 'readwrite'), store = tx.objectStore('state');
      if (state) store.put(state, stateKey); else store.delete(stateKey);
      tx.oncomplete = () => { db.close(); resolve(); }; tx.onerror = () => { db.close(); reject(tx.error); };
    });
  }
  async function post(url, data) {
    const abort = new AbortController(), timeout = setTimeout(() => abort.abort(), 5000);
    try {
      const r = await fetch(url, {method:'POST', credentials:'omit', cache:'no-store', redirect:'error',
        headers:{'Content-Type':'application/json'}, body:JSON.stringify(data), signal:abort.signal});
      if (!r.ok) throw new Error('server_' + r.status);
      const result = await r.json(); if (!result.ok) throw new Error('server_rejected'); return result;
    } finally { clearTimeout(timeout); }
  }
  self.addEventListener('message', event => {
    if (!event.source || !event.source.url || !event.source.url.startsWith(appScope.href)) return;
    const d = event.data || {};
    if (d.type === 'HQ_SAVE_PUSH_STATE') {
      const u = safeURL(d.state?.renewal_url, '');
      if (!u || !/^[a-f0-9]{64}$/.test(d.state?.renewal_token || '')) return;
      event.waitUntil(setState({...d.state, renewal_url:u}));
    } else if (d.type === 'HQ_CLEAR_PUSH_STATE') event.waitUntil(setState(null));
  });
  self.addEventListener('push', event => {
    event.waitUntil((async () => {
      let p = {};
      try { if (event.data) p = event.data.json(); } catch (_) {}
      if (!p || typeof p !== 'object') p = {};
      const orderId = Number.isSafeInteger(p.order_id) && p.order_id > 0 ? p.order_id : 0;
      const fallback = new URL(orderId ? 'order.php?id=' + orderId : 'index.php', appScope).href;
      const target = safeURL(p.url, fallback, appScope.pathname);
      const options = {
        body: String(p.body || 'Có đơn hàng mới / 收到新訂單').slice(0,500),
        icon: safeURL(p.icon, new URL('assets/icons/order-icon-192.png',base).href),
        badge: safeURL(p.badge, new URL('assets/icons/favicon.png',base).href),
        tag: String(p.tag || ('hq-' + config.app + '-' + Date.now())),
        lang:p.lang || 'vi', renotify:true, requireInteraction:true,
        data:{url:target, orderId, test:!!p.test},
        vibrate:[200,100,200]
      };
      // Always display a visible notification, including while a page is open.
      try { await self.registration.showNotification(String(p.title || 'Hồng Trà Tuổi Thơ'), options); }
      catch (_) { delete options.vibrate; delete options.requireInteraction; await self.registration.showNotification('Hồng Trà Tuổi Thơ', options); }
      const tasks = [];
      tasks.push(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(clients => {
        clients.filter(c => c.url.startsWith(appScope.href)).forEach(c => c.postMessage({type:'HQ_ORDER_PUSH',orderId,test:!!p.test}));
      }));
      if (p.job_id && /^[a-f0-9]{48}$/.test(p.receipt_token || '')) {
        const url = safeURL(p.receipt_url, '');
        if (url) tasks.push(post(url,{job_id:p.job_id,token:p.receipt_token}).catch(() => {}));
      }
      await Promise.all(tasks);
    })());
  });
  self.addEventListener('notificationclick', event => {
    event.notification.close();
    const fallback = new URL('index.php', appScope).href;
    const target = safeURL(event.notification.data?.url, fallback, appScope.pathname);
    event.waitUntil((async () => {
      const list = await self.clients.matchAll({type:'window',includeUncontrolled:true});
      const client = list.find(c => c.url.startsWith(appScope.href));
      if (client) {
        try { if ('navigate' in client) await client.navigate(target); await client.focus(); return; } catch (_) {}
      }
      if (self.clients.openWindow) await self.clients.openWindow(target);
    })());
  });
  self.addEventListener('pushsubscriptionchange', event => {
    event.waitUntil((async () => {
      const state = await getState(); if (!state) return;
      const bytes = Uint8Array.from(atob(state.publicKey.replace(/-/g,'+').replace(/_/g,'/')), c => c.charCodeAt(0));
      const sub = event.newSubscription || await self.registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:bytes});
      try {
        const json = sub.toJSON();
        await post(state.renewal_url,{device_id:state.device_id,token:state.renewal_token,...json});
        await setState({...state,needsSync:false});
      } catch (_) {
        await setState({...state,needsSync:true});
        // Next foreground visit validates and repairs the server subscription; no false "ready" state.
      }
    })());
  });
})();

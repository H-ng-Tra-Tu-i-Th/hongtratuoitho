/* HuiYiTea Order Desk v19.1. Shared by /admin/ and /mobile-admin/. */
(() => {
  'use strict';
  const cfgEl = document.getElementById('hq-notification-config');
  if (!cfgEl) return;
  const cfg = JSON.parse(cfgEl.textContent);
  const lang = ['zh', 'en'].includes(cfg.lang) ? cfg.lang : 'vi';
  const words = {
    zh: {
      title: '接單通知檢查', settings: '通知設定', ready: '推播已連接', enable: '啟用／修復此手機推播', disable: '停用此手機推播', test: '伺服器推播測試', delay: '15 秒後測試（可先鎖屏）', close: '關閉', refresh: '重新檢查', install: '安裝 App', wake: '保持螢幕亮起（前景接單）',
      intro: '前景：自訂鈴聲。背景／鎖屏：系統推播，不會持續播放上傳音效。', installHelp: 'iPhone／iPad：Safari 分享 → 加入主畫面，再從桌面圖示開啟並允許通知。Android：瀏覽器選單 → 安裝應用程式。',
      device: '此裝置', installed: '獨立 App 模式', browser: '瀏覽器分頁模式', permission: '通知權限', worker: '推播服務範圍', subscription: '伺服器訂閱', runtime: '伺服器環境', network: '訂單連線', records: '本機最近推播紀錄', none: '尚無紀錄', registered: '已驗證儲存', notSaved: '未完成註冊，請啟用／修復', inactive: '已停用，請重新啟用', keyMismatch: '金鑰或範圍不符，請修復', checking: '檢查中…', online: '連線正常', offline: '離線／連線失敗，重連後補查訂單', login: '登入已到期，請重新登入', sound: '啟用鈴聲', soundReady: '鈴聲已啟用', soundBlocked: '請點「啟用鈴聲」允許播放', queued: '等待發送', sending: '發送中', accepted: '推播服務已接受（不代表手機已收到）', received: '手機已處理通知（不代表已讀）', failed: '發送失敗', retry: '等待重試', expired: '已逾時', cancelled: '已取消', error: '操作失敗', delayNow: '測試已開始。現在可鎖屏，伺服器約 15 秒後發送。', saved: '此手機訂閱已成功存入伺服器。', disabled: '已停用此 App 的推播。', newOrder: '收到新訂單', pending: '待接單', view: '查看訂單', stop: '停止鈴聲', unsupported: '此環境不支援 Web Push；iPhone 請先加入主畫面。', denied: '通知被封鎖，請到此 App／瀏覽器的通知設定允許後重試。', secure: '需使用目前正式網站的 HTTPS 網址。', awake: '螢幕保持亮起', wakeOff: '保持亮屏已關閉', wakeError: '無法保持亮屏，請確認 App 在前景且裝置允許。', testLimit: '請等 20 秒後再測試。', cron: '重試排程最後執行', noCron: '尚未執行；可依說明設定 cPanel Cron', http: '服務回應', detail: '診斷碼', testSent: '測試已送交伺服器，請查看通知與下方紀錄。', repairKey: '訂閱金鑰已變更，請重新啟用／修復。'
    },
    vi: {
      title: 'Kiểm tra thông báo đơn hàng', settings: 'Cài đặt thông báo', ready: 'Đã kết nối thông báo', enable: 'Bật / sửa thông báo thiết bị này', disable: 'Tắt thông báo thiết bị này', test: 'Thử đẩy từ máy chủ', delay: 'Thử sau 15 giây (có thể khóa màn hình)', close: 'Đóng', refresh: 'Kiểm tra lại', install: 'Cài đặt App', wake: 'Giữ màn hình sáng khi nhận đơn',
      intro: 'Khi mở App: chuông tùy chỉnh. Khi ở nền / khóa màn hình: thông báo hệ thống, không phát chuông tùy chỉnh liên tục.', installHelp: 'iPhone/iPad: Safari → Chia sẻ → Thêm vào Màn hình chính. Mở từ biểu tượng và cho phép thông báo. Android: menu trình duyệt → Cài đặt ứng dụng.',
      device: 'Thiết bị này', installed: 'Chế độ App độc lập', browser: 'Chế độ tab trình duyệt', permission: 'Quyền thông báo', worker: 'Phạm vi dịch vụ đẩy', subscription: 'Đăng ký trên máy chủ', runtime: 'Máy chủ', network: 'Kết nối đơn hàng', records: 'Lịch sử đẩy của thiết bị', none: 'Chưa có dữ liệu', registered: 'Đã xác nhận lưu', notSaved: 'Chưa đăng ký hoàn tất. Hãy bật / sửa.', inactive: 'Đã tắt. Hãy bật lại.', keyMismatch: 'Khóa / phạm vi không khớp. Hãy sửa.', checking: 'Đang kiểm tra…', online: 'Kết nối bình thường', offline: 'Mất kết nối. Sẽ kiểm tra đơn khi kết nối lại.', login: 'Phiên đăng nhập hết hạn. Hãy đăng nhập lại.', sound: 'Bật chuông', soundReady: 'Chuông đã bật', soundBlocked: 'Chạm “Bật chuông” để cho phép phát âm thanh.', queued: 'Chờ gửi', sending: 'Đang gửi', accepted: 'Dịch vụ đẩy đã nhận (chưa xác nhận điện thoại nhận)', received: 'Thiết bị đã xử lý thông báo (không phải đã đọc)', failed: 'Gửi thất bại', retry: 'Chờ thử lại', expired: 'Hết hạn', cancelled: 'Đã hủy', error: 'Không thực hiện được', delayNow: 'Đã bắt đầu thử. Có thể khóa màn hình; máy chủ sẽ gửi sau khoảng 15 giây.', saved: 'Đã lưu đăng ký thiết bị trên máy chủ.', disabled: 'Đã tắt thông báo cho App này.', newOrder: 'Có đơn hàng mới', pending: 'Đơn chờ nhận', view: 'Xem đơn', stop: 'Dừng chuông', unsupported: 'Môi trường này không hỗ trợ Web Push. Trên iPhone, hãy thêm vào Màn hình chính.', denied: 'Thông báo bị chặn. Hãy cho phép trong cài đặt App / trình duyệt.', secure: 'Hãy dùng địa chỉ HTTPS chính thức của website hiện tại.', awake: 'Đang giữ màn hình sáng', wakeOff: 'Đã tắt giữ màn hình sáng', wakeError: 'Không thể giữ màn hình sáng. Hãy mở App ở phía trước.', testLimit: 'Chờ 20 giây rồi thử lại.', cron: 'Lần chạy lịch thử lại gần nhất', noCron: 'Chưa chạy; xem hướng dẫn cPanel Cron', http: 'Phản hồi dịch vụ', detail: 'Mã chẩn đoán', testSent: 'Đã gửi yêu cầu thử. Kiểm tra thông báo và lịch sử bên dưới.', repairKey: 'Khóa đăng ký đã đổi. Hãy bật / sửa lại.'
    },
    en: {
      title: 'Order notification check', settings: 'Notification settings', ready: 'Push connected', enable: 'Enable / repair this device', disable: 'Disable push on this device', test: 'Test server push', delay: 'Test after 15 seconds (lock screen)', close: 'Close', refresh: 'Check again', install: 'Install App', wake: 'Keep screen awake for order desk',
      intro: 'Foreground: custom ring. Background / lock screen: system push, not a continuous custom ringtone.', installHelp: 'iPhone/iPad: Safari → Share → Add to Home Screen. Open that icon and allow notifications. Android: browser menu → Install app.', device: 'This device', installed: 'Standalone App', browser: 'Browser tab', permission: 'Permission', worker: 'Push worker scope', subscription: 'Server subscription', runtime: 'Server environment', network: 'Order connection', records: 'Recent push attempts for this device', none: 'No records yet', registered: 'Confirmed saved', notSaved: 'Not fully registered. Enable / repair first.', inactive: 'Disabled. Enable again.', keyMismatch: 'Key / scope mismatch. Repair required.', checking: 'Checking…', online: 'Connected', offline: 'Offline / disconnected. Pending orders will be checked after reconnecting.', login: 'Session expired. Log in again.', sound: 'Enable ring', soundReady: 'Ring enabled', soundBlocked: 'Tap Enable ring to allow audio playback.', queued: 'Queued', sending: 'Sending', accepted: 'Push service accepted (not proof of phone receipt)', received: 'Device processed notification (not proof of reading)', failed: 'Failed', retry: 'Waiting to retry', expired: 'Expired', cancelled: 'Cancelled', error: 'Action failed', delayNow: 'Test started. Lock the screen now; the server sends in about 15 seconds.', saved: 'Device subscription saved on the server.', disabled: 'Push disabled for this App.', newOrder: 'New order received', pending: 'Pending', view: 'View order', stop: 'Stop ring', unsupported: 'Web Push is unavailable here. On iPhone, add to Home Screen first.', denied: 'Notifications are blocked. Allow them in App / browser settings.', secure: 'Use the current website’s official HTTPS address.', awake: 'Screen wake lock active', wakeOff: 'Screen wake lock off', wakeError: 'Cannot keep screen awake. Keep the App in the foreground.', testLimit: 'Wait 20 seconds before another test.', cron: 'Last retry scheduler run', noCron: 'Not run; see cPanel Cron instructions', http: 'Service response', detail: 'Diagnostic code', testSent: 'Test request sent. Check the notification and records below.', repairKey: 'Subscription key changed. Enable / repair again.'
    }
  };
  const t = words[lang];
  const btn = document.getElementById(cfg.app === 'mobile' ? 'ma-push' : 'admin-push-enable');
  const soundBtn = document.getElementById(cfg.app === 'mobile' ? 'ma-sound' : 'admin-sound-enable');
  const rootURL = new URL(cfg.scope, location.href);
  let registration = null, initPromise = null, sub = null, registered = false, busy = false;
  let lastRuntime = {}, liveState = t.checking, lastData = null;
  const local = {
    get(k) { try { return localStorage.getItem(k); } catch (_) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (_) { } },
    remove(k) { try { localStorage.removeItem(k); } catch (_) { } }
  };
  const storagePrefix = 'hq191:' + cfg.app + ':' + rootURL.pathname + ':';
  const standalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  function node(tag, text, className) { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; if (className) el.className = className; return el; }
  const panel = node('dialog', undefined, 'hq-push-dialog'); panel.id = 'hq-push-dialog';
  const head = node('div', undefined, 'hq-push-head'); head.append(node('h2', t.title));
  const close = node('button', t.close); close.type = 'button'; close.addEventListener('click', () => panel.close()); head.append(close); panel.append(head);
  panel.append(node('small', 'Core v19.1 · ' + (cfg.app === 'mobile' ? 'Mobile v1.1' : 'Admin v19.1'), 'hq-push-version'));
  panel.append(node('p', t.intro), node('p', t.installHelp, 'hq-push-help'));
  const message = node('p', '', 'hq-push-message'); message.setAttribute('role', 'status'); panel.append(message);
  const fields = node('dl', undefined, 'hq-push-fields'), fieldValues = {};
  for (const key of ['device', 'permission', 'worker', 'subscription', 'runtime', 'network', 'cron']) { fields.append(node('dt', t[key])); const value = node('dd', t.checking); fields.append(value); fieldValues[key] = value; }
  panel.append(fields);
  const actions = node('div', undefined, 'hq-push-actions'), controls = {};
  for (const key of ['enable', 'disable', 'test', 'delay', 'refresh', 'install', 'wake']) { const b = node('button', t[key]); b.type = 'button'; b.dataset.action = key; controls[key] = b; actions.append(b); }
  panel.append(actions, node('h3', t.records)); const records = node('div', t.none, 'hq-push-records'); panel.append(records); document.body.append(panel);
  const pill = node('div', t.checking, 'hq-connection-status'); pill.setAttribute('role', 'status');
  const main = document.querySelector('main'); if (main) main.prepend(pill);
  function say(text, error = false) { message.textContent = text; message.classList.toggle('hq-error', error); }
  function pushButtonText(ready) {
    const label = btn?.querySelector('[data-push-label]') || btn?.querySelector('span');
    if (label) label.textContent = ready ? t.ready : t.settings;
    btn?.classList.toggle('ready', ready); btn?.classList.toggle('active', ready);
  }
  function renderJobs(jobs) {
    records.replaceChildren(); if (!jobs?.length) { records.append(node('p', t.none)); return; }
    jobs.forEach(j => {
      const item = node('article', undefined, 'hq-push-job');
      item.append(node('strong', '#' + j.id + ' · ' + (j.kind === 'test' ? t.test : t.newOrder)));
      item.append(node('div', j.received_at ? t.received : (t[j.status] || j.status)));
      item.append(node('small', `${j.created_at || ''} · HTTP ${j.http_code || '—'} · ${j.detail || '—'}`));
      if (j.received_at) item.append(node('small', j.received_at)); records.append(item);
    });
  }
  function render() {
    fieldValues.device.textContent = standalone() ? t.installed : t.browser;
    fieldValues.permission.textContent = 'Notification' in window ? Notification.permission : t.unsupported;
    fieldValues.worker.textContent = registration?.active ? registration.scope : t.checking;
    fieldValues.subscription.textContent = registered ? t.registered : (lastData?.state === 'inactive' ? t.inactive : t.notSaved);
    fieldValues.network.textContent = liveState;
    fieldValues.runtime.textContent = lastRuntime.version ? `v${lastRuntime.version} · cURL ${lastRuntime.curl ? 'OK' : '✕'} · OpenSSL ${lastRuntime.openssl ? 'OK' : '✕'} · VAPID ${lastRuntime.vapid ? 'OK' : '✕'}${lastRuntime.schema_error ? ' · ' + lastRuntime.schema_error : ''}` : t.checking;
    fieldValues.cron.textContent = lastRuntime.cron_last_run || t.noCron;
    for (const [key, b] of Object.entries(controls)) b.disabled = busy || (['test', 'delay'].includes(key) && !registered);
    pushButtonText(registered);
  }
  function friendly(error) {
    const code = error?.message || String(error);
    return ({
      login_required: t.login, csrf_expired: t.login, unsupported: t.unsupported, permission_denied: t.denied,
      origin_mismatch: t.secure, test_rate_limit: t.testLimit, key_changed_reload: t.repairKey, enable_first: t.notSaved,
      push_schema_error: 'Database push migration failed. Check CREATE / ALTER permissions.',
      worker_timeout: 'Service Worker activation timed out. Reload this App.', network_error: t.offline
    })[code] || `${t.error}: ${code}`;
  }
  const bytes = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
  const sameKey = subscription => {
    const key = subscription?.options?.applicationServerKey; if (!key) return true;
    const a = new Uint8Array(key), b = bytes(cfg.publicKey); return a.length === b.length && a.every((v, i) => v === b[i]);
  };
  function checkOrigin() { if (rootURL.origin !== location.origin || new URL(cfg.api).origin !== location.origin) throw new Error('origin_mismatch'); }
  async function api(action, values = {}, timeoutMs = 15000) {
    checkOrigin(); const abort = new AbortController(), timer = setTimeout(() => abort.abort(), timeoutMs);
    try {
      const r = await fetch(cfg.api, {
        method: action ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal: abort.signal,
        headers: action ? { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf } : {}, body: action ? JSON.stringify({ action, ...values }) : undefined
      });
      let d; try { d = await r.json(); } catch (_) { throw new Error('invalid_server_response'); }
      if (!r.ok || d.ok !== true) throw new Error(d.error || 'server_' + r.status); return d;
    } catch (e) { if (e.name === 'AbortError') throw new Error('request_timeout'); throw e; } finally { clearTimeout(timer); }
  }
  async function activeRegistration() {
    checkOrigin(); if (!window.isSecureContext || !('serviceWorker' in navigator)) throw new Error('unsupported');
    const reg = await navigator.serviceWorker.register(cfg.worker, { scope: cfg.scope, updateViaCache: 'none' });
    const wanted = new URL(cfg.worker).href, deadline = Date.now() + 15000;
    while (!(reg.active?.state === 'activated' && reg.active.scriptURL === wanted)) {
      if (Date.now() > deadline) throw new Error('worker_timeout'); await new Promise(r => setTimeout(r, 100));
    }
    if (reg.scope !== rootURL.href) throw new Error('scope_mismatch'); registration = reg; return reg;
  }
  function getReg() { if (!initPromise) initPromise = activeRegistration().catch(e => { initPromise = null; throw e; }); return initPromise; }
  async function saveSubscription(s) {
    const r = await api('subscribe', { ...s.toJSON(), lang, applicationServerKey: cfg.publicKey });
    if (r.stored !== true) throw new Error('subscription_not_saved');
    registration.active.postMessage({ type: 'HQ_SAVE_PUSH_STATE', state: { device_id: r.device_id, renewal_token: r.renewal_token, renewal_url: r.renewal_url, publicKey: cfg.publicKey } });
    return r;
  }
  async function refresh() {
    try {
      const d = await api(); lastRuntime = d.runtime || {};
      if (!('PushManager' in window) || !('Notification' in window) || !cfg.publicKey) throw new Error('unsupported');
      const reg = await getReg(); sub = await reg.pushManager.getSubscription();
      registered = false;
      if (sub && Notification.permission === 'granted') {
        const d = await api('check', { endpoint: sub.endpoint }); lastData = d; lastRuntime = d.runtime || lastRuntime;
        registered = d.registered === true && sameKey(sub); renderJobs(d.jobs);
        if (!sameKey(sub)) say(t.repairKey, true);
        // A renewed endpoint may be missing after an offline renewal. Repair only if this App
        // previously opted in, and never silently undo an explicit server-side disable.
        if (!registered && d.state === 'not_saved' && sameKey(sub) && local.get(storagePrefix + 'push') === '1') {
          await saveSubscription(sub); const verified = await api('check', { endpoint: sub.endpoint });
          registered = verified.registered === true; lastData = verified;
        }
      }
    } catch (e) { registered = false; say(friendly(e), true); } finally { render(); }
  }
  function openPanel() { if (!panel.open) panel.showModal(); refresh(); }
  btn?.addEventListener('click', openPanel);
  document.getElementById('admin-push-enable-settings')?.addEventListener('click', openPanel);
  controls.refresh.addEventListener('click', refresh);
  controls.enable.addEventListener('click', async () => {
    if (busy) return;
    if (!('Notification' in window) || !('PushManager' in window)) { say(t.unsupported, true); return; }
    if (Notification.permission === 'denied') { say(t.denied, true); return; }
    // Permission request happens directly in the click handler, BEFORE any asynchronous work.
    const permissionRequest = Notification.permission === 'default' ? Notification.requestPermission() : Promise.resolve(Notification.permission);
    busy = true; say(t.checking); render();
    try {
      if (await permissionRequest !== 'granted') throw new Error('permission_denied');
      const reg = await getReg(); sub = await reg.pushManager.getSubscription();
      if (sub) {
        const savedState = await api('check', { endpoint: sub.endpoint });
        const expired = sub.expirationTime && sub.expirationTime <= Date.now();
        // A provider-expired (404/410) endpoint must be replaced, not merely reactivated in SQL.
        if (!sameKey(sub) || expired || savedState.state === 'inactive' || savedState.state === 'repair_required') {
          await api('unsubscribe', { endpoint: sub.endpoint });
          if (!await sub.unsubscribe()) throw new Error('unsubscribe_failed'); sub = null;
        }
      }
      if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes(cfg.publicKey) });
      await saveSubscription(sub); local.set(storagePrefix + 'push', '1');
      // Retire only this full backend's legacy ROOT subscription after the new one is safely saved.
      // Never unregister the root worker, and never unsubscribe the other backend's scope.
      if (cfg.app === 'admin') {
        const regs = await navigator.serviceWorker.getRegistrations(); const old = regs.find(r => r.scope === new URL(cfg.legacyScope).href && r.scope !== reg.scope);
        const oldSub = await old?.pushManager.getSubscription();
        if (oldSub) { try { await api('unsubscribe', { endpoint: oldSub.endpoint }); await oldSub.unsubscribe(); } catch (_) { } }
      }
      say(t.saved); await refresh();
    } catch (e) { registered = false; say(friendly(e), true); } finally { busy = false; render(); }
  });
  controls.disable.addEventListener('click', async () => {
    if (busy) return; busy = true; render();
    try {
      const reg = await getReg(); const s = await reg.pushManager.getSubscription(); if (s) { await api('unsubscribe', { endpoint: s.endpoint }); await s.unsubscribe(); }
      reg.active.postMessage({ type: 'HQ_CLEAR_PUSH_STATE' }); local.set(storagePrefix + 'push', '0'); sub = null; registered = false; say(t.disabled);
    } catch (e) { say(friendly(e), true); } finally { busy = false; render(); }
  });
  async function testPush(delay) {
    if (busy || !sub || !registered) return; busy = true; say(delay ? t.delayNow : t.checking); render();
    try { const r = await api('test', { endpoint: sub.endpoint, delay }, delay ? 40000 : 16000); renderJobs(r.jobs); say(t.testSent); }
    catch (e) { say(friendly(e), true); } finally { busy = false; render(); setTimeout(refresh, 2000); }
  }
  controls.test.addEventListener('click', () => testPush(0)); controls.delay.addEventListener('click', () => testPush(15));
  let deferredInstall = null;
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); deferredInstall = e; });
  async function install() { if (deferredInstall) { await deferredInstall.prompt(); await deferredInstall.userChoice; deferredInstall = null; } else { openPanel(); say(t.installHelp); } }
  controls.install.addEventListener('click', install); document.getElementById('ma-install')?.addEventListener('click', install);
  window.addEventListener('appinstalled', render);
  let wakeWanted = false, wake = null;
  async function requestWake() {
    if (!wakeWanted || document.visibilityState !== 'visible') return;
    try { if (!('wakeLock' in navigator)) throw new Error('wake'); wake = await navigator.wakeLock.request('screen'); say(t.awake); wake.addEventListener('release', () => { wake = null; }); }
    catch (_) { say(t.wakeError, true); }
  }
  controls.wake.addEventListener('click', async () => { wakeWanted = !wakeWanted; if (wakeWanted) await requestWake(); else { if (wake) await wake.release(); wake = null; say(t.wakeOff); } });

  // Foreground sound is separate from push. Saved preference is NOT proof of audio permission.
  const toast = document.getElementById(cfg.app === 'mobile' ? 'ma-alert' : 'order-alert-toast');
  /* [CUSTOM_SHARED_STORAGE_KEYS_START]
   * Shared keys across all admin routes (desktop & mobile-admin) so sound state & silenced orders are synced.
   */
  const SHARED_SOUND_KEY = 'hq_shared:sound_enabled';
  const SHARED_SILENCED_KEY = 'hq_shared:sound_silenced_order_id';
  function isSoundEnabled() {
    const s = local.get(SHARED_SOUND_KEY);
    if (s !== null) return s === '1';
    return local.get(storagePrefix + 'sound') === '1';
  }
  function setSoundEnabled(on) {
    const v = on ? '1' : '0';
    local.set(SHARED_SOUND_KEY, v);
    local.set(storagePrefix + 'sound', v);
  }
  function getSilencedId() {
    return Math.max(
      Number(local.get(SHARED_SILENCED_KEY) || 0),
      Number(local.get(storagePrefix + 'silenced') || 0)
    );
  }
  function setSilencedId(id) {
    const v = String(id);
    local.set(SHARED_SILENCED_KEY, v);
    local.set(storagePrefix + 'silenced', v);
  }
  let audio = null, audioCtx = null, beepTimer = null, stopTimer = null;
  let soundReady = isSoundEnabled();
  let ringing = false, ringId = 0, lastSoundId = 0, pending = [];
  /* [CUSTOM_SHARED_STORAGE_KEYS_END] */
  const tabId = Math.random().toString(36).slice(2), leaseKey = storagePrefix + 'ring-lease';
  function soundLabel() { const el = soundBtn?.querySelector('[data-sound-label]') || soundBtn?.querySelector('span'); if (el) el.textContent = soundReady ? t.soundReady : t.sound; soundBtn?.classList.toggle('ready', soundReady); soundBtn?.classList.toggle('active', soundReady); }
  function ownLease() { try { const l = JSON.parse(local.get(leaseKey) || 'null'); if (l && l.id !== tabId && l.until > Date.now()) return false; local.set(leaseKey, JSON.stringify({ id: tabId, until: Date.now() + 14000 })); return true; } catch (_) { return true; } }
  /* [CUSTOM_STOP_BTN_VISIBILITY_START]
   * Keep Stop Ring button visibility strictly in sync with the active ringing state:
   * Only show when sound is configured, enabled by user, AND actively ringing right now.
   * If sound stops, finishes its duration, is turned off, or order is silenced: hide the button completely.
   */
  function updateStopButton() {
    const stopBtn = toast?.querySelector('[data-alert-stop]');
    if (!stopBtn) return;
    if (cfg.soundEnabled && soundReady && ringing) {
      stopBtn.hidden = false;
      stopBtn.classList.remove('is-hidden');
      stopBtn.style.removeProperty('display');
    } else {
      stopBtn.hidden = true;
      stopBtn.classList.add('is-hidden');
      stopBtn.style.setProperty('display', 'none', 'important');
    }
  }
  /* [CUSTOM_STOP_BTN_VISIBILITY_END] */
  function stopSound() {
    clearInterval(beepTimer); clearTimeout(stopTimer); beepTimer = stopTimer = null;
    if (audio) { audio.pause(); try { audio.currentTime = 0; } catch (_) { } }
    ringing = false; ringId = 0;
    try { const l = JSON.parse(local.get(leaseKey) || 'null'); if (l?.id === tabId) local.remove(leaseKey); } catch (_) { }
    updateStopButton();
  }
  function beep() {
    if (!audioCtx || audioCtx.state !== 'running') return;
    const o = audioCtx.createOscillator(), g = audioCtx.createGain(); o.type = 'sine'; o.frequency.value = 880; g.gain.value = .08; o.connect(g); g.connect(audioCtx.destination); o.start(); o.stop(audioCtx.currentTime + .2); o.onended = () => { o.disconnect(); g.disconnect(); };
  }
  async function prepareAudio() {
    // Start both permission-gated audio operations synchronously in the user's gesture.
    const C = window.AudioContext || window.webkitAudioContext;
    let ctxPromise = Promise.resolve(), playPromise = null;
    if (C) { audioCtx = audioCtx || new C(); ctxPromise = audioCtx.resume().catch(() => { }); }
    if (cfg.soundUrl) {
      audio = audio || new Audio(cfg.soundUrl); audio.preload = 'auto'; audio.volume = 0;
      playPromise = audio.play().then(() => { audio.pause(); audio.currentTime = 0; return true; }).catch(() => false).finally(() => { audio.volume = 1; });
    }
    const customOK = playPromise ? await playPromise : false; await ctxPromise;
    if (!customOK && audioCtx?.state !== 'running') throw new Error('audio_blocked');
    soundReady = true; setSoundEnabled(true); soundLabel();
  }
  async function playRing(id, seconds, override) {
    /* [CUSTOM_BACKGROUND_AUDIO_START]
     * Allow ring sound to play even when tab is in background or browser is hidden/minimized.
     * To ROLLBACK, uncomment the line below and remove the active if condition:
     * if (document.visibilityState !== 'visible' || (!override && !soundReady) || !ownLease()) return;
     */
    if ((!override && !soundReady) || !ownLease()) return;
    /* [CUSTOM_BACKGROUND_AUDIO_END] */
    stopSound(); ownLease(); ringing = true; ringId = id;
    updateStopButton();
    const url = override === undefined ? cfg.soundUrl : override;
    try {
      if (url) {
        if (!audio || audio.src !== new URL(url, location.href).href) audio = new Audio(url);
        audio.loop = true; audio.volume = 1; await audio.play();
      } else {
        if (!audioCtx) {
          const C = window.AudioContext || window.webkitAudioContext;
          if (C) audioCtx = new C();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
          await audioCtx.resume().catch(() => { });
        }
        if (!audioCtx || audioCtx.state !== 'running') throw new Error('audio_blocked');
        beep(); beepTimer = setInterval(beep, 900);
      }
      updateStopButton();
      if (seconds !== null) stopTimer = setTimeout(stopSound, Math.max(1, seconds) * 1000);
    } catch (_) {
      if (audioCtx?.state === 'running') {
        beep(); beepTimer = setInterval(beep, 900);
        updateStopButton();
        if (seconds !== null) stopTimer = setTimeout(stopSound, Math.max(1, seconds) * 1000);
      } else {
        stopSound();
        /* [CUSTOM_PRESERVE_SOUND_START]
         * Do NOT reset soundReady to false here, to preserve the user's sound activation state.
         * To ROLLBACK: soundReady = false; soundLabel();
         */
        pill.textContent = t.soundBlocked;
        /* [CUSTOM_PRESERVE_SOUND_END] */
      }
    }
  }
  const seconds = cfg.mode === 'minutes' ? Math.max(60, Math.min(7200, cfg.duration * 60)) : Math.max(3, Math.min(3600, cfg.duration));
  function showPending() {
    if (!pending.length) { stopSound(); if (toast) toast.hidden = true; return; }
    const order = pending[pending.length - 1];
    if (toast) {
      toast.hidden = false; const h = toast.querySelector('[data-alert-title]'), m = toast.querySelector('[data-alert-meta]'), a = toast.querySelector('[data-alert-view]');
      if (h) h.textContent = `${t.pending} ${pendingCount} · #${order.order_no}`;
      if (m) m.textContent = `${order.customer_name} · ${Number(order.total_amount || 0).toLocaleString('en-US')}`;
      if (a) a.href = cfg.orderUrl + '?id=' + encodeURIComponent(order.id);
    }
    if (ringing && !pending.some(o => Number(o.id) === ringId)) stopSound();
    const id = Number(order.id), silenced = getSilencedId();
    const isSilenced = id <= silenced;

    if (!cfg.soundEnabled || !soundReady || isSilenced) {
      if (isSilenced) stopSound();
      updateStopButton();
      return;
    }
    /* [CUSTOM_BACKGROUND_DESKTOP_NOTIF_START]
     * Show desktop popup notification when tab is hidden in background.
     * To ROLLBACK, simply delete this entire if block.
     */
    if (document.visibilityState === 'hidden' && 'Notification' in window && Notification.permission === 'granted' && id > lastSoundId) {
      try {
        new Notification(t.newOrder || 'Có đơn hàng mới', {
          body: `#${order.order_no} - ${order.customer_name} (${Number(order.total_amount || 0).toLocaleString('vi-VN')}đ)`,
          icon: '/assets/icons/order-icon-192.png',
          tag: 'new-order-' + order.id
        });
      } catch (_) { }
    }
    /* [CUSTOM_BACKGROUND_DESKTOP_NOTIF_END] */
    if (cfg.mode === 'until_accepted') {
      if (!ringing) { lastSoundId = id; playRing(id, null); }
      else ownLease();
    } else if (id > lastSoundId) {
      lastSoundId = id; playRing(id, seconds);
    } else {
      updateStopButton();
    }
  }
  /* [CUSTOM_STOP_SOUND_ACTION_START]
   * Silence all current pending orders, stop ringing, and hide the stop button immediately.
   */
  toast?.querySelector('[data-alert-stop]')?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    const maxId = pending.reduce((max, o) => Math.max(max, Number(o.id || 0)), 0);
    if (maxId > 0) {
      setSilencedId(maxId);
    }
    stopSound();
    updateStopButton();
  });
  /* [CUSTOM_STOP_SOUND_ACTION_END] */
  /* [CUSTOM_TOGGLE_SOUND_START]
   * Toggle sound on and off when clicking the button.
   * To ROLLBACK, see original code in comment below:
   * soundBtn?.addEventListener('click', async () => {
   *   try { await prepareAudio(); local.remove(storagePrefix + 'silenced'); lastSoundId = 0; if (pending.length) showPending(); else await playRing(0, 1); }
   *   catch (_) { soundReady = false; soundLabel(); pill.textContent = t.soundBlocked; }
   * });
   */
  soundBtn?.addEventListener('click', async () => {
    if (soundReady) {
      // Turn OFF
      stopSound();
      soundReady = false;
      setSoundEnabled(false);
      soundLabel();
      updateStopButton();
    } else {
      // Turn ON: restore audio permission, reset silence block so pending orders ring, and show button
      try {
        await prepareAudio();
        soundReady = true;
        setSoundEnabled(true);
        soundLabel();
        local.remove(SHARED_SILENCED_KEY);
        local.remove(storagePrefix + 'silenced');
        lastSoundId = 0;
        if (pending.length) {
          showPending();
        } else {
          await playRing(0, 1);
        }
      } catch (_) {
        soundReady = false;
        soundLabel();
        pill.textContent = t.soundBlocked;
        updateStopButton();
      }
    }
  });
  /* [CUSTOM_TOGGLE_SOUND_END] */
  // Restore permission only on a real interaction, not from localStorage alone.
  document.addEventListener('pointerdown', () => {
    if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume().catch(() => { });
    if (isSoundEnabled()) prepareAudio().then(showPending).catch(() => { });
  }, { passive: true });
  const testSound = document.querySelector('[data-test-order-sound]');
  testSound?.addEventListener('click', async () => {
    let objectURL = '';
    try {
      await prepareAudio(); const file = document.querySelector('[name="order_alert_sound"]');
      const choice = document.querySelector('input[name="order_alert_sound_source"]:checked')?.value;
      let url = choice === 'default' ? '' : (document.body.dataset.orderAlertCustomSound || cfg.soundUrl);
      if (file?.files?.[0]) { objectURL = URL.createObjectURL(file.files[0]); url = objectURL; }
      await playRing(0, 5, url);
    } catch (_) { pill.textContent = t.soundBlocked; } finally { if (objectURL) setTimeout(() => URL.revokeObjectURL(objectURL), 7000); }
  });
  let polling = false, pollTimer = null, after = Number(cfg.latest || 0), pendingCount = 0, failures = 0;
  function status(text, error = false) { liveState = text; pill.textContent = text; pill.classList.toggle('hq-connection-error', error); fieldValues.network.textContent = text; }
  async function poll() {
    /* [CUSTOM_BACKGROUND_POLL_START]
     * Continue polling for new orders even when tab is hidden or user switched to another app.
     * To ROLLBACK, uncomment the line below and remove the active if condition:
     * if (polling || document.visibilityState !== 'visible') return;
     */
    if (polling) return;
    /* [CUSTOM_BACKGROUND_POLL_END] */
    clearTimeout(pollTimer); polling = true;
    const abort = new AbortController(), timeout = setTimeout(() => abort.abort(), 12000);
    try {
      const u = new URL(cfg.ordersApi, location.href); u.searchParams.set('after', String(after));
      const r = await fetch(u, { credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal: abort.signal });
      if (r.status === 401) { stopSound(); registered = false; pushButtonText(false); status(t.login, true); return; }
      if (!r.ok) throw new Error('network'); const d = await r.json(); if (d.ok !== true) throw new Error('response');
      pending = Array.isArray(d.pending_orders) ? d.pending_orders : (Array.isArray(d.orders) ? d.orders : []);
      pendingCount = Number(d.new_count || pending.length); after = Math.max(after, Number(d.latest || 0)); failures = 0;
      status(t.online + ' · ' + t.pending + ' ' + pendingCount); showPending();
      if ('setAppBadge' in navigator) { if (pendingCount) navigator.setAppBadge(pendingCount).catch(() => { }); else navigator.clearAppBadge?.().catch(() => { }); }
    } catch (_) { failures++; status(t.offline, true); } finally { clearTimeout(timeout); polling = false; pollTimer = setTimeout(poll, Math.min(30000, 6000 * (1 + failures))); }
  }
  navigator.serviceWorker?.addEventListener('message', e => {
    if (e.data?.type === 'HQ_ORDER_PUSH') { poll(); if (panel.open) refresh(); }
  });
  window.addEventListener('online', () => { poll(); refresh(); });
  window.addEventListener('offline', () => status(t.offline, true));
  window.addEventListener('pageshow', () => { poll(); if (panel.open) refresh(); });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') { poll(); refresh(); if (wakeWanted) requestWake(); }
    else {
      /* [CUSTOM_BACKGROUND_VISIBILITY_START]
       * Do NOT cancel pollTimer and do NOT stop ringing when tab is hidden/minimized.
       * To ROLLBACK, uncomment the line below:
       * clearTimeout(pollTimer); stopSound();
       */
      /* [CUSTOM_BACKGROUND_VISIBILITY_END] */
    }
  });
  window.addEventListener('pagehide', () => { stopSound(); if (wake) wake.release().catch(() => { }); });
  /* [CUSTOM_CROSS_TAB_SYNC_START]
   * Synchronize sound state and silenced orders across multiple tabs and pages in real-time.
   */
  window.addEventListener('storage', (e) => {
    if (e.key === SHARED_SILENCED_KEY) {
      const silenced = Number(e.newValue || 0);
      const maxId = pending.reduce((max, o) => Math.max(max, Number(o.id || 0)), 0);
      if (maxId <= silenced) {
        stopSound();
        updateStopButton();
      }
    } else if (e.key === SHARED_SOUND_KEY) {
      soundReady = e.newValue === '1';
      soundLabel();
      if (!soundReady) {
        stopSound();
        updateStopButton();
      } else {
        if (pending.length) showPending();
      }
    }
  });
  /* [CUSTOM_CROSS_TAB_SYNC_END] */
  // A root /admin URL redirected to /admin/ is handled by the server; scope is never guessed here.
  render(); soundLabel(); poll(); refresh();
  if (soundReady) {
    prepareAudio().catch(() => { });
  }
  /* [CUSTOM_BG_WORKER_TICKER_START]
   * Web Worker ticker prevents browser from throttling pollTimer when tab is in background or minimized.
   * To ROLLBACK: delete this try-catch block.
   */
  try {
    const workerScript = 'let t=null;self.onmessage=e=>{if(e.data==="start"){clearInterval(t);t=setInterval(()=>self.postMessage("tick"),5000);}else if(e.data==="stop"){clearInterval(t);t=null;}};';
    const bgBlob = new Blob([workerScript], { type: 'application/javascript' });
    const bgTicker = new Worker(URL.createObjectURL(bgBlob));
    bgTicker.onmessage = () => { poll(); };
    bgTicker.postMessage('start');
  } catch (_) { }
  /* [CUSTOM_BG_WORKER_TICKER_END] */
  window.HQNotificationVersion = cfg.version;
})();

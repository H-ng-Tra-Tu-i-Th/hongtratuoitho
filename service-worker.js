const CACHE='drink-order-v19.15.0';
const ASSETS=['./assets/vendor/huiyi-qrcode.js?v=19.8.0','./assets/js/group-qr.js?v=19.8.0','./assets/css/group-order.css?v=19.8.0','./assets/js/group-order.js?v=19.8.0','./assets/css/app.css?v=19.15.0','./assets/css/member.css?v=19.8.0','./assets/js/app.js?v=19.15.0','./assets/js/promotion.js?v=19.8.0','./assets/js/game-entry.js?v=19.9.1','./assets/icons/order-icon-192.png','./assets/icons/order-icon-512.png','./assets/icons/favicon.png'];
self.addEventListener('install',event=>{self.skipWaiting();event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(ASSETS)))});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE&&key.startsWith('drink-order-')).map(key=>caches.delete(key)))).then(()=>self.clients.claim()))});
self.addEventListener('fetch',event=>{if(event.request.method!=='GET')return;const url=new URL(event.request.url);if(!url.pathname.includes('/assets/'))return;event.respondWith(fetch(event.request).then(response=>{const copy=response.clone();caches.open(CACHE).then(cache=>cache.put(event.request,copy));return response}).catch(()=>caches.match(event.request)))});


// Compatibility handler for existing root-scope subscriptions; new HQ installs use /admin/sw.js.
self.HQ_PUSH_CONFIG={app:'admin',appScope:new URL('./admin/',self.location.href).href,base:new URL('./',self.location.href).href};
importScripts('./assets/js/order-push-worker.js?v=19.1');

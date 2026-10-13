self.HQ_PUSH_CONFIG = {app:'mobile',appScope:self.registration.scope,base:new URL('../',self.registration.scope).href};
importScripts('../assets/js/order-push-worker.js?v=19.1');
self.addEventListener('install',event=>event.waitUntil(self.skipWaiting()));
self.addEventListener('activate',event=>event.waitUntil((async()=>{
  const keys=await caches.keys();
  await Promise.all(keys.filter(k=>k.startsWith('huiyitea-mobile-admin-')).map(k=>caches.delete(k)));
  await self.clients.claim();
})()));
// No caching of authenticated pages or APIs. Notification delivery does not require an open page.

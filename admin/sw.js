self.HQ_PUSH_CONFIG = {app:'admin',appScope:self.registration.scope,base:new URL('../',self.registration.scope).href};
importScripts('../assets/js/order-push-worker.js?v=19.1');
self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
// Deliberately do not cache authenticated pages, API responses, or order details.

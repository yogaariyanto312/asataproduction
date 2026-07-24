// Service Worker minimal — hanya untuk memenuhi syarat installable PWA
// Tidak meng-cache apapun agar data selalu fresh dari server

self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', e => e.respondWith(fetch(e.request)));

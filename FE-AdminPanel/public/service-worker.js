self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()))

// Keep trip and GPS requests network-only. The worker exists to provide the
// installable PWA shell without risking stale operational data.
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return
  event.respondWith(fetch(event.request))
})

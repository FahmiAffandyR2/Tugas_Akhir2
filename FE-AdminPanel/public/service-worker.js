const CACHE_VERSION = 'ezbus-pwa-v6'
const APP_SHELL_CACHE = `${CACHE_VERSION}-shell`
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`
const APP_SHELL = [
  '/',
  '/index.html',
  '/manifest.webmanifest',
  '/driver-icon.svg',
  '/icons/ezbus-icon-192.png',
  '/icons/ezbus-icon-512.png',
  '/offline.html',
]

const API_PATH_PREFIXES = [
  '/api/',
  '/broadcasting/',
  '/sanctum/',
]

const NETWORK_ONLY_PATHS = [
  '/api/planned-trips/set-last-position',
  '/api/planned-trips/start-stop',
  '/api/drivers/get-driver-trips',
  '/api/live-tracking',
]

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(APP_SHELL_CACHE)
      .then(cache => cache.addAll(APP_SHELL))
      .then(() => self.skipWaiting())
  )
})

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys
          .filter(key => ![APP_SHELL_CACHE, RUNTIME_CACHE].includes(key))
          .map(key => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  )
})

self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting()
  }
})

self.addEventListener('fetch', event => {
  const { request } = event

  if (request.method !== 'GET') return

  const url = new URL(request.url)
  if (url.origin !== self.location.origin) return

  if (isNetworkOnly(url.pathname)) {
    event.respondWith(fetch(request))
    return
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirstNavigation(request))
    return
  }

  if (isApiRequest(url.pathname)) {
    event.respondWith(fetch(request))
    return
  }

  if (isVersionedAsset(request)) {
    event.respondWith(networkFirstAsset(request))
    return
  }

  event.respondWith(staleWhileRevalidate(request))
})

function isApiRequest(pathname) {
  return API_PATH_PREFIXES.some(prefix => pathname.startsWith(prefix))
}

function isNetworkOnly(pathname) {
  return NETWORK_ONLY_PATHS.some(path => pathname.startsWith(path))
}

function isVersionedAsset(request) {
  return ['script', 'style', 'worker', 'manifest'].includes(request.destination)
}

async function networkFirstNavigation(request) {
  try {
    const response = await fetch(request)
    const cache = await caches.open(APP_SHELL_CACHE)
    cache.put('/index.html', response.clone())
    return response
  } catch (_) {
    const cachedIndex = await caches.match('/index.html')
    return cachedIndex || caches.match('/offline.html')
  }
}

async function networkFirstAsset(request) {
  try {
    const response = await fetch(request)
    if (response && response.ok) {
      const cache = await caches.open(RUNTIME_CACHE)
      cache.put(request, response.clone())
    }
    return response
  } catch (_) {
    return caches.match(request)
  }
}

async function staleWhileRevalidate(request) {
  const cachedResponse = await caches.match(request)
  const fetchPromise = fetch(request)
    .then(response => {
      if (response && response.ok) {
        const copy = response.clone()
        caches.open(RUNTIME_CACHE).then(cache => cache.put(request, copy))
      }
      return response
    })
    .catch(() => cachedResponse)

  return cachedResponse || fetchPromise
}

---
name: pwa-system
description: >
  Build, configure, and optimize Progressive Web App (PWA) systems for projects with
  separated frontend (Vue.js SPA + Vite) and backend (Laravel API). Use this skill
  whenever the user mentions PWA, service worker, web app manifest, offline support,
  installable web app, caching strategy, push notifications, or wants to convert an
  existing Vue.js SPA into a PWA. Also trigger when the user asks about workbox,
  cache-first, network-first, background sync, app shell architecture, lighthouse PWA
  audit, or vite-plugin-pwa. This skill covers the full PWA lifecycle for a decoupled
  architecture: Vue.js frontend handles manifest, service worker, caching, install prompt,
  and offline UI; Laravel backend handles push notification subscriptions, VAPID keys,
  and sending push payloads. Designed for Vue 3 (Composition API) + Vite on the frontend
  and Laravel 10+/11+ API on the backend.
---

# PWA System Skill — Separated Vue.js (FE) + Laravel API (BE)

You are a senior full-stack engineer specializing in Progressive Web Apps for decoupled
architectures. The project has two separate codebases:

- **Frontend (FE):** Vue.js 3 SPA with Vite — its own repo, own URL (e.g., `app.example.com`)
- **Backend (BE):** Laravel REST API — its own repo, own URL (e.g., `api.example.com`)

PWA implementation lives **almost entirely on the FE side**. The BE only gets involved
for push notifications (storing subscriptions, sending payloads via web-push).

## Before You Start

### FE Side (Vue.js)
1. Confirm Vite is the bundler (`vite.config.js` or `vite.config.ts`).
2. Check `package.json` for Vue version (`vue@3.x` expected).
3. Check if Vue Router exists and uses `createWebHistory()` (history mode).
4. Check if there's a state management lib (Pinia / Vuex) — useful for offline state.
5. Look for existing PWA artifacts: `manifest.json`, `sw.js`, `vite-plugin-pwa` in deps.
6. Identify the API base URL (env var like `VITE_API_URL` or `VITE_API_BASE_URL`).

### BE Side (Laravel)
1. Only relevant if push notifications are needed.
2. Check if CORS is configured to accept requests from the FE domain.
3. Check auth mechanism (Sanctum SPA auth, token-based, JWT, etc.).

---

## FE: Core PWA Implementation

Everything below happens in the **Vue.js frontend project**.

### Approach A: vite-plugin-pwa (Recommended)

Best DX — auto-generates SW, injects manifest, provides Vue composables.

#### Step 1: Install

```bash
npm install -D vite-plugin-pwa
```

#### Step 2: Vite Config

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'prompt',  // user decides when to update

      // ─── Manifest ───
      manifest: {
        name: 'App Full Name',
        short_name: 'AppName',
        description: 'Brief description',
        theme_color: '#4A90D9',
        background_color: '#ffffff',
        display: 'standalone',
        orientation: 'portrait-primary',
        scope: '/',
        start_url: '/',
        id: '/',
        icons: [
          {
            src: '/icons/icon-192x192.png',
            sizes: '192x192',
            type: 'image/png',
          },
          {
            src: '/icons/icon-512x512.png',
            sizes: '512x512',
            type: 'image/png',
          },
          {
            src: '/icons/icon-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
        screenshots: [
          {
            src: '/screenshots/desktop.png',
            sizes: '1280x720',
            type: 'image/png',
            form_factor: 'wide',
            label: 'Desktop view',
          },
          {
            src: '/screenshots/mobile.png',
            sizes: '390x844',
            type: 'image/png',
            form_factor: 'narrow',
            label: 'Mobile view',
          },
        ],
      },

      // ─── Workbox ───
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],

        // Navigations: let Vue Router handle all routes
        navigateFallback: 'index.html',
        navigateFallbackDenylist: [
          /^\/api/,  // in case proxy is used in dev
        ],

        runtimeCaching: [
          // ─── API calls to Laravel backend ───
          {
            // Match your Laravel API domain
            urlPattern: ({ url }) => {
              // Adjust this to match your API URL
              return url.origin === 'https://api.example.com' ||
                     url.pathname.startsWith('/api/');
            },
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 5 * 60, // 5 minutes
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
              networkTimeoutSeconds: 10,
            },
          },
          // ─── Images: Cache-first ───
          {
            urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp|ico)$/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'images-cache',
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 60 * 24 * 60 * 60, // 60 days
              },
            },
          },
          // ─── Fonts: Cache-first ───
          {
            urlPattern: /\.(?:woff|woff2|ttf|eot|otf)$/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'fonts-cache',
              expiration: {
                maxEntries: 20,
                maxAgeSeconds: 365 * 24 * 60 * 60,
              },
            },
          },
          // ─── Google Fonts stylesheets ───
          {
            urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
            handler: 'StaleWhileRevalidate',
            options: { cacheName: 'google-fonts-stylesheets' },
          },
          // ─── Google Fonts files ───
          {
            urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'google-fonts-webfonts',
              expiration: {
                maxEntries: 30,
                maxAgeSeconds: 365 * 24 * 60 * 60,
              },
            },
          },
          // ─── CDN assets (if any) ───
          {
            urlPattern: /^https:\/\/cdn\./i,
            handler: 'CacheFirst',
            options: {
              cacheName: 'cdn-cache',
              expiration: {
                maxEntries: 50,
                maxAgeSeconds: 30 * 24 * 60 * 60,
              },
            },
          },
          // ─── Laravel storage / uploaded files ───
          {
            urlPattern: ({ url }) => {
              return url.origin === 'https://api.example.com' &&
                     url.pathname.startsWith('/storage/');
            },
            handler: 'CacheFirst',
            options: {
              cacheName: 'storage-uploads-cache',
              expiration: {
                maxEntries: 200,
                maxAgeSeconds: 30 * 24 * 60 * 60,
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
        ],
      },

      devOptions: {
        enabled: false,  // set true to test SW in dev (caution: HMR conflicts)
      },
    }),
  ],

  server: {
    // If you proxy API in dev:
    // proxy: {
    //   '/api': {
    //     target: 'http://localhost:8000',
    //     changeOrigin: true,
    //   },
    // },
  },
});
```

> **IMPORTANT:** Replace `https://api.example.com` in the `urlPattern` functions
> with the actual Laravel API URL. If the API URL comes from env, you can't use
> `import.meta.env` inside workbox config (it runs in SW context). Hardcode the
> production API URL or use a regex that matches the pattern.

#### Step 3: PWA Update Prompt Component

Create `src/components/PWAUpdatePrompt.vue`:

```vue
<script setup>
import { useRegisterSW } from 'virtual:pwa-register/vue';

const {
  needRefresh,
  offlineReady,
  updateServiceWorker,
} = useRegisterSW({
  onRegisteredSW(swUrl, registration) {
    if (registration) {
      // Check for updates every 60 minutes
      setInterval(() => registration.update(), 60 * 60 * 1000);
    }
  },
  onRegisterError(error) {
    console.error('[PWA] SW registration error:', error);
  },
});

function close() {
  offlineReady.value = false;
  needRefresh.value = false;
}
</script>

<template>
  <Transition name="pwa-toast">
    <div v-if="offlineReady || needRefresh" class="pwa-toast" role="alert">
      <div class="pwa-toast__content">
        <span v-if="needRefresh">🔄 Versi baru tersedia!</span>
        <span v-else>✅ Aplikasi siap digunakan offline.</span>
      </div>
      <div class="pwa-toast__actions">
        <button
          v-if="needRefresh"
          class="pwa-toast__btn pwa-toast__btn--primary"
          @click="updateServiceWorker()"
        >
          Update
        </button>
        <button class="pwa-toast__btn pwa-toast__btn--dismiss" @click="close">
          ✕
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.pwa-toast {
  position: fixed;
  bottom: 1rem;
  right: 1rem;
  display: flex;
  align-items: center;
  gap: 12px;
  background: #1e293b;
  color: #f1f5f9;
  padding: 12px 16px;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
  z-index: 9999;
  font-family: system-ui, -apple-system, sans-serif;
  font-size: 0.9rem;
  max-width: 400px;
}
.pwa-toast__content { flex: 1; }
.pwa-toast__actions { display: flex; gap: 6px; }
.pwa-toast__btn {
  border: none; border-radius: 6px; padding: 6px 14px;
  cursor: pointer; font-size: 0.85rem; transition: background 0.2s;
}
.pwa-toast__btn--primary { background: #3b82f6; color: #fff; }
.pwa-toast__btn--primary:hover { background: #2563eb; }
.pwa-toast__btn--dismiss { background: transparent; color: #94a3b8; padding: 6px 8px; }
.pwa-toast__btn--dismiss:hover { color: #f1f5f9; }

.pwa-toast-enter-active { animation: slideUp 0.3s ease-out; }
.pwa-toast-leave-active { animation: slideDown 0.2s ease-in; }
@keyframes slideUp {
  from { transform: translateY(100%); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}
@keyframes slideDown {
  from { transform: translateY(0);    opacity: 1; }
  to   { transform: translateY(100%); opacity: 0; }
}
</style>
```

#### Step 4: Install Prompt Component

Create `src/components/PWAInstallPrompt.vue`:

```vue
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const deferredPrompt = ref(null);
const canInstall = ref(false);
const isInstalled = ref(false);

function handleBeforeInstall(e) {
  e.preventDefault();
  deferredPrompt.value = e;
  canInstall.value = true;
}

function handleAppInstalled() {
  deferredPrompt.value = null;
  canInstall.value = false;
  isInstalled.value = true;
}

async function install() {
  if (!deferredPrompt.value) return;
  deferredPrompt.value.prompt();
  const { outcome } = await deferredPrompt.value.userChoice;
  deferredPrompt.value = null;
  canInstall.value = false;
}

function dismiss() {
  canInstall.value = false;
}

onMounted(() => {
  window.addEventListener('beforeinstallprompt', handleBeforeInstall);
  window.addEventListener('appinstalled', handleAppInstalled);
  if (window.matchMedia('(display-mode: standalone)').matches) {
    isInstalled.value = true;
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('beforeinstallprompt', handleBeforeInstall);
  window.removeEventListener('appinstalled', handleAppInstalled);
});
</script>

<template>
  <Transition name="install-banner">
    <div v-if="canInstall && !isInstalled" class="install-banner">
      <div class="install-banner__content">
        <span class="install-banner__icon">📲</span>
        <div>
          <strong>Install Aplikasi</strong>
          <p>Akses lebih cepat langsung dari home screen.</p>
        </div>
      </div>
      <div class="install-banner__actions">
        <button class="install-banner__btn--install" @click="install">Install</button>
        <button class="install-banner__btn--dismiss" @click="dismiss">Nanti</button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.install-banner {
  position: fixed; bottom: 1rem; left: 1rem; right: 1rem;
  max-width: 480px; margin: 0 auto;
  background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;
  padding: 16px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
  z-index: 9998; font-family: system-ui, -apple-system, sans-serif;
}
.install-banner__content {
  display: flex; align-items: center; gap: 12px; margin-bottom: 12px;
}
.install-banner__icon { font-size: 2rem; }
.install-banner__content p { margin: 2px 0 0; font-size: 0.85rem; color: #64748b; }
.install-banner__actions { display: flex; gap: 8px; justify-content: flex-end; }
.install-banner__btn--install {
  background: #3b82f6; color: #fff; border: none;
  padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;
}
.install-banner__btn--dismiss {
  background: transparent; color: #64748b; border: none;
  padding: 8px 12px; cursor: pointer;
}
.install-banner-enter-active { animation: slideUp 0.3s ease-out; }
.install-banner-leave-active { animation: slideDown 0.2s ease-in; }
@keyframes slideUp {
  from { transform: translateY(100%); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}
@keyframes slideDown {
  from { transform: translateY(0);    opacity: 1; }
  to   { transform: translateY(100%); opacity: 0; }
}
</style>
```

#### Step 5: Online Status Composable

Create `src/composables/useOnlineStatus.js`:

```javascript
import { ref, onMounted, onBeforeUnmount } from 'vue';

export function useOnlineStatus() {
  const isOnline = ref(navigator.onLine);

  const goOnline = () => { isOnline.value = true; };
  const goOffline = () => { isOnline.value = false; };

  onMounted(() => {
    window.addEventListener('online', goOnline);
    window.addEventListener('offline', goOffline);
  });

  onBeforeUnmount(() => {
    window.removeEventListener('online', goOnline);
    window.removeEventListener('offline', goOffline);
  });

  return { isOnline };
}
```

#### Step 6: Register in App

In `src/App.vue` (or your root layout component):

```vue
<script setup>
import { RouterView } from 'vue-router';
import PWAUpdatePrompt from '@/components/PWAUpdatePrompt.vue';
import PWAInstallPrompt from '@/components/PWAInstallPrompt.vue';
import { useOnlineStatus } from '@/composables/useOnlineStatus';

const { isOnline } = useOnlineStatus();
</script>

<template>
  <!-- Offline indicator bar -->
  <div v-if="!isOnline" class="offline-bar">
    ⚠️ Anda sedang offline — beberapa fitur mungkin terbatas
  </div>

  <RouterView />

  <!-- PWA UI (always at root level) -->
  <PWAUpdatePrompt />
  <PWAInstallPrompt />
</template>

<style>
.offline-bar {
  position: fixed; top: 0; left: 0; right: 0;
  background: #f59e0b; color: #1e293b;
  text-align: center; padding: 8px; font-size: 0.85rem;
  z-index: 10000; font-family: system-ui, sans-serif;
}
</style>
```

#### Step 7: HTML Meta Tags

Update `index.html` in the Vue project root:

```html
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AppName</title>

  <!-- PWA Meta Tags -->
  <meta name="theme-color" content="#4A90D9" />
  <meta name="description" content="Brief description of the app" />

  <!-- iOS Support -->
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="apple-mobile-web-app-title" content="AppName" />
  <link rel="apple-touch-icon" href="/icons/icon-192x192.png" />

  <!-- MS Tile -->
  <meta name="msapplication-TileImage" content="/icons/icon-144x144.png" />
  <meta name="msapplication-TileColor" content="#4A90D9" />

  <!-- Manifest is auto-injected by vite-plugin-pwa -->
</head>
<body>
  <div id="app"></div>
  <script type="module" src="/src/main.js"></script>
</body>
</html>
```

> `vite-plugin-pwa` auto-injects the `<link rel="manifest">` and SW registration
> script. You do NOT add these manually.

#### Step 8: Offline Fallback Page (Optional)

For a Vue SPA, the `navigateFallback: 'index.html'` in workbox config already
serves the cached `index.html` for all navigation requests when offline. Vue Router
then handles rendering.

If you want a dedicated offline page for when even `index.html` isn't cached yet
(very first visit while offline), create `public/offline.html`:

```html
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Offline — AppName</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; background: #f8fafc; color: #334155;
      text-align: center; padding: 2rem;
    }
    .container { max-width: 420px; }
    .icon { font-size: 4rem; margin-bottom: 1rem; }
    h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #1e293b; }
    p { color: #64748b; margin-bottom: 1.5rem; line-height: 1.6; }
    button {
      padding: 0.75rem 2rem; border: none; border-radius: 8px;
      background: #4A90D9; color: white; font-size: 1rem;
      cursor: pointer;
    }
    button:hover { background: #357ABD; }
  </style>
</head>
<body>
  <div class="container">
    <div class="icon">📡</div>
    <h1>Anda Sedang Offline</h1>
    <p>Koneksi internet tidak tersedia. Periksa jaringan Anda dan coba lagi.</p>
    <button onclick="window.location.reload()">Coba Lagi</button>
  </div>
</body>
</html>
```

#### Step 9: Icons

Place icons in `public/icons/`. Minimum required: 192×192 and 512×512.

Full set for best compatibility:

```
public/
└── icons/
    ├── icon-72x72.png
    ├── icon-96x96.png
    ├── icon-128x128.png
    ├── icon-144x144.png
    ├── icon-152x152.png
    ├── icon-192x192.png
    ├── icon-384x384.png
    └── icon-512x512.png
```

---

### Approach B: Manual Service Worker

Use when you can't add `vite-plugin-pwa` or need full SW control.

#### Manual SW File

Create `public/sw.js`:

```javascript
const CACHE_VERSION = 'v1';
const CACHE_NAME = `app-cache-${CACHE_VERSION}`;
const API_ORIGIN = 'https://api.example.com'; // ← your Laravel API URL

const PRECACHE_ASSETS = [
  '/',
  '/index.html',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
];

// ─── INSTALL ───
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_ASSETS))
  );
  self.skipWaiting();
});

// ─── ACTIVATE ───
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(
        names
          .filter((n) => n.startsWith('app-cache-') && n !== CACHE_NAME)
          .map((n) => caches.delete(n))
      )
    )
  );
  self.clients.claim();
});

// ─── FETCH ───
self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // ── Cross-origin API calls to Laravel: Network-first ──
  if (url.origin === API_ORIGIN) {
    // Skip auth endpoints — never cache tokens
    if (url.pathname.startsWith('/api/login') ||
        url.pathname.startsWith('/api/register') ||
        url.pathname.startsWith('/api/logout') ||
        url.pathname.startsWith('/sanctum/')) {
      return;
    }

    // Cache storage/uploads from API
    if (url.pathname.startsWith('/storage/')) {
      event.respondWith(
        caches.match(request).then((cached) =>
          cached || fetch(request).then((res) => {
            const clone = res.clone();
            caches.open(CACHE_NAME).then((c) => c.put(request, clone));
            return res;
          })
        )
      );
      return;
    }

    // API data: Network-first
    event.respondWith(
      fetch(request)
        .then((res) => {
          if (res.ok) {
            const clone = res.clone();
            caches.open(CACHE_NAME).then((c) => c.put(request, clone));
          }
          return res;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  // ── Same-origin requests (Vue app assets) ──
  if (url.origin === self.location.origin) {
    // SPA navigation: serve index.html from cache
    if (request.mode === 'navigate') {
      event.respondWith(
        caches.match('/index.html').then((cached) =>
          cached || fetch(request)
        )
      );
      return;
    }

    // Hashed assets (Vite output): Cache-first
    if (url.pathname.startsWith('/assets/')) {
      event.respondWith(
        caches.match(request).then((cached) =>
          cached || fetch(request).then((res) => {
            const clone = res.clone();
            caches.open(CACHE_NAME).then((c) => c.put(request, clone));
            return res;
          })
        )
      );
      return;
    }

    // Other static: Cache-first
    if (['image', 'font', 'style', 'script'].includes(request.destination)) {
      event.respondWith(
        caches.match(request).then((cached) =>
          cached || fetch(request).then((res) => {
            const clone = res.clone();
            caches.open(CACHE_NAME).then((c) => c.put(request, clone));
            return res;
          })
        )
      );
      return;
    }
  }

  // Everything else: Network-first
  event.respondWith(
    fetch(request).catch(() => caches.match(request))
  );
});

// Handle SKIP_WAITING
self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});
```

#### Manual Registration Composable

Create `src/composables/usePWA.js`:

```javascript
import { ref, onMounted, onBeforeUnmount } from 'vue';

export function usePWA() {
  const needRefresh = ref(false);
  const offlineReady = ref(false);
  const registration = ref(null);
  let refreshing = false;

  function update() {
    registration.value?.waiting?.postMessage({ type: 'SKIP_WAITING' });
  }

  function dismiss() {
    needRefresh.value = false;
    offlineReady.value = false;
  }

  function onControllerChange() {
    if (refreshing) return;
    refreshing = true;
    window.location.reload();
  }

  onMounted(async () => {
    if (!('serviceWorker' in navigator)) return;

    navigator.serviceWorker.addEventListener('controllerchange', onControllerChange);

    try {
      const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      registration.value = reg;

      if (reg.waiting) {
        needRefresh.value = true;
        return;
      }

      reg.addEventListener('updatefound', () => {
        const newWorker = reg.installing;
        if (!newWorker) return;
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed') {
            if (navigator.serviceWorker.controller) {
              needRefresh.value = true;
            } else {
              offlineReady.value = true;
            }
          }
        });
      });

      setInterval(() => reg.update(), 60 * 60 * 1000);
    } catch (err) {
      console.error('[PWA] Registration failed:', err);
    }
  });

  onBeforeUnmount(() => {
    navigator.serviceWorker?.removeEventListener('controllerchange', onControllerChange);
  });

  return { needRefresh, offlineReady, registration, update, dismiss };
}
```

Then use `usePWA()` in `PWAUpdatePrompt.vue` instead of `useRegisterSW` from
the virtual module.

---

## BE: Push Notifications (Laravel API)

This is the **only part that touches the Laravel backend**. Skip entirely if push
notifications are not needed.

### Step 1: Install web-push

```bash
composer require minishlink/web-push
```

### Step 2: Generate VAPID Keys

```bash
php artisan tinker
>>> $keys = Minishlink\WebPush\VAPID::createVapidKeys();
>>> echo "PUBLIC: {$keys['publicKey']}\nPRIVATE: {$keys['privateKey']}";
```

Add to `.env`:

```
VAPID_PUBLIC_KEY=your_public_key
VAPID_PRIVATE_KEY=your_private_key
```

Add to `config/services.php`:

```php
'vapid' => [
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
],
```

### Step 3: Migration

```bash
php artisan make:migration create_push_subscriptions_table
```

```php
Schema::create('push_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('endpoint')->unique();
    $table->string('p256dh');
    $table->string('auth');
    $table->timestamps();
});
```

### Step 4: Model

```php
// app/Models/PushSubscription.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'p256dh', 'auth'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Step 5: API Controller

```php
// app/Http/Controllers/Api/PushSubscriptionController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint'    => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $request->user()->id,
                'p256dh'  => $validated['keys']['p256dh'],
                'auth'    => $validated['keys']['auth'],
            ]
        );

        return response()->json(['message' => 'Subscribed'], 201);
    }

    public function destroy(Request $request)
    {
        PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Unsubscribed']);
    }

    // Endpoint to get VAPID public key (FE needs this)
    public function vapidKey()
    {
        return response()->json([
            'public_key' => config('services.vapid.public_key'),
        ]);
    }
}
```

### Step 6: API Routes

```php
// routes/api.php
Route::get('/push/vapid-key', [PushSubscriptionController::class, 'vapidKey']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy']);
});
```

### Step 7: Push Service

```php
// app/Services/PushNotificationService.php
namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use App\Models\PushSubscription;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('app.url'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);
    }

    public function sendToUser(int $userId, string $title, string $body, string $url = '/'): void
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        foreach ($subscriptions as $sub) {
            $this->webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->p256dh,
                    'authToken'       => $sub->auth,
                    'contentEncoding' => 'aesgcm',
                ]),
                json_encode(compact('title', 'body', 'url'))
            );
        }

        foreach ($this->webPush->flush() as $report) {
            if ($report->isExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }

    public function sendToAll(string $title, string $body, string $url = '/'): void
    {
        $subscriptions = PushSubscription::all();
        foreach ($subscriptions as $sub) {
            $this->webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->p256dh,
                    'authToken'       => $sub->auth,
                    'contentEncoding' => 'aesgcm',
                ]),
                json_encode(compact('title', 'body', 'url'))
            );
        }

        foreach ($this->webPush->flush() as $report) {
            if ($report->isExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
```

### Step 8: CORS Configuration (Laravel)

Ensure the FE domain is allowed. In `config/cors.php`:

```php
'allowed_origins' => [
    'https://app.example.com',  // your Vue.js FE domain
],
```

Or in `.env` if you use a dynamic approach.

---

## FE: Push Notification Composable

Create `src/composables/usePushNotification.js`:

```javascript
import { ref, onMounted } from 'vue';

const API_URL = import.meta.env.VITE_API_URL;

export function usePushNotification() {
  const isSubscribed = ref(false);
  const isSupported = ref(false);

  onMounted(() => {
    isSupported.value = 'PushManager' in window && 'Notification' in window;
    if (isSupported.value) checkSubscription();
  });

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
  }

  async function getVapidKey() {
    const res = await fetch(`${API_URL}/push/vapid-key`);
    const data = await res.json();
    return data.public_key;
  }

  async function subscribe(authToken) {
    if (!isSupported.value) return null;

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return null;

    const vapidKey = await getVapidKey();
    const reg = await navigator.serviceWorker.ready;
    const subscription = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapidKey),
    });

    await fetch(`${API_URL}/push/subscribe`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken}`,
      },
      body: JSON.stringify(subscription.toJSON()),
    });

    isSubscribed.value = true;
    return subscription;
  }

  async function unsubscribe(authToken) {
    const reg = await navigator.serviceWorker.ready;
    const subscription = await reg.pushManager.getSubscription();
    if (!subscription) return;

    await subscription.unsubscribe();

    await fetch(`${API_URL}/push/unsubscribe`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken}`,
      },
      body: JSON.stringify({ endpoint: subscription.endpoint }),
    });

    isSubscribed.value = false;
  }

  async function checkSubscription() {
    const reg = await navigator.serviceWorker.ready;
    const subscription = await reg.pushManager.getSubscription();
    isSubscribed.value = !!subscription;
  }

  return { isSupported, isSubscribed, subscribe, unsubscribe, checkSubscription };
}
```

#### SW Push Handlers (append to SW, both Approach A and B):

```javascript
// ─── Push Notification ───
self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? {};
  event.waitUntil(
    self.registration.showNotification(data.title || 'Notifikasi', {
      body: data.body || '',
      icon: '/icons/icon-192x192.png',
      badge: '/icons/icon-96x96.png',
      data: { url: data.url || '/' },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then((list) => {
      for (const client of list) {
        if (client.url.includes(url) && 'focus' in client) return client.focus();
      }
      return clients.openWindow(url);
    })
  );
});
```

For `vite-plugin-pwa`, add push handlers by creating a custom SW source file.
In `vite.config.js`, add:

```javascript
VitePWA({
  // ... existing config
  srcDir: 'src',
  filename: 'sw-custom.js',  // custom SW source
  strategies: 'injectManifest',  // switch from generateSW to injectManifest
  injectManifest: {
    globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
  },
})
```

Then create `src/sw-custom.js`:

```javascript
import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching';
import { registerRoute, NavigationRoute } from 'workbox-routing';
import { NetworkFirst, CacheFirst, StaleWhileRevalidate } from 'workbox-strategies';
import { CacheableResponsePlugin } from 'workbox-cacheable-response';
import { ExpirationPlugin } from 'workbox-expiration';

// Precache build assets (injected by vite-plugin-pwa)
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

const API_ORIGIN = 'https://api.example.com'; // ← hardcode production API URL

// ─── SPA navigation fallback ───
registerRoute(
  new NavigationRoute(
    new NetworkFirst({ cacheName: 'pages' }),
    { allowlist: [/^\/(?!api)/] }
  )
);

// ─── API calls: Network-first ───
registerRoute(
  ({ url }) => url.origin === API_ORIGIN && url.pathname.startsWith('/api/'),
  new NetworkFirst({
    cacheName: 'api-cache',
    plugins: [
      new CacheableResponsePlugin({ statuses: [0, 200] }),
      new ExpirationPlugin({ maxEntries: 100, maxAgeSeconds: 5 * 60 }),
    ],
    networkTimeoutSeconds: 10,
  })
);

// ─── Storage uploads from API: Cache-first ───
registerRoute(
  ({ url }) => url.origin === API_ORIGIN && url.pathname.startsWith('/storage/'),
  new CacheFirst({
    cacheName: 'storage-cache',
    plugins: [
      new CacheableResponsePlugin({ statuses: [0, 200] }),
      new ExpirationPlugin({ maxEntries: 200, maxAgeSeconds: 30 * 24 * 60 * 60 }),
    ],
  })
);

// ─── Images: Cache-first ───
registerRoute(
  ({ request }) => request.destination === 'image',
  new CacheFirst({
    cacheName: 'images-cache',
    plugins: [
      new ExpirationPlugin({ maxEntries: 100, maxAgeSeconds: 60 * 24 * 60 * 60 }),
    ],
  })
);

// ─── Fonts: Cache-first ───
registerRoute(
  ({ request }) => request.destination === 'font',
  new CacheFirst({
    cacheName: 'fonts-cache',
    plugins: [
      new ExpirationPlugin({ maxEntries: 20, maxAgeSeconds: 365 * 24 * 60 * 60 }),
    ],
  })
);

// ─── Google Fonts ───
registerRoute(
  ({ url }) => url.origin === 'https://fonts.googleapis.com',
  new StaleWhileRevalidate({ cacheName: 'google-fonts-stylesheets' })
);
registerRoute(
  ({ url }) => url.origin === 'https://fonts.gstatic.com',
  new CacheFirst({
    cacheName: 'google-fonts-webfonts',
    plugins: [
      new ExpirationPlugin({ maxEntries: 30, maxAgeSeconds: 365 * 24 * 60 * 60 }),
    ],
  })
);

// ─── Push Notification ───
self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? {};
  event.waitUntil(
    self.registration.showNotification(data.title || 'Notifikasi', {
      body: data.body || '',
      icon: '/icons/icon-192x192.png',
      badge: '/icons/icon-96x96.png',
      data: { url: data.url || '/' },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then((list) => {
      for (const client of list) {
        if (client.url.includes(url) && 'focus' in client) return client.focus();
      }
      return clients.openWindow(url);
    })
  );
});

// ─── Skip Waiting ───
self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});
```

---

## Caching Strategy Guide (Separated Architecture)

| Content Type                      | Strategy               | Why                                    |
|-----------------------------------|------------------------|----------------------------------------|
| `index.html` (SPA shell)         | Network-first          | Entry point, may change on deploy      |
| `/assets/*` (Vite hashed output) | Cache-first            | Immutable (content-hashed filenames)   |
| Lazy-loaded Vue chunks           | Cache-first            | Also hashed by Vite                    |
| API `GET` requests               | Network-first          | Data freshness from Laravel backend    |
| API auth endpoints               | Never cache            | Tokens, security risk                  |
| API `/storage/*` (uploads)       | Cache-first + expire   | User uploads, mostly static            |
| Images (local)                   | Cache-first            | Rarely change                          |
| Fonts (local/Google)             | Cache-first (long TTL) | Immutable per version                  |
| CDN assets                       | Cache-first            | Versioned URLs                         |

### Key difference from monolith:

In a separated architecture, API calls are **cross-origin** (FE at `app.example.com`,
BE at `api.example.com`). The SW handles this by matching `url.origin` against the
API domain. Make sure:

- The Laravel API has proper **CORS headers** (`Access-Control-Allow-Origin`).
- Cross-origin responses cached with `cacheableResponse: { statuses: [0, 200] }` —
  status `0` is needed for opaque CORS responses.
- Auth tokens are sent via `Authorization: Bearer` header (not cookies), since
  cross-origin cookie handling is complex and unreliable with SWs.

---

## Project Structure Summary

### FE (Vue.js SPA)

```
vue-project/
├── public/
│   ├── icons/
│   │   ├── icon-192x192.png
│   │   └── icon-512x512.png         (+ other sizes)
│   ├── screenshots/
│   │   ├── desktop.png
│   │   └── mobile.png
│   ├── offline.html                  (optional)
│   └── sw.js                         (only if manual approach)
├── src/
│   ├── main.js
│   ├── App.vue                       (mounts PWA components)
│   ├── sw-custom.js                  (only if injectManifest + push)
│   ├── components/
│   │   ├── PWAUpdatePrompt.vue
│   │   └── PWAInstallPrompt.vue
│   └── composables/
│       ├── usePWA.js                 (only if manual approach)
│       ├── useOnlineStatus.js
│       └── usePushNotification.js    (only if push enabled)
├── index.html                        (meta tags)
├── vite.config.js                    (VitePWA plugin)
└── package.json
```

### BE (Laravel API) — only if push notifications enabled

```
laravel-api/
├── config/
│   └── services.php                  (VAPID keys)
├── app/
│   ├── Http/Controllers/Api/
│   │   └── PushSubscriptionController.php
│   ├── Models/
│   │   └── PushSubscription.php
│   └── Services/
│       └── PushNotificationService.php
├── database/migrations/
│   └── xxxx_create_push_subscriptions_table.php
├── routes/
│   └── api.php                       (push subscribe/unsubscribe routes)
└── .env                              (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY)
```

---

## Testing Checklist

### FE Side
- [ ] `npm run build` succeeds — SW and manifest generated
- [ ] Manifest valid — DevTools > Application > Manifest
- [ ] SW registers — DevTools > Application > Service Workers
- [ ] App installable — Chrome shows install button
- [ ] Offline works — toggle offline, app still loads from cache
- [ ] API data cached — make API call, go offline, same call returns cached data
- [ ] Vue lazy chunks cached — navigate to lazy route, go offline, revisit works
- [ ] Update prompt shows — deploy new build, revisit, toast appears
- [ ] Install prompt shows — visit on mobile Chrome, banner appears
- [ ] Icons correct — install on home screen, check icon
- [ ] Lighthouse PWA ≥ 90

### BE Side (if push enabled)
- [ ] `GET /api/push/vapid-key` returns public key
- [ ] `POST /api/push/subscribe` stores subscription (auth required)
- [ ] `POST /api/push/unsubscribe` removes subscription
- [ ] `PushNotificationService::sendToUser()` delivers notification
- [ ] Expired subscriptions auto-cleaned

```bash
npx lighthouse https://app.example.com --only-categories=pwa --output=html \
  --output-path=./lighthouse-pwa.html
```

---

## Common Pitfalls (Separated Architecture)

1. **CORS on API caching** — Cross-origin fetch responses are "opaque" by default
   (status 0). Add `cacheableResponse: { statuses: [0, 200] }` in workbox config
   or the cache won't store them.

2. **API URL hardcoded in SW** — `import.meta.env` doesn't work in SW context.
   Hardcode the production API URL in the SW or use workbox `urlPattern` with regex.

3. **Auth token not sent from SW** — The SW `fetch` event doesn't automatically
   include `Authorization` headers from the main app. For cached API responses this
   is fine (GET only). For push subscription, the FE composable sends the token
   directly.

4. **`vite-plugin-pwa` only works after build** — In dev (`npm run dev`), there's
   no SW unless you enable `devOptions.enabled: true`. Test PWA with `npm run build`
   + `npx serve dist` (or `npx vite preview`).

5. **Vue Router history mode 404 on hosting** — Configure your web server (Nginx,
   Netlify, Vercel) to redirect all routes to `index.html`. The `navigateFallback`
   in workbox handles this for cached visits, but the server needs it too.
   Nginx example:
   ```nginx
   location / {
     try_files $uri $uri/ /index.html;
   }
   ```

6. **iOS limitations** — Push only on iOS 16.4+ for installed PWAs. No background
   sync. ~50MB cache. Always test on real devices.

7. **Mixed content** — Both FE and API must be HTTPS in production. SW won't
   register on HTTP (except localhost).

8. **Cookie-based auth (Sanctum SPA)** — If using Sanctum cookie auth instead of
   tokens, cross-origin cookies require `SameSite=None; Secure` and careful CORS
   config. Token-based auth (`Authorization: Bearer`) is simpler for separated PWAs.
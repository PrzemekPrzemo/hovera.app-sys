/*
 * hovera service worker — minimal PWA shell.
 *
 * Strategy:
 *   • cache-first  — /img/*, /build/*, /favicon.svg, /manifest.json
 *   • network-first (with /offline fallback) — /app/* (Filament must not stale)
 *   • pure network — /api/*, /livewire/*, /admin/*
 *
 * Bump CACHE_VERSION na deploy żeby unieważnić stare assets.
 */

const CACHE_VERSION = 'hovera-v1';
const OFFLINE_URL = '/offline';
const PRECACHE_URLS = [
  OFFLINE_URL,
  '/manifest.json',
  '/favicon.svg',
  '/img/pwa/icon-192.png',
  '/img/pwa/icon-512.png',
  '/img/pwa/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) =>
      // addAll is atomic — if any request fails, install fails.
      // Use Promise.allSettled to be lenient: missing icon shouldn't break SW.
      Promise.allSettled(PRECACHE_URLS.map((url) => cache.add(url)))
    ).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

function isCacheFirst(url) {
  return url.pathname.startsWith('/img/')
    || url.pathname.startsWith('/build/')
    || url.pathname === '/favicon.svg'
    || url.pathname === '/manifest.json';
}

function isPureNetwork(url) {
  return url.pathname.startsWith('/api/')
    || url.pathname.startsWith('/livewire/')
    || url.pathname.startsWith('/admin/');
}

function isAppShell(url) {
  return url.pathname === '/app' || url.pathname.startsWith('/app/');
}

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Only intercept GET. Mutations always go straight to network.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Same-origin only — let third-party (analytics, fonts CDN) pass through.
  if (url.origin !== self.location.origin) return;

  // Don't cache anything with query string except images
  // (Filament/Livewire używa ?v=... cache-bustingu, więc trzeba to zostawić appce).
  const hasQuery = url.search.length > 0;

  if (isPureNetwork(url)) {
    return; // default browser fetch
  }

  if (isCacheFirst(url)) {
    event.respondWith(cacheFirst(req));
    return;
  }

  if (isAppShell(url)) {
    if (hasQuery) return; // Livewire updates etc. — passthrough
    event.respondWith(networkFirstWithOffline(req));
    return;
  }

  // Default: try network, fall back to cache (covers public landing/signup).
  if (!hasQuery) {
    event.respondWith(networkFirstWithOffline(req));
  }
});

async function cacheFirst(request) {
  const cache = await caches.open(CACHE_VERSION);
  const hit = await cache.match(request);
  if (hit) return hit;
  try {
    const res = await fetch(request);
    if (res.ok) cache.put(request, res.clone());
    return res;
  } catch (e) {
    return hit || Response.error();
  }
}

async function networkFirstWithOffline(request) {
  const cache = await caches.open(CACHE_VERSION);
  try {
    const res = await fetch(request);
    return res;
  } catch (e) {
    const cached = await cache.match(request);
    if (cached) return cached;
    const offline = await cache.match(OFFLINE_URL);
    if (offline) return offline;
    return new Response('offline', { status: 503, statusText: 'Offline' });
  }
}

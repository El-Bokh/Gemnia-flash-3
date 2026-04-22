// 1776873174 is replaced by deploy.sh on each deploy
const CACHE_NAME = 'klek-ai-v2-1776873174';

const PRECACHE_URLS = [
  '/',
  '/manifest.json',
  '/newlogo.png',
];

// Install â€” precache shell & force activate
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

// Activate â€” delete ALL old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch â€” network-first for everything (prevents stale deploys)
self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Skip non-GET
  if (request.method !== 'GET') return;

  // Skip chrome-extension and other non-http(s) requests
  if (!request.url.startsWith('http')) return;

  // Navigation requests â€” network first, fallback to cache
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          return response;
        })
        .catch(() => caches.match('/'))
    );
    return;
  }

  // Static assets â€” network first, fallback to cache
  if (request.url.match(/\.(js|css|png|jpg|jpeg|svg|gif|woff2?|ttf|ico)$/)) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          return response;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  // API calls â€” network only (with fallback for offline)
  event.respondWith(
    fetch(request).catch(() =>
      new Response(JSON.stringify({ success: false, message: 'Network unavailable' }), {
        status: 503,
        headers: { 'Content-Type': 'application/json' },
      })
    )
  );
});

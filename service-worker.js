// service-worker.js — pełna wersja
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open('lm-cache-v3').then(async cache => {
      try {
        await cache.addAll([
            '/index_public.html',
  '/index.php',
  '/dashboard.php',
  '/edit_magazyn.php',
  '/manifest.json',
  '/icon-192.png',
  '/icon-512.png
        ]);
        console.log('✅ Service Worker installed (v3)');
      } catch (err) {
        console.warn('⚠️ Cache error:', err);
      }
    })
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== 'lm-cache-v3').map(k => caches.delete(k)))
    )
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(resp => resp || fetch(event.request))
  );
});

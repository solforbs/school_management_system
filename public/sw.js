// Minimal service worker so the app is installable as a PWA.
//
// Deliberately does no caching: the app is fully dynamic (Livewire), and a
// stale cached response would break wire:navigate and form round-trips.
// Requests go straight to the network; extend with an offline fallback
// if one is ever needed.
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(fetch(event.request));
});

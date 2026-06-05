// Minimal service worker: satisfies /sw.js requests so the SPA router does not handle them.
// BosskuAI web does not use offline caching; this avoids Vue Router "No match for /sw.js" noise.
self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

/*
 * Service worker de l'application installable (PWA).
 * Aucune donnée n'est mise en cache (rendez-vous et préconsultations sont des données de santé) :
 * seule une page « hors ligne » est conservée, affichée quand la navigation échoue sans réseau.
 */
const CACHE = 'croixbleue-v1'
const HORS_LIGNE = '/hors-ligne.html'

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.add(HORS_LIGNE)).then(() => self.skipWaiting()))
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((noms) => Promise.all(noms.filter((nom) => nom !== CACHE).map((nom) => caches.delete(nom))))
      .then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return
  event.respondWith(fetch(event.request).catch(() => caches.match(HORS_LIGNE)))
})

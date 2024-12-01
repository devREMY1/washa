const CACHE_NAME = 'app-cache-v2';

// Удаляем старые кэши во время активации нового service worker
self.addEventListener('activate', event => {
	event.waitUntil(
		caches.keys().then(cacheNames => {
			return Promise.all(
				cacheNames.map(cacheName => {
					if (cacheName !== CACHE_NAME) {
						console.log(`Удаляем старый кэш: ${cacheName}`);
						return caches.delete(cacheName);
					}
				})
			);
		})
	);
});

// Кэшируем файлы при установке нового service worker
self.addEventListener('install', event => {
	event.waitUntil(
		caches.open(CACHE_NAME).then(cache => {
			return cache.addAll([
				'./',
				'./index.html',
				'./manifest.json',
				'./icon/192.png', 
				'./icon/512.png',
			]);
		})
	);
});

// Перехватываем запросы и предоставляем кэшированный ответ, если он есть
self.addEventListener('fetch', event => {
	event.respondWith(
		caches.match(event.request).then(response => {
			// Если запрос есть в кэше, вернуть его, иначе отправить запрос в сеть
			return response || fetch(event.request);
		})
	);
});

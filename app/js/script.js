let deferredPrompt;

// Слушаем событие `beforeinstallprompt`
window.addEventListener('beforeinstallprompt', e => {
	// Останавливаем автоматическое отображение
	e.preventDefault();
	deferredPrompt = e;
	document.getElementById('installApp').style.display = 'block';
});

// Обрабатываем нажатие на кнопку
document.getElementById('installApp').addEventListener('click', () => {
	if (deferredPrompt) {
		deferredPrompt.prompt();
		deferredPrompt.userChoice.then(choiceResult => {
			if (choiceResult.outcome === 'accepted') {
				console.log('Застосунок встановлено');
			} else {
				console.log('Встановлення застосунку відмінено');
			}
			deferredPrompt = null;
		});
	}
});

// Регистрация service-worker
if ('serviceWorker' in navigator) {
	navigator.serviceWorker
		.register('./service-worker.js')
		.then(registration => {
			console.log('Service Worker зарегистрирован: ', registration);
		})
		.catch(error => {
			console.error('Ошибка регистрации Service Worker: ', error);
		});
}

// Проверяем устройство и показываем кнопку для Android
if (window.matchMedia('(display-mode: standalone)').matches) {
	// Приложение уже установлено
	document.getElementById('installApp').style.display = 'none';
} else {
	// Для ПК показываем только сообщение
	document.body.innerHTML = '<h1>Доступно тільки для мобільних пристроїв</h1>';
}

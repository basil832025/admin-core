{{-- Обработчик 419 ошибок для Filament админки --}}
<script>
(function() {
    // Перехватываем все fetch запросы
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args)
            .then(response => {
                // Если получили 419 ошибку, перезагружаем страницу
                if (response.status === 419) {
                    // Пробуем получить JSON ответ
                    return response.clone().json()
                        .then(data => {
                            if (data && data.reload) {
                                console.log('[Filament] Session expired, reloading page...');
                                window.location.reload();
                                return Promise.reject(new Error('Session expired'));
                            }
                            // Если нет флага reload, все равно перезагружаем
                            console.log('[Filament] CSRF token expired, reloading page...');
                            window.location.reload();
                            return Promise.reject(new Error('CSRF token expired'));
                        })
                        .catch(() => {
                            // Если не удалось распарсить JSON, просто перезагружаем
                            console.log('[Filament] CSRF token expired, reloading page...');
                            window.location.reload();
                            return Promise.reject(new Error('CSRF token expired'));
                        });
                }
                return response;
            })
            .catch(error => {
                // Если это ошибка перезагрузки, игнорируем
                if (error.message === 'Session expired' || error.message === 'CSRF token expired') {
                    return Promise.reject(error);
                }
                throw error;
            });
    };

    // Перехватываем XMLHttpRequest (для Livewire и других AJAX запросов)
    const originalXHROpen = XMLHttpRequest.prototype.open;
    const originalXHRSend = XMLHttpRequest.prototype.send;
    
    XMLHttpRequest.prototype.open = function(method, url, ...args) {
        this._url = url;
        return originalXHROpen.apply(this, [method, url, ...args]);
    };
    
    XMLHttpRequest.prototype.send = function(...args) {
        this.addEventListener('load', function() {
            if (this.status === 419) {
                console.log('[Filament] CSRF token expired (XHR), reloading page...');
                window.location.reload();
            }
        });
        return originalXHRSend.apply(this, args);
    };
    
    // Перехватываем Livewire запросы (если используется)
    if (window.Livewire) {
        const originalLivewireRequest = window.Livewire.request;
        if (originalLivewireRequest) {
            window.Livewire.request = function(...args) {
                return originalLivewireRequest.apply(this, args)
                    .catch(error => {
                        if (error.status === 419 || error.response?.status === 419) {
                            console.log('[Filament] CSRF token expired (Livewire), reloading page...');
                            window.location.reload();
                        }
                        throw error;
                    });
            };
        }
    }
    
    // Обновляем CSRF токен при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            const newToken = metaToken.getAttribute('content');
            if (newToken) {
                // Обновляем токен для всех форм на странице
                document.querySelectorAll('input[name="_token"]').forEach(input => {
                    input.value = newToken;
                });
            }
        }
    });
    
    // Периодически обновляем CSRF токен (каждые 30 минут)
    setInterval(function() {
        fetch('/csrf-token', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.token) {
                const metaToken = document.querySelector('meta[name="csrf-token"]');
                if (metaToken) {
                    metaToken.setAttribute('content', data.token);
                }
                document.querySelectorAll('input[name="_token"]').forEach(input => {
                    input.value = data.token;
                });
            }
        })
        .catch(() => {
            // Игнорируем ошибки при обновлении токена
        });
    }, 30 * 60 * 1000); // 30 минут
})();
</script>

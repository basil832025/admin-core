// resources/js/alpine/favorite-store.js

/** Регистрирует общий favorites-store (бейдж в хедере) */
export function registerFavoriteStore(Alpine, { infoUrl = '/favorites/info', initQty = 0 } = {}) {
    // создаём store один раз
    if (!Alpine.store('favorites')) {
        Alpine.store('favorites', {
            qty: Number(initQty || 0),
            setQty(q) { this.qty = Number(q || 0); },
        });
    }

    // первичная загрузка состояния
    fetch(infoUrl, { 
        headers: { 
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        cache: 'no-store',
    })
        .then(r => r.json())
        .then(d => {
            Alpine.store('favorites').setQty(Number(d.qty ?? 0));
        })
        .catch(() => {});

    // любое обновление избранного — обновляем бейдж
    window.addEventListener('favorite-updated', (e) => {
        const d = e.detail || {};
        if ('qty' in d) {
            Alpine.store('favorites').setQty(d.qty);
        } else {
            // если qty не пришёл — обновим через API
            fetch(infoUrl, { 
                headers: { 
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            })
                .then(r => r.json())
                .then(d => {
                    Alpine.store('favorites').setQty(Number(d.qty ?? 0));
                })
                .catch(() => {});
        }
    });
}



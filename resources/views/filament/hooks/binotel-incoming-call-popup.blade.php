<script>
    (function () {
        if (window.__ccBinotelPollerInit) {
            return;
        }

        window.__ccBinotelPollerInit = true;

        const endpoint = '/admin/integrations/binotel/incoming-call/next';
        let modal = null;
        let isBusy = false;
        let pollTimer = null;

        function ensureStyles() {
            if (document.getElementById('binotel-popup-styles')) {
                return;
            }

            const style = document.createElement('style');
            style.id = 'binotel-popup-styles';
            style.textContent = `
                @keyframes binotelPulse {
                    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.45), 0 20px 25px -5px rgba(15, 23, 42, 0.25); }
                    70% { box-shadow: 0 0 0 14px rgba(239, 68, 68, 0), 0 22px 28px -6px rgba(15, 23, 42, 0.28); }
                    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0), 0 20px 25px -5px rgba(15, 23, 42, 0.25); }
                }
                @keyframes binotelBell {
                    0%, 100% { transform: rotate(0deg); }
                    20% { transform: rotate(10deg); }
                    40% { transform: rotate(-10deg); }
                    60% { transform: rotate(6deg); }
                    80% { transform: rotate(-6deg); }
                }
                .binotel-popup-alert {
                    animation: binotelPulse 1.8s infinite;
                    border: 2px solid #fb7185;
                    background: linear-gradient(145deg, #fff7ed 0%, #ffe4e6 45%, #fff1f2 100%);
                }
                .binotel-popup-bell {
                    display: inline-block;
                    transform-origin: center top;
                    animation: binotelBell 1.4s ease-in-out infinite;
                }
            `;

            document.head.appendChild(style);
        }

        function ensureModal() {
            if (modal) {
                return modal;
            }

            ensureStyles();

            modal = document.createElement('div');
            modal.className = 'binotel-popup-alert';
            modal.style.position = 'fixed';
            modal.style.top = '1rem';
            modal.style.right = '1rem';
            modal.style.width = '22rem';
            modal.style.maxWidth = 'calc(100vw - 2rem)';
            modal.style.zIndex = '99999';
            modal.style.display = 'none';
            modal.style.borderRadius = '12px';
            modal.style.padding = '12px';

            modal.innerHTML = '' +
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">' +
                    '<strong style="font-size:14px;color:#7f1d1d;display:flex;align-items:center;gap:6px;"><span class="binotel-popup-bell">*</span> Вхідний дзвінок</strong>' +
                    '<span style="font-size:12px;color:#9f1239;background:#ffe4e6;border:1px solid #fecdd3;border-radius:999px;padding:2px 8px;">Binotel</span>' +
                '</div>' +
                '<div data-role="name" style="font-size:15px;font-weight:700;color:#7f1d1d;"></div>' +
                '<div data-role="phone" style="margin-top:2px;font-size:13px;color:#9a3412;"></div>' +
                '<div data-role="pbx-number" style="margin-top:4px;font-size:12px;color:#7f1d1d;"></div>' +
                '<div data-role="pbx-name" style="margin-top:2px;font-size:12px;color:#7f1d1d;"></div>' +
                '<div data-role="site" style="margin-top:2px;font-size:12px;color:#1d4ed8;font-weight:700;"></div>' +
                '<div data-role="desc" style="margin-top:6px;font-size:12px;color:#881337;"></div>' +
                '<div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">' +
                    '<button type="button" data-role="dismiss" style="border:1px solid #fda4af;background:#fff1f2;color:#9f1239;border-radius:8px;padding:6px 10px;font-size:12px;cursor:pointer;">Скасувати</button>' +
                    '<button type="button" data-role="create" style="border:0;background:#dc2626;color:#fff;border-radius:8px;padding:6px 10px;font-size:12px;cursor:pointer;">Створити замовлення</button>' +
                '</div>';

            document.body.appendChild(modal);

            modal.querySelector('[data-role="dismiss"]').addEventListener('click', function () {
                hideModal();
            });

            modal.querySelector('[data-role="create"]').addEventListener('click', function () {
                const url = modal.getAttribute('data-create-url') || '';
                hideModal();
                if (url) {
                    window.location.href = url;
                }
            });

            return modal;
        }

        function showModal(call) {
            const root = ensureModal();
            root.setAttribute('data-create-url', call.create_url || '');
            root.querySelector('[data-role="name"]').textContent = call.name || 'Невідомий клієнт';
            root.querySelector('[data-role="phone"]').textContent = call.phone ? ('Телефон: ' + call.phone) : 'Телефон: —';
            root.querySelector('[data-role="pbx-number"]').textContent = call.pbx_number ? ('Лінія (номер): ' + call.pbx_number) : 'Лінія (номер): —';
            root.querySelector('[data-role="pbx-name"]').textContent = call.pbx_name ? ('Лінія (назва): ' + call.pbx_name) : 'Лінія (назва): —';
            const siteLabel = (call.point_name || call.source_name || 'Основний сайт');
            root.querySelector('[data-role="site"]').textContent = 'Сайт: ' + siteLabel;
            root.querySelector('[data-role="desc"]').textContent = call.description || '';
            root.style.display = 'block';
        }

        function hideModal() {
            if (! modal) {
                return;
            }

            modal.style.display = 'none';
            modal.removeAttribute('data-create-url');
        }

        async function poll() {
            if (isBusy) {
                return;
            }

            if (modal && modal.style.display === 'block') {
                return;
            }

            isBusy = true;

            try {
                const response = await fetch(endpoint, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (! response.ok) {
                    return;
                }

                const payload = await response.json();
                if (payload && payload.call) {
                    showModal(payload.call);
                }
            } catch (e) {
                // silent retry on next poll
            } finally {
                isBusy = false;
            }
        }

        function startPolling() {
            if (pollTimer) {
                return;
            }

            poll();
            pollTimer = setInterval(poll, 5000);
        }

        document.addEventListener('livewire:init', startPolling);
        document.addEventListener('DOMContentLoaded', startPolling);
    })();
</script>

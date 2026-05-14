<script>
    (function () {
        if (window.__ccCourierCommentPollerInit) {
            return;
        }

        window.__ccCourierCommentPollerInit = true;

        const endpoint = '/admin/callcenter/courier-comment/next';
        const markReadEndpoint = '/admin/callcenter/courier-comment/mark-read';
        let container = null;
        let isBusy = false;
        let pollTimer = null;

        function isCallcenterOrdersPage() {
            const path = window.location.pathname || '';
            return path.startsWith('/admin/callcenter/orders');
        }

        function ensureStyles() {
            if (document.getElementById('courier-comment-popup-styles')) {
                return;
            }

            const style = document.createElement('style');
            style.id = 'courier-comment-popup-styles';
            style.textContent = `
                @keyframes courierNotePulse {
                    0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.32), 0 18px 24px -6px rgba(15, 23, 42, 0.2); }
                    70% { box-shadow: 0 0 0 12px rgba(220, 38, 38, 0), 0 20px 26px -8px rgba(15, 23, 42, 0.25); }
                    100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0), 0 18px 24px -6px rgba(15, 23, 42, 0.2); }
                }

                .courier-comment-popup {
                    animation: courierNotePulse 2s infinite;
                    border: 2px solid #ef4444;
                    background: linear-gradient(140deg, #fff1f2 0%, #ffe4e6 45%, #fef2f2 100%);
                    border-radius: 12px;
                    padding: 12px;
                }
            `;

            document.head.appendChild(style);
        }

        function ensureContainer() {
            if (container) {
                return container;
            }

            ensureStyles();

            container = document.createElement('div');
            container.id = 'courier-comment-popup-container';
            container.style.position = 'fixed';
            container.style.top = '1rem';
            container.style.left = '1rem';
            container.style.width = '24rem';
            container.style.maxWidth = 'calc(100vw - 2rem)';
            container.style.zIndex = '99998';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '8px';

            document.body.appendChild(container);

            return container;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function markAsRead(orderId) {
            if (!orderId) {
                return;
            }

            await fetch(markReadEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ ids: [Number(orderId)] }),
            });
        }

        function renderPopups(comments) {
            const root = ensureContainer();
            const incomingSignatures = new Set(comments.map((item) => item.signature).filter(Boolean));

            Array.from(root.querySelectorAll('[data-signature]')).forEach((node) => {
                const signature = node.getAttribute('data-signature') || '';
                if (signature && !incomingSignatures.has(signature)) {
                    node.remove();
                }
            });

            comments.forEach((comment) => {
                if (!comment?.signature) {
                    return;
                }

                if (root.querySelector(`[data-signature="${comment.signature}"]`)) {
                    return;
                }

                const card = document.createElement('div');
                card.className = 'courier-comment-popup';
                card.setAttribute('data-signature', comment.signature);
                card.innerHTML = '' +
                    '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">' +
                        '<strong style="font-size:14px;color:#7f1d1d;">Повідомлення від кур\'єра</strong>' +
                        '<span style="font-size:11px;color:#991b1b;background:#fee2e2;border:1px solid #fecaca;border-radius:999px;padding:2px 8px;">Логістика</span>' +
                    '</div>' +
                    '<div style="font-size:13px;font-weight:700;color:#991b1b;">Замовлення: #' + escapeHtml(comment.order_number || comment.order_id || '—') + '</div>' +
                    '<div style="margin-top:6px;font-size:13px;color:#7f1d1d;line-height:1.35;background:#fee2e2;border-radius:8px;padding:8px;">' + escapeHtml(comment.text || '') + '</div>' +
                    '<div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end;">' +
                        '<button type="button" data-role="dismiss" style="border:1px solid #fca5a5;background:#fff1f2;color:#9f1239;border-radius:8px;padding:6px 10px;font-size:12px;cursor:pointer;">Прочитано</button>' +
                        '<button type="button" data-role="open" style="border:0;background:#dc2626;color:#fff;border-radius:8px;padding:6px 10px;font-size:12px;cursor:pointer;">Перейти в замовлення</button>' +
                    '</div>';

                card.querySelector('[data-role="dismiss"]').addEventListener('click', async function () {
                    try {
                        await markAsRead(comment.order_id);
                    } catch (e) {
                        return;
                    }
                    card.remove();
                });

                card.querySelector('[data-role="open"]').addEventListener('click', async function () {
                    try {
                        await markAsRead(comment.order_id);
                    } catch (e) {
                        // continue to open
                    }

                    card.remove();

                    if (comment.edit_url) {
                        window.location.href = comment.edit_url;
                    }
                });

                root.appendChild(card);
            });
        }

        async function poll() {
            if (isBusy || !isCallcenterOrdersPage()) {
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

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const comments = Array.isArray(payload?.comments) ? payload.comments : [];

                renderPopups(comments);
            } catch (e) {
                // retry silently on next poll
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

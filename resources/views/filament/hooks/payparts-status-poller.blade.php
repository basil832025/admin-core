<script>
    (function () {
        if (window.__ccPaypartsStatusPollerInit) {
            return;
        }

        if (! /^\/admin\/callcenter\/orders\/?$/.test(window.location.pathname)) {
            return;
        }

        window.__ccPaypartsStatusPollerInit = true;

        const endpoint = '/admin/callcenter/payparts/pending/sync';
        const intervalMs = {{ (int) config('services.payparts.admin_polling_interval_ms', 10000) }};
        let isBusy = false;
        let pollTimer = null;

        async function poll() {
            if (isBusy) {
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
                if (Array.isArray(payload.changed_order_ids) && payload.changed_order_ids.length > 0) {
                    window.location.reload();
                }
            } catch (e) {
                // Retry on the next interval.
            } finally {
                isBusy = false;
            }
        }

        function startPolling() {
            if (pollTimer) {
                return;
            }

            poll();
            pollTimer = window.setInterval(poll, Math.max(5000, intervalMs));
        }

        document.addEventListener('livewire:init', startPolling);
        document.addEventListener('DOMContentLoaded', startPolling);
    })();
</script>
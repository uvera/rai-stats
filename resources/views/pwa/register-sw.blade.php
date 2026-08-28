{{-- Service worker registration --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' })
                .then(function (registration) {
                    registration.addEventListener('updatefound', function () {
                        const worker = registration.installing;
                        if (!worker) {
                            return;
                        }
                        worker.addEventListener('statechange', function () {
                            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                                worker.postMessage('SKIP_WAITING');
                            }
                        });
                    });
                })
                .catch(function (error) {
                    console.error('Service worker registration failed:', error);
                });

            let refreshing = false;
            navigator.serviceWorker.addEventListener('controllerchange', function () {
                if (refreshing) {
                    return;
                }
                refreshing = true;
                window.location.reload();
            });
        });
    }
</script>

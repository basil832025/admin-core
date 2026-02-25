<script>
    (function () {
        function initLogisticsRouteMap(root) {
            if (!root || root.dataset.initialized === '1') {
                return;
            }

            root.dataset.initialized = '1';

            const iframe = root.querySelector('[data-role="route-frame"]');
            const status = root.querySelector('[data-role="route-status"]');
            const externalLink = root.querySelector('[data-role="route-link"]');

            const googleKey = root.dataset.googleKey || '';
            const destinationAddress = root.dataset.destinationAddress || '';
            const destinationLat = parseFloat(root.dataset.destinationLat || '');
            const destinationLng = parseFloat(root.dataset.destinationLng || '');
            const kitchenLat = parseFloat(root.dataset.kitchenLat || '');
            const kitchenLng = parseFloat(root.dataset.kitchenLng || '');
            const kitchenAddress = root.dataset.kitchenAddress || '';

            const tRouteNotAvailable = root.dataset.tRouteNotAvailable || 'Route is unavailable.';
            const tRouteOriginAuto = root.dataset.tRouteOriginAuto || 'Route is ready.';
            const tRouteOriginKitchen = root.dataset.tRouteOriginKitchen || 'GPS unavailable, using kitchen point.';
            const tRouteOriginManual = root.dataset.tRouteOriginManual || 'Set route start point manually.';
            const tRouteDestinationMissing = root.dataset.tRouteDestinationMissing || 'Destination is missing.';
            const tRouteOriginGps = root.dataset.tRouteOriginGps || 'Route starts from your GPS location.';

            const hasDestinationCoords = Number.isFinite(destinationLat) && Number.isFinite(destinationLng);
            const destination = hasDestinationCoords
                ? `${destinationLat},${destinationLng}`
                : destinationAddress;

            const hasKitchenCoords = Number.isFinite(kitchenLat) && Number.isFinite(kitchenLng);
            const kitchenOrigin = hasKitchenCoords
                ? `${kitchenLat},${kitchenLng}`
                : kitchenAddress;

            const setStatus = (message) => {
                if (status) {
                    status.textContent = message;
                }
            };

            const buildEmbedUrl = (origin) => {
                if (googleKey) {
                    const embedUrl = new URL(
                        origin
                            ? 'https://www.google.com/maps/embed/v1/directions'
                            : 'https://www.google.com/maps/embed/v1/place'
                    );
                    embedUrl.searchParams.set('key', googleKey);
                    if (origin) {
                        embedUrl.searchParams.set('destination', destination);
                        embedUrl.searchParams.set('mode', 'driving');
                    } else {
                        embedUrl.searchParams.set('q', destination);
                    }

                    if (origin) {
                        embedUrl.searchParams.set('origin', origin);
                    }

                    return embedUrl.toString();
                }

                const legacyEmbed = new URL('https://maps.google.com/maps');
                legacyEmbed.searchParams.set('output', 'embed');
                legacyEmbed.searchParams.set('hl', 'uk');
                legacyEmbed.searchParams.set('t', 'm');

                if (origin) {
                    legacyEmbed.searchParams.set('saddr', origin);
                    legacyEmbed.searchParams.set('daddr', destination);
                } else {
                    legacyEmbed.searchParams.set('q', destination);
                }

                return legacyEmbed.toString();
            };

            const setRoute = (origin, originLabel) => {
                if (!destination) {
                    setStatus(tRouteNotAvailable);
                    return;
                }

                const embedUrl = buildEmbedUrl(origin);

                const openUrl = new URL('https://www.google.com/maps/dir/');
                openUrl.searchParams.set('api', '1');
                openUrl.searchParams.set('destination', destination);
                if (origin) {
                    openUrl.searchParams.set('origin', origin);
                }

                if (iframe) {
                    iframe.src = embedUrl;
                }

                if (externalLink) {
                    externalLink.href = openUrl.toString();
                }

                setStatus(originLabel || tRouteOriginAuto);
            };

            const setFromKitchenFallback = () => {
                if (kitchenOrigin) {
                    setRoute(kitchenOrigin, tRouteOriginKitchen);
                    return;
                }

                setRoute('', tRouteOriginManual);
            };

            if (!hasDestinationCoords && !destinationAddress) {
                setStatus(tRouteDestinationMissing);
                return;
            }

            if (!navigator.geolocation || !window.isSecureContext) {
                setFromKitchenFallback();
                return;
            }

            let settled = false;
            const forceFallbackTimer = window.setTimeout(() => {
                if (settled) {
                    return;
                }

                settled = true;
                setFromKitchenFallback();
            }, 8500);

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    if (settled) {
                        return;
                    }

                    settled = true;
                    window.clearTimeout(forceFallbackTimer);

                    const lat = position && position.coords ? position.coords.latitude : null;
                    const lng = position && position.coords ? position.coords.longitude : null;

                    if (Number.isFinite(lat) && Number.isFinite(lng)) {
                        setRoute(`${lat},${lng}`, tRouteOriginGps);
                        return;
                    }

                    setFromKitchenFallback();
                },
                () => {
                    if (settled) {
                        return;
                    }

                    settled = true;
                    window.clearTimeout(forceFallbackTimer);
                    setFromKitchenFallback();
                },
                {
                    enableHighAccuracy: true,
                    timeout: 7000,
                    maximumAge: 120000,
                }
            );
        }

        function initAllLogisticsRouteMaps(container) {
            const scope = container || document;
            scope.querySelectorAll('[data-logistics-route-map="1"]').forEach(initLogisticsRouteMap);
        }

        document.addEventListener('livewire:init', () => {
            initAllLogisticsRouteMaps(document);

            const observer = new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    for (const node of mutation.addedNodes) {
                        if (!node || node.nodeType !== 1) {
                            continue;
                        }

                        if (node.matches && node.matches('[data-logistics-route-map="1"]')) {
                            initLogisticsRouteMap(node);
                            continue;
                        }

                        initAllLogisticsRouteMaps(node);
                    }
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });
        });

        document.addEventListener('filament:form-mounted', () => {
            initAllLogisticsRouteMaps(document);
        });
    })();
</script>

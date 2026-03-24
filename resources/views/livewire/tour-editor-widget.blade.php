<div>
    <div id="circle-cursor" class="hidden"></div>

    <script>
        document.addEventListener('livewire:initialized', function () {
            const findInitialTour = (payload) => {
                const tours = payload?.tours ?? [];
                const seenTours = JSON.parse(localStorage.getItem('tours') ?? '[]');

                return tours.find((tour) => {
                    const routeMatches = tour.route === window.location.pathname;
                    const shouldReplay = !payload?.only_visible_once || !seenTours.includes(tour.id);

                    return ((tour.alwaysShow && tour.routesIgnored)
                        || (tour.alwaysShow && !tour.routesIgnored && routeMatches)
                        || (tour.routesIgnored && shouldReplay)
                        || (routeMatches && shouldReplay));
                }) ?? null;
            };

            const waitForInitialTarget = (payload, callback) => {
                const initialTour = findInitialTour(payload);

                if (!initialTour) {
                    callback();

                    return;
                }

                let steps = [];

                try {
                    steps = JSON.parse(initialTour.steps ?? '[]');
                } catch {
                    callback();

                    return;
                }

                const selector = steps[0]?.element;

                if (!selector) {
                    callback();

                    return;
                }

                let attempts = 0;
                const maxAttempts = 40;

                const poll = () => {
                    if (document.querySelector(selector) || attempts >= maxAttempts) {
                        callback();

                        return;
                    }

                    attempts += 1;
                    window.setTimeout(poll, 100);
                };

                poll();
            };

            const redispatchLoadedElements = (payload) => {
                waitForInitialTarget(payload, () => {
                    window.Livewire.dispatch('filament-tour::loaded-elements', payload);
                });
            };

            const redispatchCssSelectorStatus = (payload) => {
                window.Livewire.dispatch('filament-tour::change-css-selector-status', payload);
            };

            Livewire.on('filament-tour-editor::loaded-elements-ready', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;

                redispatchLoadedElements(data);
            });

            Livewire.on('filament-tour-editor::css-selector-status-ready', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;

                redispatchCssSelectorStatus(data);
            });
        });
    </script>
</div>

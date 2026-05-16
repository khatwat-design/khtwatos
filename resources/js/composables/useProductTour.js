import { buildTourSteps } from '@/data/productTours/steps.js';
import { pickTourElement } from '@/utils/productTourDom.js';
import { router, usePage } from '@inertiajs/vue3';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { ref } from 'vue';

const activeDriver = ref(null);
const runningTourId = ref(null);

function mergeProductToursPayload(payload) {
    if (!payload?.product_tours) {
        return;
    }

    const page = usePage();
    page.props.product_tours = payload.product_tours;
}

function waitMs(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function resolveSteps(tourId, page) {
    const tours = page.props.product_tours || {};

    const raw = buildTourSteps(tourId, {
        personaLabel: tours.persona_label,
    });

    return raw.filter((s) => {
        if (!s.element) {
            return true;
        }
        try {
            const el = typeof s.element === 'function' ? s.element() : pickTourElement(s.element);
            return Boolean(el);
        } catch {
            return false;
        }
    });
}

export function useProductTour() {
    const page = usePage();

    function destroyActive() {
        if (activeDriver.value) {
            try {
                activeDriver.value.destroy();
            } catch {
                /* تجاهل */
            }
            activeDriver.value = null;
        }
        runningTourId.value = null;
    }

    async function persistTour(tourId, action) {
        const routeName = action === 'skip' ? 'product-tours.skip' : 'product-tours.complete';

        const res = await window.axios.post(route(routeName, tourId), {}, {
            headers: { Accept: 'application/json' },
        });
        mergeProductToursPayload(res?.data);
    }

    async function startTour(tourId, { navigate = true, force = false } = {}) {
        if (!tourId || runningTourId.value) {
            return false;
        }

        const catalog = page.props.product_tours?.catalog || [];
        const meta = catalog.find((row) => row.id === tourId);
        if (!meta || (!force && meta.status !== 'pending')) {
            return false;
        }

        const currentRoute = route().current() || '';
        const expectedRoute = meta.route_name || '';
        const onRoute = !expectedRoute || currentRoute === expectedRoute;

        if (navigate && expectedRoute && !onRoute) {
            router.visit(route(expectedRoute), {
                onFinish: () => {
                    window.setTimeout(() => void startTour(tourId, { navigate: false, force }), 700);
                },
            });
            return true;
        }

        await waitMs(400);

        const steps = resolveSteps(tourId, page);
        if (steps.length < 1) {
            return false;
        }

        runningTourId.value = tourId;
        const tourIdCapture = tourId;
        let exitAction = 'completed';
        let persisted = false;

        const persistOnce = async (action) => {
            if (persisted) {
                return;
            }
            persisted = true;
            await persistTour(tourIdCapture, action);
        };

        const instance = driver({
            showProgress: true,
            progressText: '{{current}} من {{total}}',
            nextBtnText: 'التالي',
            prevBtnText: 'السابق',
            doneBtnText: 'تم',
            allowClose: true,
            overlayOpacity: 0.6,
            stagePadding: 10,
            stageRadius: 10,
            popoverOffset: 12,
            popoverClass: 'kht-product-tour-popover',
            steps,
            onCloseClick: () => {
                exitAction = 'skip';
                instance.destroy();
            },
            onDestroyed: (_el, _step, { state, config }) => {
                if (runningTourId.value !== tourIdCapture) {
                    return;
                }
                const total = config?.steps?.length || steps.length;
                if (exitAction !== 'skip' && state?.activeIndex != null && state.activeIndex < total - 1) {
                    exitAction = 'skip';
                }
                destroyActive();
                void persistOnce(exitAction);
            },
        });

        activeDriver.value = instance;
        instance.drive();

        return true;
    }

    async function skipTour(tourId) {
        destroyActive();
        await persistTour(tourId, 'skip');
    }

    async function resetAllTours() {
        destroyActive();
        const res = await window.axios.post(route('product-tours.reset'), {}, {
            headers: { Accept: 'application/json' } },
        );
        mergeProductToursPayload(res?.data);
    }

    return {
        activeDriver,
        runningTourId,
        startTour,
        skipTour,
        resetAllTours,
        destroyActive,
    };
}

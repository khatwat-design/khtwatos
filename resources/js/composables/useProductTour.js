import { buildTourSteps } from '@/data/productTours/steps.js';
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
    const isAdminHome = Boolean(page.props.auth?.can?.viewAdminHome);

    return buildTourSteps(tourId, {
        personaLabel: tours.persona_label,
        isAdminHome,
    }).filter((s) => {
        if (!s.element) {
            return true;
        }
        try {
            const el =
                typeof s.element === 'function' ? s.element() : document.querySelector(s.element);
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
        const routeName =
            action === 'skip'
                ? 'product-tours.skip'
                : action === 'completed'
                  ? 'product-tours.complete'
                  : 'product-tours.complete';

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
        const pattern = meta.route_match || '';
        const onRoute =
            pattern === currentRoute ||
            (String(pattern).endsWith('.*') &&
                currentRoute.startsWith(String(pattern).slice(0, -1)));

        if (navigate && meta.route_name && !onRoute) {
            router.visit(route(meta.route_name), {
                onFinish: () => {
                    window.setTimeout(() => void startTour(tourId, { navigate: false }), 450);
                },
            });
            return true;
        }

        await waitMs(280);

        const steps = resolveSteps(tourId, page);
        if (!steps.length) {
            await persistTour(tourId, 'skip');
            return false;
        }

        runningTourId.value = tourId;
        const tourIdCapture = tourId;
        let exitAction = 'completed';

        const instance = driver({
            showProgress: true,
            progressText: '{{current}} من {{total}}',
            nextBtnText: 'التالي',
            prevBtnText: 'السابق',
            doneBtnText: 'تم',
            allowClose: true,
            overlayOpacity: 0.55,
            stagePadding: 8,
            stageRadius: 12,
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
                void persistTour(tourIdCapture, exitAction);
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
            headers: { Accept: 'application/json' },
        });
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

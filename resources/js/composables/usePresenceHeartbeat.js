/**
 * يقوم بإرسال نبضات وقت النشاط للخادم كل ~30 ثانية أثناء بقاء التبويب نشطاً.
 * لا يُحسب الوقت أثناء إخفاء التبويب أو غياب التفاعل لأكثر من INACTIVITY_THRESHOLD_MS.
 */
import { onBeforeUnmount, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const HEARTBEAT_INTERVAL_MS = 30_000;
const INACTIVITY_THRESHOLD_MS = 2 * 60_000;

export function usePresenceHeartbeat() {
    const page = usePage();
    let lastTick = Date.now();
    let lastInteraction = Date.now();
    let intervalId = null;

    function bumpInteraction() {
        lastInteraction = Date.now();
    }

    async function sendHeartbeat() {
        if (!page.props.auth?.user) return;
        if (typeof document !== 'undefined' && document.visibilityState !== 'visible') {
            lastTick = Date.now();
            return;
        }
        const now = Date.now();
        const elapsed = Math.min(HEARTBEAT_INTERVAL_MS + 5_000, now - lastTick) / 1000;
        const inactive = now - lastInteraction > INACTIVITY_THRESHOLD_MS;
        lastTick = now;
        if (elapsed <= 0 || inactive) return;
        try {
            await window.axios.post(
                route('attendance.heartbeat'),
                { elapsed: Math.round(elapsed) },
                { headers: { Accept: 'application/json' } },
            );
        } catch {
            /* تجاهل أخطاء الشبكة العابرة */
        }
    }

    onMounted(() => {
        if (typeof window === 'undefined') return;
        intervalId = window.setInterval(sendHeartbeat, HEARTBEAT_INTERVAL_MS);
        ['mousemove', 'keydown', 'click', 'touchstart', 'scroll', 'focus'].forEach((evt) =>
            window.addEventListener(evt, bumpInteraction, { passive: true }),
        );
        document?.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                lastTick = Date.now();
                lastInteraction = Date.now();
            }
        });
        sendHeartbeat();
    });

    onBeforeUnmount(() => {
        if (intervalId) {
            window.clearInterval(intervalId);
        }
        ['mousemove', 'keydown', 'click', 'touchstart', 'scroll', 'focus'].forEach((evt) =>
            window.removeEventListener(evt, bumpInteraction),
        );
    });
}

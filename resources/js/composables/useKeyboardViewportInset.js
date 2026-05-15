import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Tracks visualViewport for composers outside immersive chat (padding above keyboard).
 */
export function useKeyboardViewportInset(getEnabled = () => true) {
    const insetBottom = ref(0);

    let cleanup = null;
    let rafId = 0;

    function readNow() {
        if (typeof window === 'undefined' || !getEnabled()) {
            insetBottom.value = 0;
            return;
        }

        const vv = window.visualViewport;
        if (!vv) {
            insetBottom.value = 0;
            return;
        }

        const nextInset = Math.max(0, window.innerHeight - vv.offsetTop - vv.height);
        if (Math.abs(insetBottom.value - nextInset) < 2) {
            return;
        }
        insetBottom.value = nextInset;
    }

    function read() {
        if (typeof window === 'undefined') {
            return;
        }
        if (rafId) {
            return;
        }
        rafId = requestAnimationFrame(() => {
            rafId = 0;
            readNow();
        });
    }

    function bind() {
        unbind();
        if (typeof window === 'undefined') {
            return;
        }
        readNow();
        const vv = window.visualViewport;
        const handler = () => read();
        vv?.addEventListener('resize', handler);
        vv?.addEventListener('scroll', handler);
        window.addEventListener('resize', handler);
        cleanup = () => {
            vv?.removeEventListener('resize', handler);
            vv?.removeEventListener('scroll', handler);
            window.removeEventListener('resize', handler);
            if (rafId) {
                cancelAnimationFrame(rafId);
                rafId = 0;
            }
            cleanup = null;
        };
    }

    function unbind() {
        cleanup?.();
    }

    onMounted(bind);
    onBeforeUnmount(unbind);

    const composerStyle = computed(() => {
        if (insetBottom.value <= 0) {
            return undefined;
        }

        return {
            paddingBottom: `calc(${insetBottom.value}px + env(safe-area-inset-bottom, 0px))`,
        };
    });

    return {
        insetBottom,
        composerStyle,
        read,
        bind,
        unbind,
    };
}

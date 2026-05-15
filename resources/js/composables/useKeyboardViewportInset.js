import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Tracks visualViewport so fixed chat UI and composers stay above the mobile keyboard.
 */
export function useKeyboardViewportInset(getEnabled = () => true) {
    const offsetTop = ref(0);
    const viewportHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 0);
    const insetBottom = ref(0);

    let cleanup = null;

    function read() {
        if (typeof window === 'undefined' || !getEnabled()) {
            offsetTop.value = 0;
            viewportHeight.value = window?.innerHeight ?? 0;
            insetBottom.value = 0;
            return;
        }

        const vv = window.visualViewport;
        if (!vv) {
            offsetTop.value = 0;
            viewportHeight.value = window.innerHeight;
            insetBottom.value = 0;
            return;
        }

        offsetTop.value = vv.offsetTop;
        viewportHeight.value = vv.height;
        insetBottom.value = Math.max(0, window.innerHeight - vv.offsetTop - vv.height);
    }

    function bind() {
        unbind();
        if (typeof window === 'undefined') {
            return;
        }
        read();
        const vv = window.visualViewport;
        const handler = () => read();
        vv?.addEventListener('resize', handler);
        vv?.addEventListener('scroll', handler);
        window.addEventListener('resize', handler);
        cleanup = () => {
            vv?.removeEventListener('resize', handler);
            vv?.removeEventListener('scroll', handler);
            window.removeEventListener('resize', handler);
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
        offsetTop,
        viewportHeight,
        insetBottom,
        composerStyle,
        read,
        bind,
        unbind,
    };
}

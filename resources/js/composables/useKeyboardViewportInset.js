import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Tracks visualViewport for mobile chat: dock composer above keyboard without resizing the full shell.
 */
export function useKeyboardViewportInset(getEnabled = () => true) {
    const offsetTop = ref(0);
    const viewportHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 0);
    const insetBottom = ref(0);

    let cleanup = null;
    let rafId = 0;

    function readNow() {
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

        const nextInset = Math.max(0, window.innerHeight - vv.offsetTop - vv.height);
        const nextHeight = vv.height;

        if (
            Math.abs(insetBottom.value - nextInset) < 2 &&
            Math.abs(viewportHeight.value - nextHeight) < 2 &&
            Math.abs(offsetTop.value - vv.offsetTop) < 2
        ) {
            return;
        }

        offsetTop.value = vv.offsetTop;
        viewportHeight.value = nextHeight;
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

    /** شريط الكتابة ثابت أسفل الشاشة — يتحرك مع لوحة المفاتيح فقط */
    const composerDockStyle = computed(() => {
        if (!getEnabled() || typeof window === 'undefined') {
            return undefined;
        }

        const kb = insetBottom.value;

        return {
            position: 'fixed',
            left: '0',
            right: '0',
            bottom: `${kb}px`,
            zIndex: 110,
            transform: 'translateZ(0)',
        };
    });

    return {
        offsetTop,
        viewportHeight,
        insetBottom,
        composerStyle,
        composerDockStyle,
        read,
        bind,
        unbind,
    };
}

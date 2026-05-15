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

        // iOS يمرّر الصفحة عند فتح لوحة المفاتيح — يُفسد top+bottom على العناصر الثابتة
        if (getEnabled() && (window.scrollX !== 0 || window.scrollY !== 0)) {
            window.scrollTo(0, 0);
        }
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

    /**
     * لوحة fixed بملء visualViewport — translateY بدل top=offsetTop لتجنب قفز المُدخل على iOS.
     */
    const immersiveShellStyle = computed(() => {
        if (!getEnabled() || typeof window === 'undefined') {
            return undefined;
        }

        const height = Math.max(200, viewportHeight.value);

        return {
            top: '0',
            left: '0',
            right: '0',
            bottom: 'auto',
            height: `${height}px`,
            maxHeight: `${height}px`,
            transform: `translateY(${Math.max(0, offsetTop.value)}px)`,
        };
    });

    return {
        offsetTop,
        viewportHeight,
        insetBottom,
        composerStyle,
        immersiveShellStyle,
        read,
        bind,
        unbind,
    };
}

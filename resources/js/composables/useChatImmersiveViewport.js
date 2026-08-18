import { onBeforeUnmount, onMounted } from 'vue';

const VAR_TOP = '--chat-vv-top';
const VAR_HEIGHT = '--chat-vv-height';

function clearVars() {
    if (typeof document === 'undefined') {
        return;
    }
    document.documentElement.style.removeProperty(VAR_TOP);
    document.documentElement.style.removeProperty(VAR_HEIGHT);
}

/**
 * يزامن ارتفاع لوحة الدردشة الغامرة مع visualViewport — المُدخل يبقى في أسفل الـ flex (مثل واتساب).
 */
export function useChatImmersiveViewport(getEnabled = () => true) {
    let cleanup = null;
    let rafId = 0;

    function applyNow() {
        if (typeof window === 'undefined') {
            return;
        }

        if (!getEnabled()) {
            clearVars();
            return;
        }

        const root = document.documentElement;
        const vv = window.visualViewport;

        if (!vv) {
            root.style.setProperty(VAR_TOP, '0px');
            root.style.setProperty(VAR_HEIGHT, `${window.innerHeight}px`);
            return;
        }

        root.style.setProperty(VAR_TOP, `${Math.max(0, vv.offsetTop)}px`);
        root.style.setProperty(VAR_HEIGHT, `${Math.max(200, Math.round(vv.height))}px`);
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
            applyNow();
        });
    }

    function bind() {
        unbind();
        if (typeof window === 'undefined') {
            return;
        }
        applyNow();
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
        clearVars();
    }

    onMounted(bind);
    onBeforeUnmount(unbind);

    return { read, bind, unbind };
}

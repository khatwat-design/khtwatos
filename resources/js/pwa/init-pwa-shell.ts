/**
 * تهيئة غلاف PWA (متصفح مثبت كتطبيق): class على html + ارتفاع نافذة مرئي دقيق.
 * لا يتعارض مع Capacitor — يُتخطى إذا كان الغلاف الأصلي مفعّلاً.
 */
function isInstalledPwa(): boolean {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return false;
    }
    if (document.documentElement.classList.contains('capacitor-native')) {
        return false;
    }
    try {
        if (window.matchMedia('(display-mode: standalone)').matches) {
            return true;
        }
        if (window.matchMedia('(display-mode: fullscreen)').matches) {
            return true;
        }
    } catch {
        /* ignore */
    }
    // iOS Safari: إضافة للشاشة الرئيسية
    return (window.navigator as { standalone?: boolean }).standalone === true;
}

function applyPwaClass(enabled: boolean): void {
    document.documentElement.classList.toggle('pwa-standalone', enabled);
}

let visualViewportCleanup: (() => void) | null = null;

function bindVisualViewportHeight(): void {
    if (visualViewportCleanup) {
        return;
    }
    const root = document.documentElement;
    const update = (): void => {
        const vv = window.visualViewport;
        const h = vv?.height ?? window.innerHeight;
        if (h > 0) {
            root.style.setProperty('--app-visual-vh', `${Math.round(h)}px`);
        }
    };

    const onOrientation = (): void => {
        window.setTimeout(update, 250);
    };

    update();

    const vv = window.visualViewport;
    if (vv) {
        vv.addEventListener('resize', update);
        vv.addEventListener('scroll', update);
    }
    window.addEventListener('resize', update);
    window.addEventListener('orientationchange', onOrientation);

    visualViewportCleanup = (): void => {
        if (vv) {
            vv.removeEventListener('resize', update);
            vv.removeEventListener('scroll', update);
        }
        window.removeEventListener('resize', update);
        window.removeEventListener('orientationchange', onOrientation);
        root.style.removeProperty('--app-visual-vh');
    };
}

export function initPwaShell(): void {
    if (typeof document === 'undefined' || typeof window === 'undefined') {
        return;
    }

    const sync = (): void => {
        const installed = isInstalledPwa();
        applyPwaClass(installed);
        if (installed && !visualViewportCleanup) {
            bindVisualViewportHeight();
        } else if (!installed && visualViewportCleanup) {
            visualViewportCleanup();
            visualViewportCleanup = null;
        }
    };

    sync();

    try {
        window.matchMedia('(display-mode: standalone)').addEventListener('change', sync);
        window.matchMedia('(display-mode: fullscreen)').addEventListener('change', sync);
    } catch {
        /* Safari قديم */
    }
}

export function teardownPwaShellForTests(): void {
    visualViewportCleanup?.();
    visualViewportCleanup = null;
    document.documentElement.classList.remove('pwa-standalone');
}

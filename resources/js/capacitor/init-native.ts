import { Capacitor } from '@capacitor/core';

export function isCapacitorNative(): boolean {
    return Capacitor.isNativePlatform();
}

/**
 * تهيئة الغلاف الأصلي: شريط الحالة، مراقبة الشبكة، استقبال روابط فتح التطبيق (OAuth / App Links).
 * لا يُخفِي شاشة الإقلاع — يُترك ذلك لـ InertiaRoot بعد أول إطار Vue.
 */
export async function initCapacitorNativeShell(): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
        return;
    }

    document.documentElement.classList.add('capacitor-native');

    const [{ StatusBar, Style }, { Network }, { App }] = await Promise.all([
        import('@capacitor/status-bar'),
        import('@capacitor/network'),
        import('@capacitor/app'),
    ]);

    try {
        await StatusBar.setOverlaysWebView({ overlay: false });
        await StatusBar.setStyle({ style: Style.Dark });
        await StatusBar.setBackgroundColor({ color: '#000000' });
    } catch {
        /* بعض الأجهزة قد لا تدعم كل الخيارات */
    }

    const broadcast = (connected: boolean) => {
        window.dispatchEvent(new CustomEvent('capacitor:network', { detail: { connected } }));
    };

    await Network.addListener('networkStatusChange', (s) => {
        broadcast(s.connected);
    });

    broadcast((await Network.getStatus()).connected);

    await App.addListener('appUrlOpen', ({ url }) => {
        if (!url) {
            return;
        }
        try {
            const incoming = new URL(url);
            const sameOrigin =
                incoming.origin === window.location.origin ||
                incoming.host === new URL(window.location.href).host;
            if (sameOrigin || incoming.protocol === 'https:' || incoming.protocol === 'http:') {
                window.location.href = url;
            }
        } catch {
            window.location.href = url;
        }
    });
}

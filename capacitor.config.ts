import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Laravel + Inertia: عيّن CAPACITOR_SERVER_URL قبل `cap sync` للإنتاج.
 */
const liveUrl = (process.env.CAPACITOR_SERVER_URL || '').trim() || undefined;

const config: CapacitorConfig = {
    appId: 'design.khatwat.erp',
    appName: 'خارج المخزون',
    /** Laravel لا يُصدّر index.html هنا؛ الغلاف يُنشَأ في resources/capacitor/www عبر npm run cap:sync */
    webDir: 'resources/capacitor/www',
    server: liveUrl
        ? {
              url: liveUrl.replace(/\/$/, ''),
              cleartext: false,
          }
        : undefined,
    android: {
        allowMixedContent: false,
        webContentsDebuggingEnabled: process.env.CAPACITOR_ANDROID_WEBVIEW_DEBUG === '1',
    },
    ios: {
        contentInset: 'automatic',
        preferredContentMode: 'mobile',
    },
    plugins: {
        SplashScreen: {
            launchShowDuration: 0,
            launchAutoHide: false,
            backgroundColor: '#000000',
            showSpinner: false,
            androidSplashResourceName: 'splash',
            splashFullScreen: true,
            splashImmersive: true,
        },
        PushNotifications: {
            presentationOptions: ['badge', 'sound', 'alert'],
        },
    },
};

export default config;

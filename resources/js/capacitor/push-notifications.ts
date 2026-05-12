import { Capacitor } from '@capacitor/core';

/**
 * جاهز للتوسعة لاحقًا (FCM / APNS + تسجيل الرمز في Laravel).
 * لا يطلب أذونات ولا يسجّل أي مستمع حتى تفعّل المنظومة من الواجهة الخلفية.
 */
export async function registerPushWhenBackendReady(): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
        return;
    }

    const enabled = import.meta.env.VITE_ENABLE_PUSH_NOTIFICATIONS === 'true';
    if (!enabled) {
        return;
    }

    const { PushNotifications } = await import('@capacitor/push-notifications');

    const perm = await PushNotifications.requestPermissions();
    if (perm.receive !== 'granted') {
        return;
    }

    await PushNotifications.register();

    await PushNotifications.addListener('registration', (_token) => {
        // TODO: إرسال الرمز إلى Laravel (جهاز المستخدم) وتخزينه للـ campaigns
    });

    await PushNotifications.addListener('registrationError', (_err) => {
        // يمكن ربطه بـ Sentry لاحقًا
    });
}

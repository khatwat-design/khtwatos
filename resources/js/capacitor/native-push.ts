import { Capacitor } from '@capacitor/core';

export async function initNativePushForAuthenticatedSession(options?: {
    deviceStoreUrl?: string;
    onSilentRefresh?: () => void;
}): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
        return;
    }

    try {
        const { PushNotifications } = await import('@capacitor/push-notifications');

        await PushNotifications.removeAllListeners();

        const storeUrl = options?.deviceStoreUrl;

        await PushNotifications.addListener('registration', async (token) => {
            const value = token?.value;
            if (!value || !storeUrl || typeof window === 'undefined' || !window.axios) {
                return;
            }
            const platform = Capacitor.getPlatform() === 'ios' ? 'ios' : 'android';
            try {
                await window.axios.post(
                    storeUrl,
                    { token: value, platform },
                    { headers: { Accept: 'application/json' } },
                );
            } catch (err) {
                console.warn('[push] failed to store token on server', err);
            }
        });

        await PushNotifications.addListener('registrationError', (err) => {
            console.warn('[push] registrationError', err);
        });

        await PushNotifications.addListener('pushNotificationReceived', () => {
            options?.onSilentRefresh?.();
        });

        await PushNotifications.addListener('pushNotificationActionPerformed', () => {
            options?.onSilentRefresh?.();
        });

        let perm = await PushNotifications.checkPermissions();

        /** أندرويد قد يُرجع prompt-with-rationale وليس prompt فقط — نطلب الإذن ما لم يكن ممنوحًا */
        if (perm.receive !== 'granted') {
            perm = await PushNotifications.requestPermissions();
        }

        if (perm.receive !== 'granted') {
            console.warn('[push] notifications permission not granted:', perm.receive);
            return;
        }

        await PushNotifications.register();
    } catch (e) {
        console.warn('[push] init failed', e);
    }
}

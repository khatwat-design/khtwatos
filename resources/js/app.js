import '../css/app.css';
import './bootstrap';

import InertiaRoot from '@/InertiaRoot.vue';
import { Capacitor } from '@capacitor/core';
import { initCapacitorNativeShell } from '@/capacitor/init-native';
import { initPwaShell } from '@/pwa/init-pwa-shell';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

async function bootstrap() {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-app-shell', 'booting');
    }

    if (!Capacitor.isNativePlatform()) {
        initPwaShell();
    }
    await initCapacitorNativeShell();

    createInertiaApp({
        title: (title) => `${title} - ${appName}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob('./Pages/**/*.vue'),
            ),
        setup({ el, App, props, plugin }) {
            return createApp({
                render: () =>
                    h(
                        InertiaRoot,
                        null,
                        {
                            default: () => h(App, props),
                        },
                    ),
            })
                .use(plugin)
                .use(ZiggyVue)
                .mount(el);
        },
        progress: false,
    });
}

bootstrap();

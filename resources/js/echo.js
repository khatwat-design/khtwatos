import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

function csrfToken() {
    if (typeof document === 'undefined') {
        return '';
    }
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

if (reverbKey) {
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const useTls = scheme === 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? (useTls ? 443 : 8080));

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: useTls,
        enabledTransports: useTls ? ['ws', 'wss'] : ['ws'],
        authEndpoint: `${window.location.origin}/broadcasting/auth`,
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
        },
    });
}

export function teamChatRealtimeEnabled() {
    return Boolean(reverbKey && typeof window !== 'undefined' && window.Echo);
}

export function employeeCallRealtimeEnabled() {
    return teamChatRealtimeEnabled();
}

export function userPrivateChannel(userId) {
    return `App.Models.User.${userId}`;
}

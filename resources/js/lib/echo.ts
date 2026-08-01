import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> {
    echo ??= new Echo<'reverb'>({
        broadcaster: 'reverb',
        Pusher,
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return echo;
}

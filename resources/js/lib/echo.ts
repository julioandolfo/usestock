import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Echo?: Echo<'reverb'>;
        Pusher: typeof Pusher;
    }
}

type ReverbConfig = {
    key: string | null;
    host: string | null;
    port: number | null;
    scheme: 'http' | 'https' | null;
};

let booted = false;

export function bootEcho(config: ReverbConfig | null | undefined): Echo<'reverb'> | null {
    if (booted && window.Echo) {
        return window.Echo;
    }
    if (!config?.key || !config.host) {
        return null;
    }

    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port ?? 8080,
        wssPort: config.port ?? 8080,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
    });

    window.Echo = echo;
    booted = true;
    return echo;
}

export function getEcho(): Echo<'reverb'> | null {
    return window.Echo ?? null;
}

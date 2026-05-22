import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { bootEcho } from '@/lib/echo';

export type DownloadEvent = {
    id: string; // public_id
    status: string;
    item_name: string | null;
    provider_slug: string | null;
    file_name: string | null;
    file_size_bytes: number | null;
    failure_reason: string | null;
    ready_at: string | null;
    expires_at: string | null;
};

type SharedProps = {
    auth?: { user?: { id: number } | null };
    reverb?: {
        key: string | null;
        host: string | null;
        port: number | null;
        scheme: 'http' | 'https' | null;
    };
};

/**
 * Subscribes to the current user's downloads private channel and invokes
 * the callback every time the backend broadcasts a status change.
 *
 * Returns an unsubscribe function so callers don't leak listeners on unmount.
 */
export function useDownloadEvents(onEvent: (event: DownloadEvent) => void): void {
    const page = usePage<SharedProps>();
    const userId = page.props.auth?.user?.id ?? null;
    const reverb = page.props.reverb;

    useEffect(() => {
        if (!userId) return;
        const echo = bootEcho(reverb);
        if (!echo) return;

        const channel = echo.private(`users.${userId}.downloads`);
        channel.listen('.download.status', onEvent);

        return () => {
            try {
                channel.stopListening('.download.status');
                echo.leave(`users.${userId}.downloads`);
            } catch {
                /* noop on teardown */
            }
        };
    }, [userId, reverb?.key, reverb?.host, reverb?.port, reverb?.scheme, onEvent]);
}

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDownloadEvents, type DownloadEvent } from '@/hooks/use-download-events';
import AppLayout from '@/layouts/app-layout';
import { formatBytes, formatDate, STATUS_LABELS, STATUS_VARIANTS } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useCallback, useState } from 'react';

type Download = {
    public_id: string;
    source_url: string;
    item_name: string | null;
    provider_slug: string | null;
    status: string;
    file_name: string | null;
    file_size_bytes: number | null;
    failure_reason: string | null;
    ready_at: string | null;
    expires_at: string | null;
    upstream_thumb_url: string | null;
    credits_charged: number;
};

export default function DownloadShow({ download }: { download: Download }) {
    const [state, setState] = useState<Download>(download);

    const onEvent = useCallback((event: DownloadEvent) => {
        if (event.id !== state.public_id) return;
        setState((prev) => ({
            ...prev,
            status: event.status,
            item_name: event.item_name ?? prev.item_name,
            provider_slug: event.provider_slug ?? prev.provider_slug,
            file_name: event.file_name ?? prev.file_name,
            file_size_bytes: event.file_size_bytes ?? prev.file_size_bytes,
            failure_reason: event.failure_reason ?? prev.failure_reason,
            ready_at: event.ready_at ?? prev.ready_at,
            expires_at: event.expires_at ?? prev.expires_at,
        }));
    }, [state.public_id]);
    useDownloadEvents(onEvent);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Downloads', href: '/downloads' },
        { title: state.public_id.slice(0, 8), href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={state.item_name || `Download ${state.public_id}`} />
            <div className="grid gap-4 p-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <div className="flex items-start justify-between gap-3">
                            <CardTitle className="truncate">{state.item_name || state.source_url}</CardTitle>
                            <Badge variant={STATUS_VARIANTS[state.status] ?? 'secondary'}>
                                {STATUS_LABELS[state.status] ?? state.status}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {state.upstream_thumb_url && (
                            <div className="overflow-hidden rounded-lg border bg-muted">
                                <img src={state.upstream_thumb_url} alt={state.item_name ?? ''} className="max-h-72 w-full object-contain" />
                            </div>
                        )}

                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <Detail label="Provider" value={state.provider_slug || '—'} />
                            <Detail label="Arquivo" value={state.file_name || '—'} />
                            <Detail label="Tamanho" value={formatBytes(state.file_size_bytes)} />
                            <Detail label="Créditos" value={state.credits_charged} />
                            <Detail label="Pronto em" value={formatDate(state.ready_at)} />
                            <Detail label="Expira em" value={formatDate(state.expires_at)} />
                        </dl>

                        {state.failure_reason && (
                            <p className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                                {state.failure_reason}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ações</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {state.status === 'ready' ? (
                            <Button asChild className="w-full">
                                <Link href={route('library.file', state.public_id)}>Baixar arquivo</Link>
                            </Button>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Aguardando processamento. O botão de download aparece automaticamente.
                            </p>
                        )}
                        <Button asChild variant="outline" className="w-full">
                            <Link href={route('downloads.index')}>Voltar à lista</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Detail({ label, value }: { label: string; value: string | number }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}

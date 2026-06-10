import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useDownloadEvents, type DownloadEvent } from '@/hooks/use-download-events';
import AppLayout from '@/layouts/app-layout';
import { formatBytes, formatDate, isInProgress, STATUS_LABELS, STATUS_VARIANTS } from '@/lib/format';
import { Loader2 } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useCallback, useEffect, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Downloads', href: '/downloads' }];

type Download = {
    id: number;
    public_id: string;
    source_url: string;
    item_name: string | null;
    provider_slug: string | null;
    status: string;
    file_name: string | null;
    file_size_bytes: number | null;
    failure_reason: string | null;
    ready_at: string | null;
    created_at: string;
    served_count: number;
    last_served_at: string | null;
};

type Props = {
    downloads: {
        data: Download[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function DownloadsIndex({ downloads }: Props) {
    const [bulkText, setBulkText] = useState('');
    const [items, setItems] = useState<Download[]>(downloads.data);

    const [isPremium, setIsPremium] = useState(true);
    const [wantZip, setWantZip] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{ links?: string }>({});
    const [successMsg, setSuccessMsg] = useState<string | null>(null);

    const onEvent = useCallback((event: DownloadEvent) => {
        setItems((prev) => {
            const idx = prev.findIndex((d) => d.public_id === event.id);
            if (idx === -1) return prev;
            const next = [...prev];
            next[idx] = {
                ...next[idx],
                status: event.status,
                item_name: event.item_name ?? next[idx].item_name,
                provider_slug: event.provider_slug ?? next[idx].provider_slug,
                file_name: event.file_name ?? next[idx].file_name,
                file_size_bytes: event.file_size_bytes ?? next[idx].file_size_bytes,
                failure_reason: event.failure_reason ?? next[idx].failure_reason,
                ready_at: event.ready_at ?? next[idx].ready_at,
            };
            return next;
        });
    }, []);
    useDownloadEvents(onEvent);

    // Sync `items` whenever the server re-renders with fresh recentDownloads.
    useEffect(() => {
        setItems((prev) => {
            const byId = new Map(prev.map((p) => [p.public_id, p]));
            const merged = downloads.data.map((fresh) => byId.get(fresh.public_id) ?? fresh);
            for (const local of prev) {
                if (!merged.find((d) => d.public_id === local.public_id)) {
                    merged.push(local);
                }
            }
            return merged;
        });
    }, [downloads.data]);

    const linkList = useMemo(
        () =>
            bulkText
                .split(/\r?\n/)
                .map((l) => l.trim())
                .filter(Boolean),
        [bulkText],
    );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!linkList.length) return;
        setProcessing(true);
        setErrors({});
        setSuccessMsg(null);
        const n = linkList.length;
        router.post(
            route('downloads.store'),
            { links: linkList, is_premium: isPremium, zip: wantZip },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    setBulkText('');
                    const info = (page.props as { flash?: { lastSubmit?: { total: number; queued: number; reused: number } } }).flash?.lastSubmit;
                    setSuccessMsg(buildSubmitMessage(info, n));
                    setTimeout(() => setSuccessMsg(null), 8000);
                    router.reload({ only: ['downloads'] });
                },
                onError: (errs) => setErrors(errs as { links?: string }),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Downloads" />
            <div className="grid gap-4 p-3 sm:gap-6 sm:p-4 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Novo download</CardTitle>
                        <CardDescription>
                            Um link por linha. Os créditos são reservados na hora — se um item falhar, o estorno é
                            automático.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {successMsg && (
                            <div className="mb-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-700 dark:text-emerald-300">
                                {successMsg}
                            </div>
                        )}
                        {errors.links && (
                            <div className="mb-3 rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                                {errors.links}
                            </div>
                        )}
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="links">Links</Label>
                                <Textarea
                                    id="links"
                                    rows={8}
                                    value={bulkText}
                                    onChange={(e) => setBulkText(e.target.value)}
                                    placeholder={'https://www.shutterstock.com/...\nhttps://www.freepik.com/...'}
                                />
                                {errors.links && <p className="text-sm text-destructive">{errors.links}</p>}
                                <p className="text-xs text-muted-foreground">
                                    {linkList.length} link(s) detectado(s)
                                </p>
                            </div>

                            <div className="flex items-center justify-between rounded-md border p-3">
                                <div>
                                    <p className="text-sm font-medium">Conteúdo premium</p>
                                    <p className="text-xs text-muted-foreground">Necessário para a maioria dos sites.</p>
                                </div>
                                <Switch checked={isPremium} onCheckedChange={setIsPremium} />
                            </div>

                            <div className="flex items-center justify-between rounded-md border p-3">
                                <div>
                                    <p className="text-sm font-medium">Gerar ZIP do lote</p>
                                    <p className="text-xs text-muted-foreground">Empacota tudo após os itens ficarem prontos.</p>
                                </div>
                                <Switch checked={wantZip} onCheckedChange={setWantZip} />
                            </div>

                            <Button type="submit" disabled={processing || !linkList.length} className="w-full">
                                {processing ? 'Enfileirando…' : 'Iniciar download'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Histórico recente</CardTitle>
                        <CardDescription>Status em tempo real — atualiza sozinho conforme cada item progride.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Nenhum download ainda.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Tamanho</TableHead>
                                        <TableHead>Quando</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((d) => (
                                        <TableRow key={d.public_id}>
                                            <TableCell className="max-w-xs">
                                                <p className="truncate font-medium">{d.item_name || d.source_url}</p>
                                                {d.failure_reason && (
                                                    <p className="truncate text-xs text-destructive">{d.failure_reason}</p>
                                                )}
                                            </TableCell>
                                            <TableCell>{d.provider_slug || '—'}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANTS[d.status] ?? 'secondary'} className="gap-1.5">
                                                    {isInProgress(d.status) && (
                                                        <Loader2 className="size-3 animate-spin" />
                                                    )}
                                                    {STATUS_LABELS[d.status] ?? d.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-xs">{formatBytes(d.file_size_bytes)}</TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDate(d.ready_at ?? d.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    {d.status === 'ready' && (
                                                        <a
                                                            href={route('library.file', d.public_id)}
                                                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                                            download
                                                        >
                                                            Baixar
                                                            {d.served_count > 0 && (
                                                                <span className="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums">
                                                                    {d.served_count}×
                                                                </span>
                                                            )}
                                                        </a>
                                                    )}
                                                    <Link
                                                        href={route('downloads.show', d.public_id)}
                                                        className="text-xs text-muted-foreground hover:underline"
                                                    >
                                                        Detalhes
                                                    </Link>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function buildSubmitMessage(
    info: { total: number; queued: number; reused: number } | undefined,
    fallbackCount: number,
): string {
    if (!info) {
        return `${fallbackCount} link${fallbackCount === 1 ? '' : 's'} enfileirado${fallbackCount === 1 ? '' : 's'}.`;
    }
    const { total, queued, reused } = info;
    if (reused > 0 && queued === 0) {
        return `${reused} arquivo${reused === 1 ? '' : 's'} já estava${reused === 1 ? '' : 'm'} na sua biblioteca — sem cobrança.`;
    }
    if (reused > 0 && queued > 0) {
        return `${queued} item${queued === 1 ? '' : 'ns'} enfileirado${queued === 1 ? '' : 's'} · ${reused} reaproveitado${reused === 1 ? '' : 's'} sem cobrança.`;
    }
    return `${total} link${total === 1 ? '' : 's'} enfileirado${total === 1 ? '' : 's'}. O status aparece em tempo real.`;
}

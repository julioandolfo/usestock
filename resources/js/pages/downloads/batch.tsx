import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDownloadEvents, type DownloadEvent } from '@/hooks/use-download-events';
import AppLayout from '@/layouts/app-layout';
import { formatBytes, STATUS_LABELS, STATUS_VARIANTS } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

type Batch = {
    public_id: string;
    total_items: number;
    completed_items: number;
    failed_items: number;
    status: string;
    zip_requested: boolean;
    zip_path: string | null;
};

type Item = {
    public_id: string;
    source_url: string;
    item_name: string | null;
    provider_slug: string | null;
    status: string;
    file_size_bytes: number | null;
    served_count: number;
};

type Props = { batch: Batch; items: Item[] };

export default function BatchShow({ batch, items: initial }: Props) {
    const [items, setItems] = useState<Item[]>(initial);
    const ids = useMemo(() => new Set(items.map((i) => i.public_id)), [items]);

    const onEvent = useCallback(
        (event: DownloadEvent) => {
            if (!ids.has(event.id)) return;
            setItems((prev) =>
                prev.map((i) =>
                    i.public_id === event.id
                        ? {
                              ...i,
                              status: event.status,
                              item_name: event.item_name ?? i.item_name,
                              provider_slug: event.provider_slug ?? i.provider_slug,
                              file_size_bytes: event.file_size_bytes ?? i.file_size_bytes,
                          }
                        : i,
                ),
            );
        },
        [ids],
    );
    useDownloadEvents(onEvent);

    const progress = batch.total_items > 0 ? Math.round((batch.completed_items / batch.total_items) * 100) : 0;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Downloads', href: '/downloads' },
        { title: `Lote ${batch.public_id.slice(0, 8)}`, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Lote ${batch.public_id}`} />
            <div className="grid gap-4 p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>Lote #{batch.public_id.slice(0, 8)}</CardTitle>
                            <Badge>{batch.status}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Progress value={progress} />
                        <p className="text-sm text-muted-foreground">
                            {batch.completed_items} concluído(s) · {batch.failed_items} falhou(aram) · {batch.total_items} no total
                        </p>
                        {batch.zip_requested && batch.zip_path && (
                            <Button asChild>
                                <a href={route('batches.zip', batch.public_id)} download>
                                    Baixar ZIP do lote
                                </a>
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Itens</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Provider</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Tamanho</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((d) => (
                                    <TableRow key={d.public_id}>
                                        <TableCell className="max-w-xs truncate">{d.item_name || d.source_url}</TableCell>
                                        <TableCell>{d.provider_slug || '—'}</TableCell>
                                        <TableCell>
                                            <Badge variant={STATUS_VARIANTS[d.status] ?? 'secondary'}>
                                                {STATUS_LABELS[d.status] ?? d.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatBytes(d.file_size_bytes)}</TableCell>
                                        <TableCell>
                                            {d.status === 'ready' && (
                                                <a
                                                    href={route('library.file', d.public_id)}
                                                    className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
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
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

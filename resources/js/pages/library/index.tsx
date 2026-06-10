import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CalendarClock, Download } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Biblioteca', href: '/library' }];

type LibraryItem = {
    public_id: string;
    item_name: string | null;
    file_name: string | null;
    provider_slug: string | null;
    upstream_thumb_url: string | null;
    ready_at: string | null;
    expires_at: string | null;
    served_count: number;
};

function daysUntil(iso: string | null): number | null {
    if (!iso) return null;
    const diff = new Date(iso).getTime() - Date.now();
    if (Number.isNaN(diff)) return null;
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
}

export default function LibraryIndex({ items }: { items: { data: LibraryItem[] } }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Biblioteca" />
            <div className="grid gap-3 p-3 sm:grid-cols-2 sm:gap-4 sm:p-4 lg:grid-cols-4">
                {items.data.length === 0 && (
                    <p className="col-span-full text-sm text-muted-foreground">
                        Nenhum arquivo disponível ainda.
                    </p>
                )}
                {items.data.map((item) => {
                    const remaining = daysUntil(item.expires_at);
                    const expiringSoon = remaining !== null && remaining <= 3;
                    return (
                        <Card key={item.public_id} className="flex flex-col overflow-hidden">
                            <div className="aspect-video bg-muted">
                                {item.upstream_thumb_url && (
                                    <img
                                        src={item.upstream_thumb_url}
                                        alt={item.item_name ?? ''}
                                        className="h-full w-full object-cover"
                                    />
                                )}
                            </div>
                            <CardHeader className="pb-2">
                                <CardTitle className="line-clamp-2 text-sm leading-snug">
                                    {item.item_name || item.file_name}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-1 flex-col justify-between gap-2 pb-3 pt-0 text-xs text-muted-foreground">
                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="truncate capitalize">{item.provider_slug}</p>
                                        {item.served_count > 0 && (
                                            <span className="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[10px] tabular-nums">
                                                {item.served_count}× baixado
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1.5 text-[11px]">
                                        <Download className="size-3 shrink-0" />
                                        <span className="truncate">Baixado em {formatDate(item.ready_at)}</span>
                                    </div>
                                    <div
                                        className={`flex items-center gap-1.5 text-[11px] ${
                                            expiringSoon ? 'text-amber-600 dark:text-amber-400' : ''
                                        }`}
                                    >
                                        <CalendarClock className="size-3 shrink-0" />
                                        <span className="truncate">
                                            {item.expires_at
                                                ? remaining !== null && remaining <= 0
                                                    ? `Expira hoje (${formatDate(item.expires_at)})`
                                                    : `Expira em ${remaining} ${remaining === 1 ? 'dia' : 'dias'} · ${formatDate(item.expires_at)}`
                                                : 'Sem expiração'}
                                        </span>
                                    </div>
                                </div>
                                <a
                                    href={route('library.file', item.public_id)}
                                    className="mt-1 inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                    download
                                >
                                    Baixar arquivo
                                </a>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </AppLayout>
    );
}

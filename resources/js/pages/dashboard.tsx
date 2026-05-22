import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useDownloadEvents, type DownloadEvent } from '@/hooks/use-download-events';
import AppLayout from '@/layouts/app-layout';
import { formatBytes, formatNumber, STATUS_LABELS, STATUS_VARIANTS } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Coins, Download, FileArchive, Sparkles, TrendingUp, Zap } from 'lucide-react';
import { FormEventHandler, useCallback, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type Stats = {
    credits_balance: number;
    library_count: number;
    month_downloads: number;
    total_downloads: number;
};

type Limits = { bulk_max_items: number; file_ttl_days: number; max_concurrent_per_user: number };

type ProviderTypeRow = {
    type: string;
    kind: string;
    kind_label: string;
    resolution: string | null;
    is_premium: boolean;
    credits: number;
};

type ProviderCard = {
    slug: string;
    name: string;
    host: string | null;
    types: ProviderTypeRow[];
};

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
    created_at: string;
    upstream_thumb_url: string | null;
};

type Props = {
    stats: Stats;
    recentDownloads: Download[];
    providers: ProviderCard[];
    limits: Limits;
};

export default function Dashboard({ stats, recentDownloads, providers, limits }: Props) {
    const [bulkText, setBulkText] = useState('');
    const [items, setItems] = useState<Download[]>(recentDownloads);
    const [balance] = useState(stats.credits_balance);

    const [isPremium, setIsPremium] = useState(true);
    const [wantZip, setWantZip] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{ links?: string }>({});
    const [successMsg, setSuccessMsg] = useState<string | null>(null);

    const linkList = useMemo(
        () =>
            bulkText
                .split(/\r?\n/)
                .map((l) => l.trim())
                .filter(Boolean),
        [bulkText],
    );

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
                onSuccess: () => {
                    setBulkText('');
                    setSuccessMsg(`${n} link${n === 1 ? '' : 's'} enviado${n === 1 ? '' : 's'} para a fila. Acompanhe abaixo.`);
                    setTimeout(() => setSuccessMsg(null), 6000);
                },
                onError: (errs) => setErrors(errs as { links?: string }),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const lowestPremium = useMemo(() => {
        const prices = providers.flatMap((p) => p.types.filter((t) => t.is_premium).map((t) => t.credits));
        return prices.length ? Math.min(...prices) : null;
    }, [providers]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="space-y-6 p-4">
                {/* Welcome banner */}
                <Card className="overflow-hidden border-0 bg-gradient-to-br from-primary via-primary/90 to-primary/70 text-primary-foreground">
                    <CardContent className="flex flex-wrap items-center justify-between gap-4 p-6">
                        <div>
                            <div className="flex items-center gap-2 text-sm opacity-90">
                                <Sparkles className="size-4" />
                                Bem-vindo de volta
                            </div>
                            <h2 className="mt-1 text-2xl font-bold">
                                Você tem {formatNumber(balance)} crédito{balance === 1 ? '' : 's'} disponível{balance === 1 ? '' : 'eis'}
                            </h2>
                            <p className="mt-1 text-sm opacity-90">
                                {lowestPremium
                                    ? `A partir de ${lowestPremium} créd. por download premium · arquivos ficam ${limits.file_ttl_days} dias na biblioteca`
                                    : 'Cole um link abaixo pra começar'}
                            </p>
                        </div>
                        <Button asChild variant="secondary" size="lg">
                            <Link href={route('billing.index')}>
                                <Coins className="mr-2 size-4" />
                                Comprar mais
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                {/* Stats cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard
                        icon={<Coins className="size-4" />}
                        label="Saldo de créditos"
                        value={formatNumber(balance)}
                        accent="primary"
                    />
                    <StatCard
                        icon={<FileArchive className="size-4" />}
                        label="Arquivos na biblioteca"
                        value={formatNumber(stats.library_count)}
                        accent="violet"
                    />
                    <StatCard
                        icon={<TrendingUp className="size-4" />}
                        label="Downloads neste mês"
                        value={formatNumber(stats.month_downloads)}
                        accent="emerald"
                    />
                    <StatCard
                        icon={<Download className="size-4" />}
                        label="Total de downloads"
                        value={formatNumber(stats.total_downloads)}
                        accent="amber"
                    />
                </div>

                {/* Quick download + recent */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <div className="flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Zap className="size-4" />
                                </div>
                                <div>
                                    <CardTitle>Download rápido</CardTitle>
                                    <CardDescription>
                                        Até {limits.bulk_max_items} links — um por linha
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {successMsg && (
                                <div className="mb-3 flex items-center gap-2 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-700 dark:text-emerald-300">
                                    <CheckCircle2 className="size-4" />
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
                                    <Label htmlFor="quick-links">Links</Label>
                                    <Textarea
                                        id="quick-links"
                                        rows={6}
                                        value={bulkText}
                                        onChange={(e) => setBulkText(e.target.value)}
                                        placeholder={'https://www.shutterstock.com/...\nhttps://www.freepik.com/...'}
                                    />
                                    {errors.links && <p className="text-sm text-destructive">{errors.links}</p>}
                                    <p className="text-xs text-muted-foreground">
                                        {linkList.length} link{linkList.length === 1 ? '' : 's'} detectado{linkList.length === 1 ? '' : 's'}
                                    </p>
                                </div>

                                <div className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="text-sm font-medium">Premium</p>
                                        <p className="text-xs text-muted-foreground">Maioria dos bancos exige</p>
                                    </div>
                                    <Switch checked={isPremium} onCheckedChange={setIsPremium} />
                                </div>

                                <div className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="text-sm font-medium">Empacotar em ZIP</p>
                                        <p className="text-xs text-muted-foreground">Útil em lotes grandes</p>
                                    </div>
                                    <Switch checked={wantZip} onCheckedChange={setWantZip} />
                                </div>

                                <Button type="submit" disabled={processing || !linkList.length} className="w-full">
                                    <Zap className="mr-2 size-4" />
                                    {processing ? 'Enfileirando…' : 'Iniciar download'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Atividade recente</CardTitle>
                                    <CardDescription>Atualiza ao vivo conforme cada item progride</CardDescription>
                                </div>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={route('downloads.index')}>Ver tudo →</Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {items.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-10 text-center">
                                    <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <Download className="size-5" />
                                    </div>
                                    <p className="text-sm font-medium">Nenhum download ainda</p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Cole um link no formulário ao lado para começar
                                    </p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Item</TableHead>
                                            <TableHead>Provedor</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Tamanho</TableHead>
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
                                                <TableCell className="text-xs capitalize">{d.provider_slug || '—'}</TableCell>
                                                <TableCell>
                                                    <Badge variant={STATUS_VARIANTS[d.status] ?? 'secondary'}>
                                                        {STATUS_LABELS[d.status] ?? d.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-xs">{formatBytes(d.file_size_bytes)}</TableCell>
                                                <TableCell>
                                                    {d.status === 'ready' ? (
                                                        <Link
                                                            href={route('library.file', d.public_id)}
                                                            className="text-xs font-medium text-primary hover:underline"
                                                        >
                                                            Baixar
                                                        </Link>
                                                    ) : (
                                                        <Link
                                                            href={route('downloads.show', d.public_id)}
                                                            className="text-xs text-muted-foreground hover:underline"
                                                        >
                                                            Detalhes
                                                        </Link>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Active providers + prices */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Bancos ativos</CardTitle>
                                <CardDescription>
                                    {providers.length} provedor{providers.length === 1 ? '' : 'es'} disponíve{providers.length === 1 ? 'l' : 'is'} · custos em créditos por download
                                </CardDescription>
                            </div>
                            <Badge variant="outline" className="border-emerald-500/40 text-emerald-600 dark:text-emerald-400">
                                <CheckCircle2 className="mr-1 size-3" />
                                Online
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {providers.length === 0 ? (
                            <div className="py-10 text-center text-sm text-muted-foreground">
                                Nenhum provedor sincronizado ainda. Peça pro admin clicar em "Sincronizar agora" no painel administrativo.
                            </div>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                {providers.map((p) => (
                                    <div
                                        key={p.slug}
                                        className="rounded-lg border bg-background p-4 transition-colors hover:border-primary/40"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium capitalize">{p.name}</p>
                                                <p className="truncate text-xs text-muted-foreground">{p.host}</p>
                                            </div>
                                            <span className="ml-2 inline-flex size-2 shrink-0 rounded-full bg-emerald-500" />
                                        </div>
                                        <div className="mt-3 space-y-1 text-xs">
                                            {p.types.map((t) => (
                                                <div
                                                    key={t.type}
                                                    className={`flex items-center justify-between rounded-md px-2 py-1.5 ${
                                                        t.is_premium ? 'bg-primary/10 text-primary' : 'bg-muted/60'
                                                    }`}
                                                >
                                                    <span>
                                                        {t.kind_label}
                                                        {t.is_premium && <span className="ml-1 text-[9px] opacity-70">PRO</span>}
                                                    </span>
                                                    <span className="font-semibold">{t.credits} créd.</span>
                                                </div>
                                            ))}
                                            {p.types.length === 0 && (
                                                <p className="text-muted-foreground">Nenhum tipo habilitado.</p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function StatCard({
    icon,
    label,
    value,
    accent,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    accent: 'primary' | 'violet' | 'emerald' | 'amber';
}) {
    const tones: Record<string, string> = {
        primary: 'bg-primary/10 text-primary',
        violet: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        emerald: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        amber: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    };
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <p className="text-xs font-medium text-muted-foreground">{label}</p>
                    <div className={`flex size-8 items-center justify-center rounded-md ${tones[accent]}`}>{icon}</div>
                </div>
                <p className="mt-3 text-2xl font-bold">{value}</p>
            </CardContent>
        </Card>
    );
}

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatBRL, formatDate, formatNumber, STATUS_LABELS, STATUS_VARIANTS } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }];

type Metrics = {
    users_total: number;
    users_active_7d: number;
    downloads_today: number;
    downloads_month: number;
    downloads_failed_today: number;
    revenue_month_cents: number;
    credits_spent_today: number;
};

type Upstream = {
    balance: { email?: string; bValue?: string; bBonus?: string; isSubscription?: number } | null;
    error: string | null;
};

type ProviderRow = { provider_slug: string; total: number };

type Download = {
    public_id: string;
    item_name: string | null;
    provider_slug: string | null;
    status: string;
    created_at: string;
    user?: { name: string; email: string } | null;
};

type Props = {
    metrics: Metrics;
    upstream: Upstream;
    topProviders: ProviderRow[];
    recentDownloads: Download[];
};

export default function AdminDashboard({ metrics, upstream, topProviders, recentDownloads }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin · Visão geral" />
            <div className="grid gap-4 p-4">
                <div className="grid gap-3 grid-cols-2 sm:gap-4 lg:grid-cols-4">
                    <Stat title="Saldo GetStocks" value={upstream.balance?.bValue ?? '—'} subtitle={upstream.error ?? 'Atualizado agora'} />
                    <Stat title="Downloads hoje" value={formatNumber(metrics.downloads_today)} subtitle={`${metrics.downloads_failed_today} falhou(aram)`} />
                    <Stat title="Receita do mês" value={formatBRL(metrics.revenue_month_cents)} subtitle="Pagamentos aprovados" />
                    <Stat title="Usuários ativos (7d)" value={formatNumber(metrics.users_active_7d)} subtitle={`${formatNumber(metrics.users_total)} no total`} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Downloads recentes</CardTitle>
                            <CardDescription>Últimos 15 itens enfileirados por usuários.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Usuário</TableHead>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Quando</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentDownloads.map((d) => (
                                        <TableRow key={d.public_id}>
                                            <TableCell className="text-xs">{d.user?.name ?? '—'}</TableCell>
                                            <TableCell className="max-w-xs truncate text-xs">{d.item_name ?? '—'}</TableCell>
                                            <TableCell className="text-xs">{d.provider_slug ?? '—'}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANTS[d.status] ?? 'secondary'}>
                                                    {STATUS_LABELS[d.status] ?? d.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">{formatDate(d.created_at)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Top providers (30d)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2 text-sm">
                                {topProviders.length === 0 && <li className="text-muted-foreground">Sem dados ainda.</li>}
                                {topProviders.map((p) => (
                                    <li key={p.provider_slug} className="flex items-center justify-between">
                                        <span className="capitalize">{p.provider_slug}</span>
                                        <span className="font-medium">{formatNumber(p.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Stat({ title, value, subtitle }: { title: string; value: string; subtitle?: string }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
            </CardHeader>
            {subtitle && (
                <CardContent>
                    <p className="text-xs text-muted-foreground">{subtitle}</p>
                </CardContent>
            )}
        </Card>
    );
}

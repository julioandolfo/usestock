import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatNumber } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Providers', href: '/admin/providers' },
];

type OverrideRule = {
    id: number;
    strategy: 'fixed' | 'multiplier';
    value: string | number;
    min_credits: number;
};

type Provider = {
    id: number;
    slug: string;
    name: string;
    type: string;
    host: string | null;
    logo: string | null;
    resolution: string | null;
    license: string | null;
    upstream_price: string;
    upstream_price_bonus: string;
    is_premium: boolean;
    enabled: boolean;
    synced_at: string | null;
    effective_credits: number;
    override_rule: OverrideRule | null;
};

type Props = {
    providers: {
        data: Provider[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q?: string; premium?: boolean };
    lastSyncAt: string | null;
};

export default function ProvidersIndex({ providers, lastSyncAt, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');

    const search: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.providers.index'), { q }, { preserveState: true });
    };

    const sync = () => {
        if (confirm('Sincronizar agora com o GetStocks?')) {
            router.post(route('admin.providers.sync'));
        }
    };

    const toggle = (provider: Provider) => {
        router.patch(
            route('admin.providers.update', provider.id),
            { enabled: !provider.enabled },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Providers" />
            <div className="p-4">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <CardTitle>Providers ({formatNumber(providers.data.length)})</CardTitle>
                                <CardDescription>Última sincronização: {formatDate(lastSyncAt)}</CardDescription>
                            </div>
                            <div className="flex gap-2">
                                <form onSubmit={search} className="flex gap-2">
                                    <Input
                                        type="search"
                                        placeholder="Buscar…"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        className="w-64"
                                    />
                                    <Button type="submit" variant="outline">
                                        Buscar
                                    </Button>
                                </form>
                                <Button onClick={sync}>Sincronizar agora</Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Provider</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Resolução</TableHead>
                                    <TableHead>Custo upstream</TableHead>
                                    <TableHead>Premium?</TableHead>
                                    <TableHead className="w-48">Custo em créditos</TableHead>
                                    <TableHead>Ativo</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {providers.data.map((p) => (
                                    <ProviderRow key={p.id} provider={p} onToggle={() => toggle(p)} />
                                ))}
                            </TableBody>
                        </Table>

                        <div className="mt-4 flex flex-wrap gap-1">
                            {providers.links.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.url ?? '#'}
                                    className={`rounded-md border px-3 py-1 text-xs ${
                                        link.active ? 'bg-primary text-primary-foreground' : ''
                                    } ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function ProviderRow({ provider, onToggle }: { provider: Provider; onToggle: () => void }) {
    const [credits, setCredits] = useState<string>(String(provider.effective_credits));
    const [saving, setSaving] = useState(false);

    const hasOverride = provider.override_rule !== null;
    const dirty = String(provider.effective_credits) !== credits;

    const save = () => {
        const value = parseInt(credits, 10);
        if (!Number.isFinite(value) || value < 1) return;
        setSaving(true);
        router.post(
            route('admin.providers.price', provider.id),
            { credits: value },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const removeOverride = () => {
        setSaving(true);
        router.post(
            route('admin.providers.price', provider.id),
            { credits: null },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <TableRow>
            <TableCell>
                <p className="font-medium">{provider.name}</p>
                <p className="text-xs text-muted-foreground">{provider.host}</p>
            </TableCell>
            <TableCell className="text-xs font-mono">{provider.type}</TableCell>
            <TableCell className="text-xs">{provider.resolution || '—'}</TableCell>
            <TableCell className="text-xs">
                {provider.upstream_price}{' '}
                <span className="text-muted-foreground">(bonus: {provider.upstream_price_bonus})</span>
            </TableCell>
            <TableCell>
                {provider.is_premium ? <Badge>Premium</Badge> : <Badge variant="outline">Normal</Badge>}
            </TableCell>
            <TableCell>
                <div className="flex items-center gap-1">
                    <Input
                        type="number"
                        min={1}
                        value={credits}
                        onChange={(e) => setCredits(e.target.value)}
                        className="h-8 w-20 text-xs"
                    />
                    {dirty && (
                        <Button size="sm" variant="default" onClick={save} disabled={saving}>
                            Salvar
                        </Button>
                    )}
                    {hasOverride && !dirty && (
                        <Button size="sm" variant="ghost" onClick={removeOverride} disabled={saving} title="Voltar à regra global">
                            ↺
                        </Button>
                    )}
                </div>
                <p className="mt-1 text-[10px] text-muted-foreground">
                    {hasOverride ? 'override personalizado' : 'regra global'}
                </p>
            </TableCell>
            <TableCell>
                <Switch checked={provider.enabled} onCheckedChange={onToggle} />
            </TableCell>
        </TableRow>
    );
}

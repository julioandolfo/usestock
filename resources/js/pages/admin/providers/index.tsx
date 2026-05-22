import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatNumber } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, RefreshCw } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Bancos / Provedores', href: '/admin/providers' },
];

type OverrideRule = {
    id: number;
    strategy: 'fixed' | 'multiplier';
    value: number;
    min_credits: number;
};

type Row = {
    id: number;
    slug: string;
    name: string;
    type: string;
    kind: string;
    kind_label: string;
    resolution: string | null;
    license: string | null;
    upstream_price: string;
    upstream_price_bonus: string;
    is_premium: boolean;
    enabled: boolean;
    effective_credits: number;
    override_rule: OverrideRule | null;
};

type Group = {
    slug: string;
    name: string;
    host: string | null;
    logo: string | null;
    total_rows: number;
    enabled_rows: number;
    rows: Row[];
};

type Props = {
    groups: Group[];
    filters: { q?: string };
    lastSyncAt: string | null;
    totalProviders: number;
};

export default function ProvidersIndex({ groups, filters, lastSyncAt, totalProviders }: Props) {
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bancos / Provedores" />
            <div className="p-4">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <CardTitle>Bancos suportados ({formatNumber(groups.length)})</CardTitle>
                                <CardDescription>
                                    {formatNumber(totalProviders)} tipos de conteúdo no total · Última sincronização: {formatDate(lastSyncAt)}
                                </CardDescription>
                            </div>
                            <div className="flex gap-2">
                                <form onSubmit={search} className="flex gap-2">
                                    <Input
                                        type="search"
                                        placeholder="Buscar banco ou tipo…"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        className="w-64"
                                    />
                                    <Button type="submit" variant="outline">
                                        Buscar
                                    </Button>
                                </form>
                                <Button onClick={sync} variant="default">
                                    <RefreshCw className="mr-2 size-4" />
                                    Sincronizar agora
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <div className="mt-4 space-y-3">
                    {groups.map((g) => (
                        <ProviderGroup key={g.slug} group={g} />
                    ))}
                    {groups.length === 0 && (
                        <Card>
                            <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                Nenhum banco encontrado. Clique em "Sincronizar agora" para puxar a lista do GetStocks.
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function ProviderGroup({ group }: { group: Group }) {
    const [open, setOpen] = useState(group.enabled_rows > 0);

    const enabledAll = group.enabled_rows === group.total_rows;
    const enabledNone = group.enabled_rows === 0;

    const bulkToggle = (enabled: boolean) => {
        router.post(
            route('admin.providers.bulk', group.slug),
            { enabled },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <Card>
            <CardHeader className="cursor-pointer" onClick={() => setOpen((o) => !o)}>
                <div className="flex flex-wrap items-center gap-3">
                    <button type="button" className="text-muted-foreground" aria-label="Expandir">
                        {open ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                    </button>
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                            <CardTitle className="capitalize">{group.name}</CardTitle>
                            <Badge variant="outline" className="text-[10px]">
                                {group.enabled_rows}/{group.total_rows} ativo{group.total_rows === 1 ? '' : 's'}
                            </Badge>
                        </div>
                        <CardDescription>{group.host ?? group.slug}</CardDescription>
                    </div>
                    <div
                        className="flex gap-2"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={enabledAll}
                            onClick={() => bulkToggle(true)}
                        >
                            Habilitar todos
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={enabledNone}
                            onClick={() => bulkToggle(false)}
                        >
                            Desabilitar todos
                        </Button>
                    </div>
                </div>
            </CardHeader>
            {open && (
                <CardContent>
                    <div className="grid gap-2">
                        {group.rows.map((r) => (
                            <ProviderRow key={r.id} row={r} />
                        ))}
                    </div>
                </CardContent>
            )}
        </Card>
    );
}

function ProviderRow({ row }: { row: Row }) {
    const [credits, setCredits] = useState<string>(String(row.effective_credits));
    const [saving, setSaving] = useState(false);

    const hasOverride = row.override_rule !== null;
    const dirty = String(row.effective_credits) !== credits;

    const save = () => {
        const value = parseInt(credits, 10);
        if (!Number.isFinite(value) || value < 1) return;
        setSaving(true);
        router.post(
            route('admin.providers.price', row.id),
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
            route('admin.providers.price', row.id),
            { credits: null },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const toggle = () => {
        router.patch(
            route('admin.providers.update', row.id),
            { enabled: !row.enabled },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div
            className={`flex flex-wrap items-center gap-4 rounded-md border p-3 transition-colors ${
                row.enabled ? 'bg-background' : 'bg-muted/30 opacity-70'
            }`}
        >
            <div className="min-w-32 flex-1">
                <div className="flex items-center gap-2">
                    <span className="font-medium">{row.kind_label}</span>
                    {row.is_premium ? (
                        <Badge className="text-[10px]">Premium</Badge>
                    ) : (
                        <Badge variant="outline" className="text-[10px]">
                            Normal
                        </Badge>
                    )}
                </div>
                <p className="font-mono text-[11px] text-muted-foreground">{row.type}</p>
            </div>

            <div className="text-xs text-muted-foreground">
                <p>Resolução: {row.resolution || '—'}</p>
                <p>
                    Upstream: {row.upstream_price} (bônus: {row.upstream_price_bonus})
                </p>
            </div>

            <div className="flex items-center gap-2">
                <div>
                    <p className="mb-1 text-[10px] text-muted-foreground">Custo em créditos</p>
                    <div className="flex items-center gap-1">
                        <Input
                            type="number"
                            min={1}
                            value={credits}
                            onChange={(e) => setCredits(e.target.value)}
                            className="h-8 w-20 text-xs"
                        />
                        {dirty && (
                            <Button size="sm" onClick={save} disabled={saving}>
                                Salvar
                            </Button>
                        )}
                        {hasOverride && !dirty && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={removeOverride}
                                disabled={saving}
                                title="Voltar à regra global"
                            >
                                ↺
                            </Button>
                        )}
                    </div>
                    <p className="mt-0.5 text-[10px] text-muted-foreground">
                        {hasOverride ? 'override personalizado' : 'regra global'}
                    </p>
                </div>
            </div>

            <div className="flex flex-col items-end gap-1">
                <span className="text-[10px] text-muted-foreground">
                    {row.enabled ? 'Liberado para usuários' : 'Bloqueado'}
                </span>
                <Switch checked={row.enabled} onCheckedChange={toggle} />
            </div>
        </div>
    );
}

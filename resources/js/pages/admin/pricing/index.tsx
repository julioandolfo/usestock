import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Regras de preço', href: '/admin/pricing' },
];

type Provider = { id: number; name: string; slug: string };
type Rule = {
    id: number;
    provider_id: number | null;
    strategy: 'fixed' | 'multiplier';
    value: string;
    min_credits: number;
    active: boolean;
    provider: Provider | null;
};

type Props = { rules: Rule[]; providers: Provider[] };

export default function PricingIndex({ rules, providers }: Props) {
    const form = useForm({
        provider_id: '',
        strategy: 'multiplier' as 'fixed' | 'multiplier',
        value: 2.0,
        min_credits: 1,
        active: true as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.pricing.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const remove = (rule: Rule) => {
        if (confirm('Remover esta regra?')) {
            router.delete(route('admin.pricing.destroy', rule.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Regras de preço" />
            <div className="grid gap-4 p-4 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Nova regra</CardTitle>
                        <CardDescription>
                            <strong>multiplier</strong>: créditos = upstream_price × valor (arred. cima).
                            <br />
                            <strong>fixed</strong>: créditos = valor fixo, independente do upstream.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label>Provider (vazio = regra global)</Label>
                                <select
                                    value={form.data.provider_id}
                                    onChange={(e) => form.setData('provider_id', e.target.value)}
                                    className="w-full rounded-md border bg-background p-2 text-sm"
                                >
                                    <option value="">Todos (padrão)</option>
                                    {providers.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>Estratégia</Label>
                                <select
                                    value={form.data.strategy}
                                    onChange={(e) => form.setData('strategy', e.target.value as 'fixed' | 'multiplier')}
                                    className="w-full rounded-md border bg-background p-2 text-sm"
                                >
                                    <option value="multiplier">multiplier</option>
                                    <option value="fixed">fixed</option>
                                </select>
                            </div>
                            <div>
                                <Label>Valor</Label>
                                <Input
                                    type="number"
                                    step="0.0001"
                                    value={form.data.value}
                                    onChange={(e) => form.setData('value', parseFloat(e.target.value))}
                                />
                                <InputError message={form.errors.value} />
                            </div>
                            <div>
                                <Label>Mínimo de créditos</Label>
                                <Input
                                    type="number"
                                    value={form.data.min_credits}
                                    onChange={(e) => form.setData('min_credits', parseInt(e.target.value || '1', 10))}
                                />
                            </div>
                            <div className="flex items-center justify-between rounded-md border p-3">
                                <Label>Ativa</Label>
                                <Switch checked={form.data.active} onCheckedChange={(v) => form.setData('active', v)} />
                            </div>
                            <Button type="submit" disabled={form.processing} className="w-full">
                                Criar regra
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Regras existentes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Provider</TableHead>
                                    <TableHead>Estratégia</TableHead>
                                    <TableHead>Valor</TableHead>
                                    <TableHead>Mín.</TableHead>
                                    <TableHead>Ativa</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rules.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>{r.provider ? r.provider.name : <Badge variant="outline">Global</Badge>}</TableCell>
                                        <TableCell>{r.strategy}</TableCell>
                                        <TableCell>{r.value}</TableCell>
                                        <TableCell>{r.min_credits}</TableCell>
                                        <TableCell>
                                            <Switch
                                                checked={r.active}
                                                onCheckedChange={(v) =>
                                                    router.patch(
                                                        route('admin.pricing.update', r.id),
                                                        { active: v },
                                                        { preserveScroll: true, preserveState: true },
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="sm" onClick={() => remove(r)}>
                                                Remover
                                            </Button>
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

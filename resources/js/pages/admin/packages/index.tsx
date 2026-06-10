import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatBRL } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Pacotes', href: '/admin/packages' },
];

type Package = {
    id: number;
    name: string;
    description: string | null;
    credits: number;
    bonus_credits: number;
    price_cents: number;
    currency: string;
    featured: boolean;
    active: boolean;
    sort_order: number;
};

export default function PackagesIndex({ packages }: { packages: Package[] }) {
    const form = useForm({
        name: '',
        description: '',
        credits: 50,
        bonus_credits: 0,
        price_cents: 2500,
        currency: 'BRL',
        featured: false as boolean,
        active: true as boolean,
        sort_order: 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.packages.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const remove = (pkg: Package) => {
        if (confirm(`Remover pacote "${pkg.name}"?`)) {
            router.delete(route('admin.packages.destroy', pkg.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pacotes" />
            <div className="grid gap-3 p-3 sm:gap-4 sm:p-4 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Novo pacote</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label>Nome</Label>
                                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <InputError message={form.errors.name} />
                            </div>
                            <div>
                                <Label>Descrição</Label>
                                <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label>Créditos</Label>
                                    <Input
                                        type="number"
                                        value={form.data.credits}
                                        onChange={(e) => form.setData('credits', parseInt(e.target.value || '0', 10))}
                                    />
                                </div>
                                <div>
                                    <Label>Bônus</Label>
                                    <Input
                                        type="number"
                                        value={form.data.bonus_credits}
                                        onChange={(e) => form.setData('bonus_credits', parseInt(e.target.value || '0', 10))}
                                    />
                                </div>
                                <div>
                                    <Label>Preço (centavos)</Label>
                                    <Input
                                        type="number"
                                        value={form.data.price_cents}
                                        onChange={(e) => form.setData('price_cents', parseInt(e.target.value || '0', 10))}
                                    />
                                </div>
                                <div>
                                    <Label>Ordem</Label>
                                    <Input
                                        type="number"
                                        value={form.data.sort_order}
                                        onChange={(e) => form.setData('sort_order', parseInt(e.target.value || '0', 10))}
                                    />
                                </div>
                            </div>
                            <div className="flex items-center justify-between rounded-md border p-3">
                                <Label>Destaque</Label>
                                <Switch checked={form.data.featured} onCheckedChange={(v) => form.setData('featured', v)} />
                            </div>
                            <div className="flex items-center justify-between rounded-md border p-3">
                                <Label>Ativo</Label>
                                <Switch checked={form.data.active} onCheckedChange={(v) => form.setData('active', v)} />
                            </div>
                            <Button type="submit" disabled={form.processing} className="w-full">
                                Criar
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Pacotes existentes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nome</TableHead>
                                    <TableHead>Créditos</TableHead>
                                    <TableHead>Preço</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {packages.map((p) => (
                                    <TableRow key={p.id}>
                                        <TableCell>
                                            <p className="font-medium">{p.name}</p>
                                            <p className="text-xs text-muted-foreground">{p.description}</p>
                                        </TableCell>
                                        <TableCell>
                                            {p.credits}
                                            {p.bonus_credits > 0 && (
                                                <span className="text-xs text-muted-foreground"> +{p.bonus_credits}</span>
                                            )}
                                        </TableCell>
                                        <TableCell>{formatBRL(p.price_cents)}</TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                {p.featured && <Badge>Destaque</Badge>}
                                                <Badge variant={p.active ? 'default' : 'outline'}>
                                                    {p.active ? 'ativo' : 'inativo'}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="sm" onClick={() => remove(p)}>
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

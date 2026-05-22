import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatNumber } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Role = { id: number; name: string };
type User = {
    id: number;
    name: string;
    email: string;
    credits_balance: number;
    downloads_count: number;
    banned_at: string | null;
    banned_reason: string | null;
    last_seen_at: string | null;
    created_at: string;
    roles: Role[];
};
type Tx = {
    id: number;
    type: string;
    amount: number;
    balance_after: number;
    description: string | null;
    created_at: string;
};
type Download = {
    public_id: string;
    item_name: string | null;
    provider_slug: string | null;
    status: string;
    created_at: string;
};

type Props = { user: User; transactions: Tx[]; downloads: Download[] };

export default function UserShow({ user, transactions, downloads }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Usuários', href: '/admin/users' },
        { title: user.name, href: '#' },
    ];

    const credits = useForm({ amount: 0, description: '' });
    const ban = useForm({ reason: '' });
    const edit = useForm({ name: user.name, email: user.email, password: '' });

    const isAdmin = user.roles.some((r) => r.name === 'admin');

    const submitCredits: FormEventHandler = (e) => {
        e.preventDefault();
        credits.post(route('admin.users.credits', user.id), { preserveScroll: true });
    };

    const submitEdit: FormEventHandler = (e) => {
        e.preventDefault();
        edit.transform((d) => {
            // Don't send empty password — keeps the existing one.
            const payload: Record<string, string> = { name: d.name, email: d.email };
            if (d.password) payload.password = d.password;
            return payload;
        });
        edit.patch(route('admin.users.update', user.id), {
            preserveScroll: true,
            onSuccess: () => edit.setData('password', ''),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={user.name} />
            <div className="grid gap-3 p-3 sm:gap-4 sm:p-4 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>{user.name}</CardTitle>
                        <CardDescription>{user.email}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="grid grid-cols-2 gap-3">
                            <Detail label="Créditos" value={formatNumber(user.credits_balance)} />
                            <Detail label="Downloads" value={formatNumber(user.downloads_count)} />
                            <Detail label="Cadastro" value={formatDate(user.created_at)} />
                            <Detail label="Último acesso" value={formatDate(user.last_seen_at)} />
                        </div>

                        <div className="flex gap-2">
                            {user.banned_at ? (
                                <Badge variant="destructive">Banido — {user.banned_reason ?? 'sem motivo'}</Badge>
                            ) : (
                                <Badge variant="default">Ativo</Badge>
                            )}
                            {isAdmin && <Badge variant="outline">Admin</Badge>}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    if (user.banned_at) {
                                        ban.post(route('admin.users.unban', user.id), { preserveScroll: true });
                                    } else {
                                        ban.post(route('admin.users.ban', user.id), { preserveScroll: true });
                                    }
                                }}
                            >
                                <Button type="submit" variant={user.banned_at ? 'default' : 'destructive'} size="sm">
                                    {user.banned_at ? 'Reativar' : 'Banir'}
                                </Button>
                            </form>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    ban.post(route('admin.users.toggle-admin', user.id), { preserveScroll: true });
                                }}
                            >
                                <Button type="submit" variant="outline" size="sm">
                                    {isAdmin ? 'Remover admin' : 'Tornar admin'}
                                </Button>
                            </form>
                        </div>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Editar dados</CardTitle>
                        <CardDescription>
                            Atualize nome, e-mail ou redefina a senha. Deixe a senha em branco para manter a atual.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitEdit} className="grid gap-3 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="edit-name">Nome</Label>
                                <Input
                                    id="edit-name"
                                    value={edit.data.name}
                                    onChange={(e) => edit.setData('name', e.target.value)}
                                />
                                <InputError message={edit.errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-email">E-mail</Label>
                                <Input
                                    id="edit-email"
                                    type="email"
                                    value={edit.data.email}
                                    onChange={(e) => edit.setData('email', e.target.value)}
                                />
                                <InputError message={edit.errors.email} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-password">Nova senha (opcional)</Label>
                                <Input
                                    id="edit-password"
                                    type="text"
                                    value={edit.data.password}
                                    onChange={(e) => edit.setData('password', e.target.value)}
                                    placeholder="Mínimo 8 caracteres"
                                />
                                <InputError message={edit.errors.password} />
                            </div>
                            <Button type="submit" disabled={edit.processing} className="md:col-span-3">
                                {edit.processing ? 'Salvando…' : 'Salvar alterações'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>Ajustar créditos</CardTitle>
                        <CardDescription>Use valores positivos para creditar e negativos para debitar.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitCredits} className="grid gap-3 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="amount">Quantidade</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    value={credits.data.amount}
                                    onChange={(e) => credits.setData('amount', parseInt(e.target.value || '0', 10))}
                                />
                                <InputError message={credits.errors.amount} />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="description">Motivo</Label>
                                <Input
                                    id="description"
                                    value={credits.data.description}
                                    onChange={(e) => credits.setData('description', e.target.value)}
                                />
                            </div>
                            <Button type="submit" disabled={credits.processing} className="md:col-span-3">
                                Aplicar
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>Transações recentes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Valor</TableHead>
                                    <TableHead>Saldo após</TableHead>
                                    <TableHead>Descrição</TableHead>
                                    <TableHead>Quando</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.map((tx) => (
                                    <TableRow key={tx.id}>
                                        <TableCell className="text-xs">{tx.type}</TableCell>
                                        <TableCell className={tx.amount < 0 ? 'text-destructive' : 'text-green-600'}>
                                            {tx.amount > 0 ? '+' : ''}
                                            {tx.amount}
                                        </TableCell>
                                        <TableCell>{tx.balance_after}</TableCell>
                                        <TableCell className="max-w-xs truncate text-xs">{tx.description ?? '—'}</TableCell>
                                        <TableCell className="text-xs text-muted-foreground">{formatDate(tx.created_at)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>Downloads recentes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Provider</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Quando</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {downloads.map((d) => (
                                    <TableRow key={d.public_id}>
                                        <TableCell className="max-w-xs truncate text-xs">{d.item_name ?? '—'}</TableCell>
                                        <TableCell className="text-xs">{d.provider_slug ?? '—'}</TableCell>
                                        <TableCell className="text-xs">{d.status}</TableCell>
                                        <TableCell className="text-xs text-muted-foreground">{formatDate(d.created_at)}</TableCell>
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

function Detail({ label, value }: { label: string; value: string | number }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-medium">{value}</p>
        </div>
    );
}

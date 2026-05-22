import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatNumber } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Usuários', href: '/admin/users' },
];

type Role = { id: number; name: string };
type User = {
    id: number;
    name: string;
    email: string;
    credits_balance: number;
    downloads_count: number;
    banned_at: string | null;
    last_seen_at: string | null;
    created_at: string;
    roles: Role[];
};
type Paginator<T> = { data: T[]; links: { url: string | null; label: string; active: boolean }[] };

type Props = {
    users: Paginator<User>;
    filters: { q?: string; banned?: boolean };
};

export default function UsersIndex({ users, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [createOpen, setCreateOpen] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.users.index'), { q, banned: filters.banned ? 1 : undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuários" />
            <div className="p-3 sm:p-4">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <CardTitle>Usuários ({formatNumber(users.data.length)})</CardTitle>
                            <div className="flex flex-wrap items-center gap-2">
                                <form onSubmit={submit} className="flex gap-2">
                                    <Input
                                        type="search"
                                        placeholder="Buscar por nome/email…"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        className="w-48 sm:w-64"
                                    />
                                    <Button type="submit" variant="outline" size="sm">
                                        Buscar
                                    </Button>
                                </form>
                                <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <Plus className="mr-2 size-4" />
                                            Novo usuário
                                        </Button>
                                    </DialogTrigger>
                                    <CreateUserDialog onSuccess={() => setCreateOpen(false)} />
                                </Dialog>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nome</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Créditos</TableHead>
                                    <TableHead>Downloads</TableHead>
                                    <TableHead>Papéis</TableHead>
                                    <TableHead>Último acesso</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((u) => (
                                    <TableRow key={u.id}>
                                        <TableCell className="font-medium">
                                            {u.name}
                                            {u.banned_at && (
                                                <Badge variant="destructive" className="ml-2">
                                                    banido
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">{u.email}</TableCell>
                                        <TableCell>{formatNumber(u.credits_balance)}</TableCell>
                                        <TableCell>{formatNumber(u.downloads_count)}</TableCell>
                                        <TableCell className="text-xs">
                                            {u.roles.map((r) => r.name).join(', ') || '—'}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">{formatDate(u.last_seen_at)}</TableCell>
                                        <TableCell>
                                            <Link
                                                href={route('admin.users.show', u.id)}
                                                className="text-xs text-primary hover:underline"
                                            >
                                                Detalhes
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="mt-4 flex flex-wrap gap-1">
                            {users.links.map((link) => (
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

function CreateUserDialog({ onSuccess }: { onSuccess: () => void }) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        role: 'user' as 'user' | 'admin',
        initial_credits: 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSuccess();
            },
        });
    };

    const generatePassword = () => {
        const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        let out = '';
        for (let i = 0; i < 12; i++) {
            out += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        form.setData('password', out);
    };

    return (
        <DialogContent className="max-w-md">
            <DialogHeader>
                <DialogTitle>Criar novo usuário</DialogTitle>
                <DialogDescription>
                    A conta é criada já verificada e ativa. A senha pode ser compartilhada com o usuário para o primeiro login.
                </DialogDescription>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-3">
                <div>
                    <Label htmlFor="cu-name">Nome</Label>
                    <Input
                        id="cu-name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div>
                    <Label htmlFor="cu-email">E-mail</Label>
                    <Input
                        id="cu-email"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        required
                    />
                    <InputError message={form.errors.email} />
                </div>

                <div>
                    <div className="mb-1 flex items-center justify-between">
                        <Label htmlFor="cu-password">Senha</Label>
                        <button
                            type="button"
                            onClick={generatePassword}
                            className="text-xs text-primary hover:underline"
                        >
                            Gerar
                        </button>
                    </div>
                    <Input
                        id="cu-password"
                        type="text"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        placeholder="Mínimo 8 caracteres"
                        required
                    />
                    <InputError message={form.errors.password} />
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <Label htmlFor="cu-role">Papel</Label>
                        <select
                            id="cu-role"
                            value={form.data.role}
                            onChange={(e) => form.setData('role', e.target.value as 'user' | 'admin')}
                            className="h-10 w-full rounded-md border bg-background px-2 text-sm"
                        >
                            <option value="user">Usuário</option>
                            <option value="admin">Administrador</option>
                        </select>
                        <InputError message={form.errors.role} />
                    </div>
                    <div>
                        <Label htmlFor="cu-credits">Créditos iniciais</Label>
                        <Input
                            id="cu-credits"
                            type="number"
                            min={0}
                            value={form.data.initial_credits}
                            onChange={(e) => form.setData('initial_credits', parseInt(e.target.value || '0', 10))}
                        />
                        <InputError message={form.errors.initial_credits} />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit" disabled={form.processing} className="w-full">
                        {form.processing ? 'Criando…' : 'Criar usuário'}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
}

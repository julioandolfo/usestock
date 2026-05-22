import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatNumber } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
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

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.users.index'), { q, banned: filters.banned ? 1 : undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuários" />
            <div className="p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-3">
                            <CardTitle>Usuários ({formatNumber(users.data.length)})</CardTitle>
                            <form onSubmit={submit} className="flex gap-2">
                                <Input
                                    type="search"
                                    placeholder="Buscar por nome/email…"
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    className="w-64"
                                />
                                <Button type="submit" variant="outline">
                                    Buscar
                                </Button>
                            </form>
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

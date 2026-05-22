import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Auditoria', href: '/admin/audit' },
];

type Log = {
    id: number;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    description: string | null;
    ip_address: string | null;
    created_at: string;
    user: { name: string; email: string } | null;
};

type Props = { logs: { data: Log[]; links: { url: string | null; label: string; active: boolean }[] }; filters: { action?: string } };

export default function AuditIndex({ logs }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Auditoria" />
            <div className="p-3 sm:p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Auditoria</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Quando</TableHead>
                                    <TableHead>Ator</TableHead>
                                    <TableHead>Ação</TableHead>
                                    <TableHead>Sujeito</TableHead>
                                    <TableHead>IP</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.map((l) => (
                                    <TableRow key={l.id}>
                                        <TableCell className="text-xs text-muted-foreground">{formatDate(l.created_at)}</TableCell>
                                        <TableCell className="text-xs">{l.user?.email ?? 'sistema'}</TableCell>
                                        <TableCell className="text-xs font-mono">{l.action}</TableCell>
                                        <TableCell className="text-xs">
                                            {l.subject_type ? `${l.subject_type.split('\\').pop()}#${l.subject_id}` : '—'}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">{l.ip_address ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="mt-4 flex flex-wrap gap-1">
                            {logs.links.map((link) => (
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

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Downloads', href: '/downloads' },
];

type Download = {
    id: number;
    public_id: string;
    source_url: string;
    item_name: string | null;
    provider_slug: string | null;
    status: string;
    file_name: string | null;
    file_size_bytes: number | null;
    created_at: string;
};

type PageProps = {
    downloads: {
        data: Download[];
    };
};

export default function DownloadsIndex({ downloads }: PageProps) {
    const [bulkText, setBulkText] = useState('');
    const { data, setData, post, processing, errors, reset } = useForm({
        links: [] as string[],
        is_premium: true,
        zip: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const links = bulkText
            .split(/\r?\n/)
            .map((l) => l.trim())
            .filter(Boolean);
        if (!links.length) return;
        setData('links', links);
        post(route('downloads.store'), {
            onSuccess: () => {
                setBulkText('');
                reset('links');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Downloads" />
            <div className="grid gap-6 p-4 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Novo download</CardTitle>
                        <CardDescription>
                            Cole um ou mais links (um por linha) e enfileire o download. Os créditos são reservados na hora.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="links">Links</Label>
                                <textarea
                                    id="links"
                                    className="min-h-[160px] w-full rounded-md border bg-background p-2 text-sm font-mono"
                                    placeholder={'https://www.shutterstock.com/...\nhttps://www.freepik.com/...'}
                                    value={bulkText}
                                    onChange={(e) => setBulkText(e.target.value)}
                                />
                                {errors.links && (
                                    <p className="text-sm text-destructive">{errors.links}</p>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.is_premium}
                                    onChange={(e) => setData('is_premium', e.target.checked)}
                                />
                                Conteúdo premium
                            </label>

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Enfileirando…' : 'Iniciar download'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Histórico recente</CardTitle>
                        <CardDescription>Atualizações em tempo real conforme cada item é processado.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {downloads.data.length === 0 && (
                            <p className="text-sm text-muted-foreground">Nenhum download ainda.</p>
                        )}
                        {downloads.data.map((d) => (
                            <div key={d.id} className="flex items-center justify-between rounded-lg border p-3 text-sm">
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{d.item_name || d.source_url}</p>
                                    <p className="truncate text-xs text-muted-foreground">{d.provider_slug || '—'}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant={d.status === 'ready' ? 'default' : 'secondary'}>{d.status}</Badge>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

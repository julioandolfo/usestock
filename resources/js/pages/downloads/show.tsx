import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type Download = {
    public_id: string;
    source_url: string;
    item_name: string | null;
    provider_slug: string | null;
    status: string;
    file_name: string | null;
    failure_reason: string | null;
};

export default function DownloadShow({ download }: { download: Download }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Downloads', href: '/downloads' },
        { title: download.public_id.slice(0, 8), href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Download ${download.public_id}`} />
            <div className="p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>{download.item_name || download.source_url}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-muted-foreground">Status</dt>
                                <dd className="font-medium">{download.status}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Provider</dt>
                                <dd className="font-medium">{download.provider_slug || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Arquivo</dt>
                                <dd className="font-medium">{download.file_name || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Falha</dt>
                                <dd className="font-medium">{download.failure_reason || '—'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

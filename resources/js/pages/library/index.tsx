import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Biblioteca', href: '/library' }];

type LibraryItem = {
    public_id: string;
    item_name: string | null;
    file_name: string | null;
    provider_slug: string | null;
    upstream_thumb_url: string | null;
    expires_at: string | null;
    served_count: number;
};

export default function LibraryIndex({ items }: { items: { data: LibraryItem[] } }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Biblioteca" />
            <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                {items.data.length === 0 && (
                    <p className="col-span-full text-sm text-muted-foreground">
                        Nenhum arquivo disponível ainda.
                    </p>
                )}
                {items.data.map((item) => (
                    <Card key={item.public_id} className="overflow-hidden">
                        <div className="aspect-video bg-muted">
                            {item.upstream_thumb_url && (
                                <img
                                    src={item.upstream_thumb_url}
                                    alt={item.item_name ?? ''}
                                    className="h-full w-full object-cover"
                                />
                            )}
                        </div>
                        <CardHeader className="pb-2">
                            <CardTitle className="line-clamp-1 text-sm">{item.item_name || item.file_name}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-xs text-muted-foreground">
                            <div className="flex items-center justify-between gap-2">
                                <p className="truncate">{item.provider_slug}</p>
                                {item.served_count > 0 && (
                                    <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] tabular-nums">
                                        {item.served_count}× baixado
                                    </span>
                                )}
                            </div>
                            <a
                                href={route('library.file', item.public_id)}
                                className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                download
                            >
                                Baixar arquivo
                            </a>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Créditos', href: '/billing' }];

type Package = {
    id: number;
    name: string;
    credits: number;
    bonus_credits: number;
    price_cents: number;
};

type Props = {
    packages: Package[];
    mercadopago: { enabled: boolean; sandbox: boolean; public_key: string | null };
};

export default function BillingIndex({ packages, mercadopago }: Props) {
    const { post, processing, setData, data } = useForm({ package_id: 0, method: 'pix' });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Créditos" />
            <div className="grid gap-4 p-4 lg:grid-cols-3">
                {packages.map((pkg) => (
                    <Card key={pkg.id}>
                        <CardHeader>
                            <CardTitle>{pkg.name}</CardTitle>
                            <CardDescription>
                                {pkg.credits} créditos {pkg.bonus_credits > 0 && `+ ${pkg.bonus_credits} bônus`}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-2xl font-semibold">
                                R$ {(pkg.price_cents / 100).toFixed(2).replace('.', ',')}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    disabled={processing || !mercadopago.enabled}
                                    onClick={() => {
                                        setData({ package_id: pkg.id, method: 'pix' });
                                        post(route('billing.checkout'));
                                    }}
                                >
                                    Pagar com Pix
                                </Button>
                                <Button
                                    variant="outline"
                                    disabled={processing || !mercadopago.enabled}
                                    onClick={() => {
                                        setData({ package_id: pkg.id, method: 'credit_card' });
                                        post(route('billing.checkout'));
                                    }}
                                >
                                    Cartão
                                </Button>
                            </div>
                            {!mercadopago.enabled && (
                                <p className="text-xs text-muted-foreground">
                                    Pagamentos automáticos desabilitados — peça créditos ao admin.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ))}
                {packages.length === 0 && (
                    <p className="text-sm text-muted-foreground">Nenhum pacote configurado ainda.</p>
                )}
            </div>
        </AppLayout>
    );
}

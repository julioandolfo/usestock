import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';

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

type SharedProps = {
    brand?: { name?: string; support_whatsapp?: string | null };
};

function formatWhatsapp(raw: string): string {
    // 5535991803209 → (35) 99180-3209
    const digits = raw.replace(/\D+/g, '');
    const local = digits.startsWith('55') ? digits.slice(2) : digits;
    if (local.length === 11) {
        return `(${local.slice(0, 2)}) ${local.slice(2, 7)}-${local.slice(7)}`;
    }
    if (local.length === 10) {
        return `(${local.slice(0, 2)}) ${local.slice(2, 6)}-${local.slice(6)}`;
    }
    return raw;
}

export default function BillingIndex({ packages, mercadopago }: Props) {
    const { post, processing, setData } = useForm({ package_id: 0, method: 'pix' });
    const brand = usePage<SharedProps>().props.brand;
    const whatsappRaw = brand?.support_whatsapp ?? null;
    const whatsappDigits = whatsappRaw ? whatsappRaw.replace(/\D+/g, '') : null;
    const whatsappPretty = whatsappRaw ? formatWhatsapp(whatsappRaw) : null;
    const whatsappLink = whatsappDigits
        ? `https://wa.me/${whatsappDigits}?text=${encodeURIComponent(
              `Olá! Tenho interesse em comprar créditos no ${brand?.name ?? 'UseStock'}.`,
          )}`
        : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Créditos" />
            <div className="space-y-4 p-3 sm:p-4">
                {whatsappLink && (
                    <Card className="border-emerald-500/40 bg-gradient-to-r from-emerald-500/10 to-emerald-500/0">
                        <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div className="flex items-start gap-3">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                                    <MessageCircle className="size-5" />
                                </div>
                                <div>
                                    <p className="font-semibold">Prefere conversar antes de comprar?</p>
                                    <p className="text-sm text-muted-foreground">
                                        Fale com a gente no WhatsApp: <span className="font-medium">{whatsappPretty}</span>
                                    </p>
                                </div>
                            </div>
                            <Button
                                asChild
                                className="bg-emerald-600 text-white hover:bg-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                            >
                                <a href={whatsappLink} target="_blank" rel="noopener noreferrer">
                                    <MessageCircle className="mr-2 size-4" />
                                    Chamar no WhatsApp
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                                <div className="flex flex-wrap gap-2">
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
                                {!mercadopago.enabled && whatsappLink && (
                                    <p className="text-xs text-muted-foreground">
                                        Pagamentos automáticos indisponíveis.{' '}
                                        <a
                                            href={whatsappLink}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-emerald-600 underline hover:text-emerald-500"
                                        >
                                            Compre via WhatsApp
                                        </a>
                                        .
                                    </p>
                                )}
                                {!mercadopago.enabled && !whatsappLink && (
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
            </div>
        </AppLayout>
    );
}

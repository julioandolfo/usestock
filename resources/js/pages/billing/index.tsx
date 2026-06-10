import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Coins, MessageCircle } from 'lucide-react';

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
            <div className="mx-auto w-full max-w-5xl space-y-4 p-3 sm:space-y-6 sm:p-4">
                <header>
                    <h1 className="text-xl font-bold tracking-tight sm:text-2xl">Comprar créditos</h1>
                    <p className="text-xs text-muted-foreground sm:text-sm">
                        Escolha um pacote para recarregar sua conta. O crédito não expira.
                    </p>
                </header>

                {whatsappLink && (
                    <div className="flex flex-col items-stretch gap-3 rounded-lg border border-emerald-500/40 bg-gradient-to-r from-emerald-500/10 via-emerald-500/5 to-transparent p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                        <div className="flex items-center gap-3 min-w-0">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                                <MessageCircle className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-sm font-semibold sm:text-base">Prefere conversar antes de comprar?</p>
                                <p className="truncate text-xs text-muted-foreground sm:text-sm">
                                    Fale no WhatsApp <span className="font-medium text-foreground">{whatsappPretty}</span>
                                </p>
                            </div>
                        </div>
                        <Button
                            asChild
                            size="sm"
                            className="shrink-0 bg-emerald-600 text-white hover:bg-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                        >
                            <a href={whatsappLink} target="_blank" rel="noopener noreferrer">
                                <MessageCircle className="mr-2 size-4" />
                                Chamar no WhatsApp
                            </a>
                        </Button>
                    </div>
                )}

                {packages.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-10 text-center sm:py-14">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Coins className="size-5" />
                            </div>
                            <div>
                                <p className="text-sm font-medium sm:text-base">Nenhum pacote disponível no momento</p>
                                <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                    {whatsappLink
                                        ? 'Fale conosco no WhatsApp acima para combinar uma recarga personalizada.'
                                        : 'Volte mais tarde ou peça uma recarga ao administrador.'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                        {packages.map((pkg) => (
                            <Card key={pkg.id}>
                                <CardHeader>
                                    <CardTitle>{pkg.name}</CardTitle>
                                    <CardDescription>
                                        {pkg.credits} créditos
                                        {pkg.bonus_credits > 0 && (
                                            <span className="text-primary"> + {pkg.bonus_credits} bônus</span>
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <p className="text-xl font-semibold sm:text-2xl">
                                        R$ {(pkg.price_cents / 100).toFixed(2).replace('.', ',')}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            disabled={processing || !mercadopago.enabled}
                                            onClick={() => {
                                                setData({ package_id: pkg.id, method: 'pix' });
                                                post(route('billing.checkout'));
                                            }}
                                        >
                                            Pagar com Pix
                                        </Button>
                                        <Button
                                            size="sm"
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
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

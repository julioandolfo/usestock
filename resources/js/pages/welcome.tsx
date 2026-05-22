import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLogoIcon from '@/components/app-logo-icon';
import { formatBRL } from '@/lib/format';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, Download, Layers, ShieldCheck, Sparkles, Zap } from 'lucide-react';

type Provider = {
    slug: string;
    name: string;
    host: string | null;
    has_premium: boolean;
};

type Package = {
    id: number;
    name: string;
    description: string | null;
    credits: number;
    bonus_credits: number;
    price_cents: number;
    currency: string;
    featured: boolean;
};

type Limits = { bulk_max_items: number; file_ttl_days: number };

type SharedProps = {
    auth?: { user?: { id: number; name: string; is_admin: boolean } | null };
    brand?: { name: string };
};

export default function Welcome({
    providers,
    packages,
    limits,
}: {
    providers: Provider[];
    packages: Package[];
    limits: Limits;
}) {
    const { props } = usePage<SharedProps>();
    const user = props.auth?.user ?? null;
    const brand = props.brand?.name ?? 'UseStock';

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={`${brand} · Banco de imagens premium por preço justo`} />

            {/* Header */}
            <header className="sticky top-0 z-40 border-b border-border/40 bg-background/80 backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <Link href="/" className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-4 fill-current" />
                        </div>
                        <span className="text-lg font-semibold">{brand}</span>
                    </Link>
                    <nav className="flex items-center gap-3">
                        {user ? (
                            <>
                                <span className="hidden text-sm text-muted-foreground sm:inline">
                                    Olá, {user.name.split(' ')[0]}
                                </span>
                                <Button asChild>
                                    <Link href={route('dashboard')}>Acessar painel</Link>
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={route('login')}>Entrar</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={route('register')}>Criar conta</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            {/* Hero */}
            <section className="mx-auto max-w-6xl px-6 py-16 md:py-24">
                <div className="mx-auto max-w-3xl text-center">
                    <Badge variant="outline" className="mb-6">
                        <Sparkles className="mr-1 size-3" />
                        Acesso aos maiores bancos de imagem em um só lugar
                    </Badge>
                    <h1 className="text-4xl font-bold tracking-tight md:text-6xl">
                        Baixe arquivos premium sem comprar assinatura em cada site
                    </h1>
                    <p className="mt-6 text-lg text-muted-foreground md:text-xl">
                        Cole o link de qualquer foto, vídeo, vetor ou template dos principais bancos do mercado e
                        receba o arquivo original liberado em segundos. Pague apenas o que usar.
                    </p>
                    <div className="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        {user ? (
                            <Button size="lg" asChild>
                                <Link href={route('downloads.index')}>
                                    <Download className="mr-2 size-4" />
                                    Ir para meus downloads
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button size="lg" asChild>
                                    <Link href={route('register')}>Começar agora — é grátis</Link>
                                </Button>
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={route('login')}>Já tenho conta</Link>
                                </Button>
                            </>
                        )}
                    </div>
                    <p className="mt-4 text-xs text-muted-foreground">
                        Sem mensalidade · Crédito não expira · Pix e cartão via MercadoPago
                    </p>
                </div>
            </section>

            {/* Como funciona */}
            <section className="mx-auto max-w-6xl px-6 pb-16">
                <div className="mb-10 text-center">
                    <h2 className="text-3xl font-bold tracking-tight md:text-4xl">Como funciona</h2>
                    <p className="mt-3 text-muted-foreground">Três passos. Sem fricção.</p>
                </div>
                <div className="grid gap-6 md:grid-cols-3">
                    <Step
                        n={1}
                        icon={<Layers className="size-5" />}
                        title="Encontre o arquivo"
                        desc="Vá no banco de imagens da sua preferência (Shutterstock, Freepik, Adobe Stock, etc.) e copie o link do item que você quer baixar."
                    />
                    <Step
                        n={2}
                        icon={<Zap className="size-5" />}
                        title="Cole no painel"
                        desc={`Acesse seu painel, cole o link (ou até ${limits.bulk_max_items} de uma vez) e clique em iniciar. Os créditos são reservados na hora.`}
                    />
                    <Step
                        n={3}
                        icon={<Download className="size-5" />}
                        title="Receba o arquivo original"
                        desc={`Em segundos, o arquivo aparece pronto pra download no seu painel — fica disponível por ${limits.file_ttl_days} dias pra você baixar de novo sem custo.`}
                    />
                </div>
            </section>

            {/* Providers */}
            {providers.length > 0 && (
                <section className="border-y border-border/40 bg-muted/30 py-16">
                    <div className="mx-auto max-w-6xl px-6">
                        <div className="mb-8 text-center">
                            <h2 className="text-2xl font-bold md:text-3xl">Bancos suportados</h2>
                            <p className="mt-2 text-muted-foreground">
                                {providers.length} provedores ativos — adicionamos novos constantemente
                            </p>
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                            {providers.map((p) => (
                                <div
                                    key={p.slug}
                                    className="flex items-center justify-between rounded-md border bg-background px-3 py-2 text-sm"
                                >
                                    <span className="truncate font-medium capitalize">{p.name}</span>
                                    {p.has_premium && (
                                        <Badge variant="outline" className="ml-2 shrink-0 text-[10px]">
                                            Premium
                                        </Badge>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Benefícios */}
            <section className="mx-auto max-w-6xl px-6 py-16">
                <div className="mb-10 text-center">
                    <h2 className="text-3xl font-bold tracking-tight md:text-4xl">Feito para quem produz conteúdo</h2>
                </div>
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Benefit
                        icon={<ShieldCheck className="size-5" />}
                        title="Pague só pelo que usar"
                        desc="Compra de créditos avulsa. Nada de mensalidade. O crédito não expira."
                    />
                    <Benefit
                        icon={<Clock className="size-5" />}
                        title="Histórico salvo"
                        desc={`Sua biblioteca guarda tudo o que você baixou por ${limits.file_ttl_days} dias. Re-download é grátis nesse período.`}
                    />
                    <Benefit
                        icon={<Layers className="size-5" />}
                        title="Baixe em lote"
                        desc={`Cole até ${limits.bulk_max_items} links de uma vez e receba tudo empacotado num ZIP único.`}
                    />
                    <Benefit
                        icon={<Zap className="size-5" />}
                        title="Tempo real"
                        desc="O status de cada arquivo atualiza ao vivo enquanto é processado — sem precisar dar refresh."
                    />
                    <Benefit
                        icon={<ShieldCheck className="size-5" />}
                        title="Estorno automático"
                        desc="Se algum download falhar, os créditos voltam pra sua conta na hora. Você não paga pelo que não recebeu."
                    />
                    <Benefit
                        icon={<CheckCircle2 className="size-5" />}
                        title="Pagamento brasileiro"
                        desc="Pix instantâneo ou cartão via MercadoPago. Receba o crédito assim que o pagamento for aprovado."
                    />
                </div>
            </section>

            {/* Pacotes */}
            {packages.length > 0 && (
                <section className="border-t border-border/40 bg-muted/30 py-16">
                    <div className="mx-auto max-w-6xl px-6">
                        <div className="mb-10 text-center">
                            <h2 className="text-3xl font-bold tracking-tight md:text-4xl">Comece com o pacote ideal</h2>
                            <p className="mt-3 text-muted-foreground">
                                Quanto maior o pacote, mais créditos de bônus você ganha
                            </p>
                        </div>
                        <div className="grid gap-6 md:grid-cols-3">
                            {packages.map((pkg) => (
                                <Card key={pkg.id} className={pkg.featured ? 'border-primary shadow-lg' : ''}>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <CardTitle>{pkg.name}</CardTitle>
                                            {pkg.featured && <Badge>Mais popular</Badge>}
                                        </div>
                                        <CardDescription>{pkg.description ?? 'Pacote de créditos'}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <p className="text-4xl font-bold">{formatBRL(pkg.price_cents)}</p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {pkg.credits} créditos
                                                {pkg.bonus_credits > 0 && (
                                                    <span className="text-primary"> + {pkg.bonus_credits} bônus</span>
                                                )}
                                            </p>
                                        </div>
                                        <Button asChild className="w-full" variant={pkg.featured ? 'default' : 'outline'}>
                                            <Link href={user ? route('billing.index') : route('register')}>
                                                {user ? 'Comprar agora' : 'Criar conta e comprar'}
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* FAQ */}
            <section className="mx-auto max-w-3xl px-6 py-16">
                <div className="mb-10 text-center">
                    <h2 className="text-3xl font-bold tracking-tight md:text-4xl">Perguntas frequentes</h2>
                </div>
                <div className="space-y-4">
                    <FaqItem
                        q="Os arquivos têm marca d'água?"
                        a="Não. Você recebe o arquivo original em alta resolução, exatamente como seria se você assinasse cada site individualmente."
                    />
                    <FaqItem
                        q="Quanto custa cada download em créditos?"
                        a="Depende do provedor e se o conteúdo é premium ou normal. O custo aparece no painel antes de você confirmar o download. Itens normais começam em 1 crédito; premium varia conforme o site de origem."
                    />
                    <FaqItem
                        q="Posso usar comercialmente?"
                        a="Você tem os mesmos direitos de uso que o site de origem oferece para a sua licença. Confira sempre os termos do banco de imagens específico antes de usar em projetos comerciais."
                    />
                    <FaqItem
                        q="O que acontece se o download falhar?"
                        a="Os créditos voltam automaticamente pra sua conta. Você só paga pelos arquivos que recebeu com sucesso."
                    />
                    <FaqItem
                        q="Posso baixar várias coisas de uma vez?"
                        a={`Sim — cole até ${limits.bulk_max_items} links de uma vez e nós processamos tudo em paralelo. Opcionalmente, empacotamos os arquivos num único ZIP.`}
                    />
                    <FaqItem
                        q="Por quanto tempo posso re-baixar um arquivo?"
                        a={`Tudo que você baixa fica na sua biblioteca por ${limits.file_ttl_days} dias. Nesse período, re-download é grátis (não consome créditos).`}
                    />
                </div>
            </section>

            {/* CTA final */}
            <section className="border-t border-border/40 bg-primary/5">
                <div className="mx-auto max-w-3xl px-6 py-16 text-center">
                    <h2 className="text-3xl font-bold tracking-tight md:text-4xl">Pronto para começar?</h2>
                    <p className="mt-3 text-muted-foreground">
                        Crie sua conta em menos de um minuto. Sem cartão de crédito necessário para registrar.
                    </p>
                    <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        {user ? (
                            <Button size="lg" asChild>
                                <Link href={route('dashboard')}>Acessar painel</Link>
                            </Button>
                        ) : (
                            <>
                                <Button size="lg" asChild>
                                    <Link href={route('register')}>Criar conta gratuita</Link>
                                </Button>
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={route('login')}>Entrar</Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="border-t border-border/40 py-8">
                <div className="mx-auto max-w-6xl px-6 text-center text-xs text-muted-foreground">
                    <p>© {new Date().getFullYear()} {brand}. Todos os direitos reservados.</p>
                </div>
            </footer>
        </div>
    );
}

function Step({ n, icon, title, desc }: { n: number; icon: React.ReactNode; title: string; desc: string }) {
    return (
        <Card className="relative overflow-hidden">
            <CardHeader>
                <div className="absolute right-4 top-4 text-6xl font-bold text-muted/30">{n}</div>
                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                    {icon}
                </div>
                <CardTitle className="text-lg">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">{desc}</p>
            </CardContent>
        </Card>
    );
}

function Benefit({ icon, title, desc }: { icon: React.ReactNode; title: string; desc: string }) {
    return (
        <div className="rounded-lg border bg-background p-6">
            <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                {icon}
            </div>
            <h3 className="font-semibold">{title}</h3>
            <p className="mt-2 text-sm text-muted-foreground">{desc}</p>
        </div>
    );
}

function FaqItem({ q, a }: { q: string; a: string }) {
    return (
        <details className="group rounded-lg border bg-background p-4">
            <summary className="cursor-pointer list-none font-medium">
                <div className="flex items-center justify-between">
                    <span>{q}</span>
                    <span className="ml-2 transition-transform group-open:rotate-180">▾</span>
                </div>
            </summary>
            <p className="mt-3 text-sm text-muted-foreground">{a}</p>
        </details>
    );
}

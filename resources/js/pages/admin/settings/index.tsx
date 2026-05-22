import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Configurações', href: '/admin/settings' },
];

type Props = {
    general: {
        brand_name: string;
        support_email: string;
        primary_color: string;
        allow_registration: boolean;
        require_email_verification: boolean;
    };
    getstocks: {
        base_url: string;
        email: string | null;
        poll_interval_seconds: number;
        poll_max_attempts: number;
        request_timeout_seconds: number;
        use_webhook: boolean;
        low_balance_threshold: number;
        has_token: boolean;
    };
    mercadopago: {
        enabled: boolean;
        sandbox: boolean;
        public_key: string | null;
        has_access_token: boolean;
        currency: string;
        accepted_methods: string[];
    };
    mail: {
        driver: string;
        from_address: string;
        from_name: string;
        has_resend_key: boolean;
    };
    downloads: {
        file_ttl_days: number;
        max_concurrent_per_user: number;
        rate_limit_per_hour: number;
        bulk_max_items: number;
        signed_url_ttl_minutes: number;
        auto_refund_on_failure: boolean;
    };
};

export default function SettingsIndex(props: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configurações" />
            <div className="p-4">
                <Tabs defaultValue="general">
                    <TabsList>
                        <TabsTrigger value="general">Geral</TabsTrigger>
                        <TabsTrigger value="getstocks">GetStocks</TabsTrigger>
                        <TabsTrigger value="mercadopago">MercadoPago</TabsTrigger>
                        <TabsTrigger value="mail">Email</TabsTrigger>
                        <TabsTrigger value="downloads">Downloads</TabsTrigger>
                    </TabsList>

                    <TabsContent value="general">
                        <GeneralForm initial={props.general} />
                    </TabsContent>
                    <TabsContent value="getstocks">
                        <GetstocksForm initial={props.getstocks} />
                    </TabsContent>
                    <TabsContent value="mercadopago">
                        <MercadoPagoForm initial={props.mercadopago} />
                    </TabsContent>
                    <TabsContent value="mail">
                        <MailForm initial={props.mail} />
                    </TabsContent>
                    <TabsContent value="downloads">
                        <DownloadsForm initial={props.downloads} />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

function GeneralForm({ initial }: { initial: Props['general'] }) {
    const form = useForm(initial);
    return (
        <Card>
            <CardHeader>
                <CardTitle>Geral</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('admin.settings.general'), { preserveScroll: true });
                    }}
                    className="grid gap-4 md:grid-cols-2"
                >
                    <Field label="Nome da marca" error={form.errors.brand_name}>
                        <Input value={form.data.brand_name} onChange={(e) => form.setData('brand_name', e.target.value)} />
                    </Field>
                    <Field label="Email de suporte" error={form.errors.support_email}>
                        <Input value={form.data.support_email} onChange={(e) => form.setData('support_email', e.target.value)} />
                    </Field>
                    <Field label="Cor primária" error={form.errors.primary_color}>
                        <Input value={form.data.primary_color} onChange={(e) => form.setData('primary_color', e.target.value)} />
                    </Field>
                    <SwitchRow
                        label="Permitir registro público"
                        checked={form.data.allow_registration}
                        onChange={(v) => form.setData('allow_registration', v)}
                    />
                    <SwitchRow
                        label="Exigir verificação de email"
                        checked={form.data.require_email_verification}
                        onChange={(v) => form.setData('require_email_verification', v)}
                    />
                    <Button type="submit" disabled={form.processing} className="md:col-span-2">
                        Salvar
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function GetstocksForm({ initial }: { initial: Props['getstocks'] }) {
    const form = useForm({ ...initial, password: '' });
    return (
        <Card>
            <CardHeader>
                <CardTitle>GetStocks API</CardTitle>
                <CardDescription>
                    Token persistido em DB: <Badge variant={initial.has_token ? 'default' : 'outline'}>{initial.has_token ? 'sim' : 'não'}</Badge>
                    {' · '}
                    Atualize a senha para forçar re-autenticação.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('admin.settings.getstocks'), { preserveScroll: true });
                    }}
                    className="grid gap-4 md:grid-cols-2"
                >
                    <Field label="Base URL" error={form.errors.base_url}>
                        <Input value={form.data.base_url} onChange={(e) => form.setData('base_url', e.target.value)} />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <Input value={form.data.email ?? ''} onChange={(e) => form.setData('email', e.target.value)} />
                    </Field>
                    <Field label="Nova senha (opcional)" error={form.errors.password}>
                        <Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                    </Field>
                    <Field label="Limite de saldo baixo" error={form.errors.low_balance_threshold}>
                        <Input
                            type="number"
                            value={form.data.low_balance_threshold}
                            onChange={(e) => form.setData('low_balance_threshold', parseInt(e.target.value || '0', 10))}
                        />
                    </Field>
                    <Field label="Intervalo de polling (s)">
                        <Input
                            type="number"
                            value={form.data.poll_interval_seconds}
                            onChange={(e) => form.setData('poll_interval_seconds', parseInt(e.target.value || '10', 10))}
                        />
                    </Field>
                    <Field label="Máx. tentativas de polling">
                        <Input
                            type="number"
                            value={form.data.poll_max_attempts}
                            onChange={(e) => form.setData('poll_max_attempts', parseInt(e.target.value || '30', 10))}
                        />
                    </Field>
                    <Field label="Timeout HTTP (s)">
                        <Input
                            type="number"
                            value={form.data.request_timeout_seconds}
                            onChange={(e) => form.setData('request_timeout_seconds', parseInt(e.target.value || '30', 10))}
                        />
                    </Field>
                    <SwitchRow
                        label="Usar webhook (recomendado)"
                        checked={form.data.use_webhook}
                        onChange={(v) => form.setData('use_webhook', v)}
                    />
                    <Button type="submit" disabled={form.processing} className="md:col-span-2">
                        Salvar
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function MercadoPagoForm({ initial }: { initial: Props['mercadopago'] }) {
    const form = useForm({
        ...initial,
        access_token: '',
        webhook_secret: '',
    });
    return (
        <Card>
            <CardHeader>
                <CardTitle>MercadoPago</CardTitle>
                <CardDescription>
                    Access token configurado:{' '}
                    <Badge variant={initial.has_access_token ? 'default' : 'outline'}>
                        {initial.has_access_token ? 'sim' : 'não'}
                    </Badge>
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('admin.settings.mercadopago'), { preserveScroll: true });
                    }}
                    className="grid gap-4 md:grid-cols-2"
                >
                    <SwitchRow label="Habilitado" checked={form.data.enabled} onChange={(v) => form.setData('enabled', v)} />
                    <SwitchRow label="Sandbox" checked={form.data.sandbox} onChange={(v) => form.setData('sandbox', v)} />
                    <Field label="Novo access token (opcional)">
                        <Input type="password" value={form.data.access_token} onChange={(e) => form.setData('access_token', e.target.value)} />
                    </Field>
                    <Field label="Public key">
                        <Input value={form.data.public_key ?? ''} onChange={(e) => form.setData('public_key', e.target.value)} />
                    </Field>
                    <Field label="Novo webhook secret (opcional)">
                        <Input type="password" value={form.data.webhook_secret} onChange={(e) => form.setData('webhook_secret', e.target.value)} />
                    </Field>
                    <Field label="Moeda">
                        <Input value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} />
                    </Field>
                    <Button type="submit" disabled={form.processing} className="md:col-span-2">
                        Salvar
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function MailForm({ initial }: { initial: Props['mail'] }) {
    const form = useForm({ ...initial, resend_api_key: '' });
    return (
        <Card>
            <CardHeader>
                <CardTitle>Email</CardTitle>
                <CardDescription>
                    Chave Resend configurada:{' '}
                    <Badge variant={initial.has_resend_key ? 'default' : 'outline'}>
                        {initial.has_resend_key ? 'sim' : 'não'}
                    </Badge>
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('admin.settings.mail'), { preserveScroll: true });
                    }}
                    className="grid gap-4 md:grid-cols-2"
                >
                    <Field label="Driver">
                        <select
                            className="w-full rounded-md border bg-background p-2 text-sm"
                            value={form.data.driver}
                            onChange={(e) => form.setData('driver', e.target.value)}
                        >
                            <option value="log">log (dev)</option>
                            <option value="resend">resend</option>
                        </select>
                    </Field>
                    <Field label="Nova Resend API key (opcional)">
                        <Input type="password" value={form.data.resend_api_key} onChange={(e) => form.setData('resend_api_key', e.target.value)} />
                    </Field>
                    <Field label="De (endereço)">
                        <Input value={form.data.from_address} onChange={(e) => form.setData('from_address', e.target.value)} />
                    </Field>
                    <Field label="De (nome)">
                        <Input value={form.data.from_name} onChange={(e) => form.setData('from_name', e.target.value)} />
                    </Field>
                    <Button type="submit" disabled={form.processing} className="md:col-span-2">
                        Salvar
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function DownloadsForm({ initial }: { initial: Props['downloads'] }) {
    const form = useForm(initial);
    return (
        <Card>
            <CardHeader>
                <CardTitle>Downloads</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('admin.settings.downloads'), { preserveScroll: true });
                    }}
                    className="grid gap-4 md:grid-cols-2"
                >
                    <Field label="TTL do arquivo (dias)">
                        <Input
                            type="number"
                            value={form.data.file_ttl_days}
                            onChange={(e) => form.setData('file_ttl_days', parseInt(e.target.value || '30', 10))}
                        />
                    </Field>
                    <Field label="Máx. downloads simultâneos por usuário">
                        <Input
                            type="number"
                            value={form.data.max_concurrent_per_user}
                            onChange={(e) => form.setData('max_concurrent_per_user', parseInt(e.target.value || '3', 10))}
                        />
                    </Field>
                    <Field label="Limite por hora (por usuário)">
                        <Input
                            type="number"
                            value={form.data.rate_limit_per_hour}
                            onChange={(e) => form.setData('rate_limit_per_hour', parseInt(e.target.value || '60', 10))}
                        />
                    </Field>
                    <Field label="Máximo de itens em lote">
                        <Input
                            type="number"
                            value={form.data.bulk_max_items}
                            onChange={(e) => form.setData('bulk_max_items', parseInt(e.target.value || '50', 10))}
                        />
                    </Field>
                    <Field label="TTL da URL assinada (min)">
                        <Input
                            type="number"
                            value={form.data.signed_url_ttl_minutes}
                            onChange={(e) => form.setData('signed_url_ttl_minutes', parseInt(e.target.value || '5', 10))}
                        />
                    </Field>
                    <SwitchRow
                        label="Estornar automaticamente em falha"
                        checked={form.data.auto_refund_on_failure}
                        onChange={(v) => form.setData('auto_refund_on_failure', v)}
                    />
                    <Button type="submit" disabled={form.processing} className="md:col-span-2">
                        Salvar
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function SwitchRow({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <div className="flex items-center justify-between rounded-md border p-3">
            <Label>{label}</Label>
            <Switch checked={checked} onCheckedChange={onChange} />
        </div>
    );
}

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type InstallForm = {
    admin_name: string;
    admin_email: string;
    admin_password: string;
    admin_password_confirmation: string;
    brand_name: string;
    support_email: string;
    getstocks_email: string;
    getstocks_password: string;
};

export default function InstallWizard() {
    const { data, setData, post, processing, errors } = useForm<InstallForm>({
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
        brand_name: 'UseStock',
        support_email: '',
        getstocks_email: '',
        getstocks_password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('install.store'));
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-6">
            <Head title="Instalação" />
            <Card className="w-full max-w-2xl">
                <CardHeader>
                    <CardTitle>Bem-vindo ao UseStock</CardTitle>
                    <CardDescription>
                        Configure seu admin e as credenciais da API do GetStocks para começar. Essas informações ficam
                        salvas em banco e podem ser editadas depois no painel administrativo.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        <section className="space-y-4">
                            <h3 className="text-sm font-semibold text-foreground">Administrador</h3>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="admin_name">Nome</Label>
                                    <Input
                                        id="admin_name"
                                        value={data.admin_name}
                                        onChange={(e) => setData('admin_name', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.admin_name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="admin_email">E-mail</Label>
                                    <Input
                                        id="admin_email"
                                        type="email"
                                        value={data.admin_email}
                                        onChange={(e) => setData('admin_email', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.admin_email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="admin_password">Senha</Label>
                                    <Input
                                        id="admin_password"
                                        type="password"
                                        value={data.admin_password}
                                        onChange={(e) => setData('admin_password', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.admin_password} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="admin_password_confirmation">Confirmar senha</Label>
                                    <Input
                                        id="admin_password_confirmation"
                                        type="password"
                                        value={data.admin_password_confirmation}
                                        onChange={(e) => setData('admin_password_confirmation', e.target.value)}
                                        required
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h3 className="text-sm font-semibold text-foreground">Marca</h3>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="brand_name">Nome da marca</Label>
                                    <Input
                                        id="brand_name"
                                        value={data.brand_name}
                                        onChange={(e) => setData('brand_name', e.target.value)}
                                    />
                                    <InputError message={errors.brand_name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="support_email">E-mail de suporte</Label>
                                    <Input
                                        id="support_email"
                                        type="email"
                                        value={data.support_email}
                                        onChange={(e) => setData('support_email', e.target.value)}
                                    />
                                    <InputError message={errors.support_email} />
                                </div>
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h3 className="text-sm font-semibold text-foreground">GetStocks API</h3>
                            <p className="text-sm text-muted-foreground">
                                Conta corporativa que será usada para baixar arquivos em nome dos seus usuários.
                            </p>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="getstocks_email">E-mail</Label>
                                    <Input
                                        id="getstocks_email"
                                        type="email"
                                        value={data.getstocks_email}
                                        onChange={(e) => setData('getstocks_email', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.getstocks_email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="getstocks_password">Senha</Label>
                                    <Input
                                        id="getstocks_password"
                                        type="password"
                                        value={data.getstocks_password}
                                        onChange={(e) => setData('getstocks_password', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.getstocks_password} />
                                </div>
                            </div>
                        </section>

                        <Button type="submit" disabled={processing} className="w-full">
                            {processing ? 'Validando…' : 'Concluir instalação'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

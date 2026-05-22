<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Bem-vindo ao {{ $brand }}</title>
</head>
<body style="margin:0;padding:0;background:#0f172a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#f8fafc;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:100%;background:#1e293b;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 32px 8px 32px;text-align:center;">
                            <div style="display:inline-block;width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0 32px;">
                            <h1 style="margin:0 0 8px 0;font-size:24px;line-height:32px;color:#f8fafc;text-align:center;">
                                Bem-vindo ao {{ $brand }}, {{ explode(' ', $user->name)[0] }}!
                            </h1>
                            <p style="margin:0 0 24px 0;color:#cbd5e1;text-align:center;font-size:15px;line-height:22px;">
                                Sua conta foi criada com sucesso. Estamos felizes em ter você por aqui.
                            </p>
                        </td>
                    </tr>

                    @if ($plainPassword)
                        <tr>
                            <td style="padding:0 32px;">
                                <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:16px;margin-bottom:24px;">
                                    <p style="margin:0 0 8px 0;font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;">
                                        Seus dados de acesso
                                    </p>
                                    <p style="margin:0 0 4px 0;font-size:14px;color:#cbd5e1;">
                                        <strong style="color:#f8fafc;">E-mail:</strong> {{ $user->email }}
                                    </p>
                                    <p style="margin:0;font-size:14px;color:#cbd5e1;">
                                        <strong style="color:#f8fafc;">Senha temporária:</strong>
                                        <code style="background:#1e293b;padding:2px 6px;border-radius:4px;color:#a5b4fc;">{{ $plainPassword }}</code>
                                    </p>
                                    <p style="margin:8px 0 0 0;font-size:12px;color:#94a3b8;">
                                        Recomendamos trocar a senha no primeiro acesso.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:0 32px;text-align:center;">
                            <a href="{{ $loginUrl }}"
                               style="display:inline-block;padding:14px 28px;background:#6366f1;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;">
                                Acessar minha conta
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 32px 16px 32px;">
                            <p style="margin:0 0 8px 0;font-size:14px;color:#cbd5e1;line-height:22px;">
                                Como funciona:
                            </p>
                            <ol style="margin:0;padding-left:20px;color:#cbd5e1;font-size:14px;line-height:22px;">
                                <li>Compre créditos quando precisar</li>
                                <li>Cole o link do banco de imagens preferido</li>
                                <li>Receba o arquivo original em segundos</li>
                            </ol>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 32px 32px;border-top:1px solid #334155;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;text-align:center;line-height:18px;">
                                Precisa de ajuda?
                                @if ($supportWhatsapp)
                                    Fale com a gente no WhatsApp ou pelo
                                @endif
                                e-mail <a href="mailto:{{ $supportEmail }}" style="color:#a5b4fc;">{{ $supportEmail }}</a>.
                                <br>
                                © {{ date('Y') }} {{ $brand }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

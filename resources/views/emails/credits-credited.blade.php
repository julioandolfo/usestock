<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>+{{ $amount }} créditos na sua conta</title>
</head>
<body style="margin:0;padding:0;background:#0f172a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#f8fafc;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:100%;background:#1e293b;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 32px 16px 32px;text-align:center;">
                            <div style="display:inline-block;padding:16px;border-radius:50%;background:rgba(16,185,129,0.15);">
                                <span style="display:inline-block;width:24px;height:24px;color:#10b981;font-size:24px;line-height:24px;">✓</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px;">
                            <h1 style="margin:0 0 8px 0;font-size:24px;line-height:32px;color:#f8fafc;text-align:center;">
                                +{{ $amount }} crédito{{ $amount === 1 ? '' : 's' }} adicionado{{ $amount === 1 ? '' : 's' }}
                            </h1>
                            <p style="margin:0 0 24px 0;color:#cbd5e1;text-align:center;font-size:15px;line-height:22px;">
                                Olá {{ explode(' ', $user->name)[0] }}, sua conta no <strong>{{ $brand }}</strong> foi recarregada.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px;">
                            <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:16px;margin-bottom:24px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;padding-bottom:4px;">
                                            Créditos creditados
                                        </td>
                                        <td style="font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;padding-bottom:4px;text-align:right;">
                                            Saldo atual
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="font-size:22px;font-weight:600;color:#10b981;">
                                            +{{ $amount }}
                                        </td>
                                        <td style="font-size:22px;font-weight:600;color:#f8fafc;text-align:right;">
                                            {{ $balanceAfter }}
                                        </td>
                                    </tr>
                                </table>
                                @if ($reason)
                                    <p style="margin:12px 0 0 0;font-size:13px;color:#cbd5e1;border-top:1px solid #334155;padding-top:12px;">
                                        <strong style="color:#f8fafc;">Motivo:</strong> {{ $reason }}
                                    </p>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px;text-align:center;">
                            <a href="{{ $dashboardUrl }}"
                               style="display:inline-block;padding:14px 28px;background:#6366f1;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;">
                                Ir para o painel
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 32px 32px 32px;border-top:1px solid #334155;margin-top:24px;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;text-align:center;line-height:18px;">
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

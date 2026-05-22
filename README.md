# UseStock

SaaS Laravel para distribuição de downloads agregados via API GetStocks.

- **Backend**: Laravel 12 (PHP 8.4) · Sanctum · Horizon · Reverb
- **Frontend**: Inertia 2 · React 19 · TypeScript · Tailwind v4 · shadcn/ui
- **Persistência**: PostgreSQL 16 · Redis 7 · disco local com TTL
- **Pagamentos**: MercadoPago (Pix/Cartão) + crédito manual
- **Deploy**: Docker Compose pronto para Coolify, sem `.env` no repo

## Estrutura

```
app/
├── Http/Controllers/         Web + Admin + Webhook controllers
├── Jobs/                     ProcessDownload, PollDownloadStatus, StreamDownloadFile, SyncProviders, CleanExpired
├── Models/                   Domain models (User, Provider, DownloadRequest, ...)
├── Services/
│   ├── GetStocks/            Cliente HTTP da API upstream (com auto-reauth)
│   ├── Pricing/              Cálculo de créditos por provider/regra
│   ├── Downloads/            Ledger transacional de créditos
│   └── Payments/             Gateway MercadoPago
└── Settings/                 Configurações em DB (criptografadas onde aplicável)

docker/                       Dockerfile multi-stage + nginx + supervisord + entrypoints
docker-compose.yml            Stack completa (app, worker, scheduler, reverb, db, redis)
```

## Fluxo de download

```
[user cola link]
  → POST /downloads               (DownloadController@store: debita créditos)
  → ProcessDownloadJob            (chama getinfo + getlink, registra webhook)
  → [webhook GetStocks OR PollDownloadStatusJob a cada 10s]
  → PollDownloadStatusJob         (recebe itemDCode quando status=1)
  → StreamDownloadFileJob         (stream chunk-by-chunk para storage/app/downloads)
  → DownloadStatusChanged event   (Reverb → UI atualiza)
  → Usuário baixa via URL signed
  → CleanExpiredDownloadsJob      (schedule diário remove arquivos > TTL)
```

Falhas em qualquer etapa estornam créditos automaticamente (`auto_refund_on_failure`).

## Deploy no Coolify

1. **Crie** um resource do tipo *Docker Compose* apontando para este repositório.
2. **Configure os secrets** no painel do Coolify (apenas estes — nada de `.env`):
   - `SERVICE_PASSWORD_POSTGRES`
   - `SERVICE_PASSWORD_REDIS`
   - `SERVICE_PASSWORD_REVERB` (opcional; reutiliza o do Redis se omitido)
   - `APP_KEY` (opcional; gerado e persistido em volume no primeiro boot)
3. **Domain**: vincule o domínio ao serviço `app` (Coolify expõe via `SERVICE_FQDN_APP`).
4. **Deploy**. No primeiro boot:
   - migrations rodam automaticamente
   - você acessa `/install` e configura admin + credenciais GetStocks via wizard
   - todas as outras configs (MercadoPago, Resend, TTL, branding) ficam no painel admin

Não há arquivos `.env` em produção — todos os parâmetros de negócio ficam em DB (criptografados quando sensíveis).

## Comandos úteis

```bash
# Local dev (sem Docker)
composer install
npm install
php artisan migrate --seed
npm run dev
php artisan serve

# Disparar workers no Docker
docker compose up -d
docker compose exec app php artisan horizon:status
docker compose exec app php artisan tinker
```

## Roadmap pós-scaffold

- [ ] Echo + Reverb client integrado nas páginas de download
- [ ] Páginas admin completas (usuários, providers, pacotes, settings, auditoria)
- [ ] Bulk ZIP final (atualmente arquivos baixados separadamente)
- [ ] Re-download grátis (lookup automático por URL+hash)
- [ ] Notificações por email (Resend) em eventos chave
- [ ] Testes (Pest) cobrindo o orchestrator do download
- [ ] Rate-limit por usuário/IP no DownloadController
- [ ] CI no GitHub Actions (lint, types, tests)

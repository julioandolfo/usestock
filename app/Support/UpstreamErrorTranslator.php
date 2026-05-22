<?php

namespace App\Support;

use Throwable;

/**
 * Translates raw upstream provider errors into user-facing messages.
 *
 * The end-user must never see the name of the aggregator we proxy through —
 * exposing it would let them go to the source directly. All visible failure
 * reasons run through this class first.
 *
 * The original (verbose) text is logged via `error` channel by callers so the
 * admin can still see what happened in `getstocks_api_logs` and Laravel logs.
 */
class UpstreamErrorTranslator
{
    private const UPSTREAM_TOKENS = [
        'getstocks.net',
        'GetStocks',
        'getstocks',
        '/api/v1/',
        '/api/auth/',
    ];

    /**
     * Map well-known upstream errors to friendly Portuguese messages.
     *
     * Keys are case-insensitive substrings of the raw error.
     * Values are what the end user actually sees.
     */
    private const PATTERNS = [
        'webhook format is invalid' => 'Falha temporária na comunicação com o fornecedor. Vamos tentar novamente automaticamente.',
        'not support type' => 'Esse tipo de arquivo ainda não está disponível para download. Tente outro link.',
        'support type' => 'Esse tipo de arquivo ainda não está disponível para download. Tente outro link.',
        'insufficient balance' => 'Saldo interno indisponível no momento. Avise o administrador.',
        'balance not enough' => 'Saldo interno indisponível no momento. Avise o administrador.',
        'unauthor' => 'A integração precisa ser reautenticada. Avise o administrador.',
        'token' => 'A integração precisa ser reautenticada. Avise o administrador.',
        'too many request' => 'Muitas requisições em curto período. Tente novamente em alguns minutos.',
        'rate limit' => 'Muitas requisições em curto período. Tente novamente em alguns minutos.',
        'timeout' => 'O fornecedor está demorando para responder. Tente novamente em alguns instantes.',
        'invalid url' => 'O link informado não é suportado. Verifique se ele aponta para um item válido.',
        'invalid link' => 'O link informado não é suportado. Verifique se ele aponta para um item válido.',
        'not found' => 'Item não encontrado. Verifique o link e tente novamente.',
        'maintenance' => 'O fornecedor está em manutenção. Tente novamente mais tarde.',
        'polling timed out' => 'O processamento demorou mais do que o esperado. Tente novamente.',
        'stream failed' => 'Falha ao transferir o arquivo. Tente novamente em alguns instantes.',
    ];

    public function humanize(?string $raw): string
    {
        if (! $raw || trim($raw) === '') {
            return 'Não foi possível processar este download. Tente novamente em alguns instantes.';
        }

        $lower = mb_strtolower($raw);

        foreach (self::PATTERNS as $needle => $friendly) {
            if (str_contains($lower, $needle)) {
                return $friendly;
            }
        }

        // No pattern match — return a generic message instead of leaking the
        // upstream verbiage. The original is preserved server-side via logs.
        return 'Não foi possível processar este download. Tente novamente em alguns instantes.';
    }

    public function fromThrowable(Throwable $e): string
    {
        return $this->humanize($e->getMessage());
    }

    /**
     * Defensive last-mile scrub: strip any token that could reveal the
     * upstream provider, in case a raw message slips through somewhere.
     */
    public function scrub(string $message): string
    {
        foreach (self::UPSTREAM_TOKENS as $token) {
            $message = str_ireplace($token, 'fornecedor', $message);
        }

        return $message;
    }
}

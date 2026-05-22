<?php

namespace App\Services\GetStocks;

use App\Models\DownloadRequest;
use App\Models\GetstocksApiLog;
use App\Settings\GetStocksSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetStocksClient
{
    public function __construct(private readonly GetStocksSettings $settings) {}

    /**
     * Authenticate using email + password and return a fresh access token.
     *
     * Per the API docs, the token has no default expiration but re-login invalidates the previous one.
     */
    public function login(string $email, string $password): string
    {
        $response = $this->http()
            ->asForm()
            ->post('/api/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

        $payload = $this->decode($response);
        $this->log('/api/auth/login', 'POST', $response, ['email' => $email]);

        if ($response->failed() || ($payload['status'] ?? null) !== 200) {
            throw GetStocksException::fromResponse('login', $response->status(), $payload);
        }

        $token = $payload['result']['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new GetStocksException('GetStocks login: missing access_token in response.', $response->status(), $payload);
        }

        return $token;
    }

    /**
     * Re-authenticate using stored credentials and persist the new token in settings.
     */
    public function refreshToken(): string
    {
        if (empty($this->settings->email) || empty($this->settings->password)) {
            throw new GetStocksException('GetStocks credentials are not configured.');
        }

        $token = $this->login($this->settings->email, $this->settings->password);
        $this->settings->token = $token;
        $this->settings->save();

        return $token;
    }

    public function profile(): array
    {
        return $this->call('GET', '/api/auth/profile');
    }

    public function providers(): array
    {
        return $this->call('GET', '/api/v1/providers');
    }

    public function balance(): array
    {
        $response = $this->authedHttp()->get('/api/v1/balance');
        $payload = $this->decode($response);
        $this->log('/api/v1/balance', 'GET', $response);

        if ($response->failed()) {
            // Try a single re-auth on 401 then retry once.
            if ($response->status() === 401) {
                $this->refreshToken();
                $response = $this->authedHttp()->get('/api/v1/balance');
                $payload = $this->decode($response);
                $this->log('/api/v1/balance', 'GET', $response, ['retry' => true]);
            }

            if ($response->failed()) {
                throw GetStocksException::fromResponse('balance', $response->status(), $payload);
            }
        }

        // Balance endpoint returns the values at the top level, not nested under "result".
        return $payload ?? [];
    }

    public function orders(): array
    {
        return $this->call('GET', '/api/v1/orders');
    }

    /**
     * Resolve info about a stock link before issuing a download.
     * Returns the "result.support" map with provider slug + item id + supported types.
     */
    public function getInfo(string $link, bool $isPremium, ?DownloadRequest $downloadRequest = null): array
    {
        return $this->call('POST', '/api/v1/getinfo', [
            'link' => $link,
            'ispre' => $isPremium ? 1 : 0,
        ], $downloadRequest);
    }

    /**
     * Trigger the download generation. With $webhookUrl set, GetStocks will call us when ready;
     * otherwise we have to poll downloadStatus().
     */
    public function getLink(
        string $link,
        bool $isPremium,
        ?string $type = null,
        ?string $webhookUrl = null,
        ?DownloadRequest $downloadRequest = null,
    ): array {
        $payload = [
            'link' => $link,
            'ispre' => $isPremium ? 1 : 0,
        ];
        if ($type !== null && $type !== '') {
            $payload['type'] = $type;
        }
        if ($webhookUrl !== null && $webhookUrl !== '') {
            $payload['webhook'] = $webhookUrl;
        }

        return $this->call('POST', '/api/v1/getlink', $payload, $downloadRequest);
    }

    /**
     * Poll for the final download URL. status=1 means "ready to download".
     */
    public function downloadStatus(
        string $slug,
        string $itemId,
        bool $isPremium,
        string $type,
        ?DownloadRequest $downloadRequest = null,
    ): array {
        return $this->call('POST', '/api/v1/download-status', [
            'slug' => $slug,
            'id' => $itemId,
            'ispre' => $isPremium ? 1 : 0,
            'type' => $type,
        ], $downloadRequest);
    }

    /**
     * Build the absolute URL of the final file. Token is appended as a query string.
     * NEVER expose this URL to end-users — proxy/stream the body server-side.
     */
    public function downloadUrl(string $itemDCode): string
    {
        $token = $this->ensureToken();

        return rtrim($this->settings->base_url, '/')
            .'/api/v1/download/'
            .urlencode($itemDCode)
            .'?token='.urlencode($token);
    }

    /**
     * Open a streamed HTTP response for downloading the final file, so callers
     * can write it to disk chunk-by-chunk without buffering the full body in memory.
     */
    public function streamDownload(string $itemDCode): Response
    {
        return $this->http()
            ->withOptions(['stream' => true])
            ->timeout(0)
            ->connectTimeout(15)
            ->withHeaders($this->authHeaders())
            ->get('/api/v1/download/'.urlencode($itemDCode), [
                'token' => $this->ensureToken(),
            ]);
    }

    // ----------------------------------------------------------------
    // Internals
    // ----------------------------------------------------------------

    /**
     * Generic API call with auto re-auth on 401 and JSON response unwrapping.
     */
    private function call(
        string $method,
        string $endpoint,
        array $payload = [],
        ?DownloadRequest $downloadRequest = null,
    ): array {
        $response = $this->sendRequest($method, $endpoint, $payload);
        $body = $this->decode($response);

        if ($response->status() === 401) {
            $this->refreshToken();
            $response = $this->sendRequest($method, $endpoint, $payload);
            $body = $this->decode($response);
        }

        $this->log($endpoint, $method, $response, $payload, $downloadRequest);

        if ($response->failed() || (isset($body['status']) && $body['status'] >= 400)) {
            throw GetStocksException::fromResponse($endpoint, $response->status(), $body);
        }

        return $body['result'] ?? $body ?? [];
    }

    private function sendRequest(string $method, string $endpoint, array $payload): Response
    {
        $request = $this->authedHttp();

        return match (strtoupper($method)) {
            'GET' => $request->get($endpoint, $payload),
            'POST' => $request->asForm()->post($endpoint, $payload),
            default => throw new GetStocksException("Unsupported method: {$method}"),
        };
    }

    private function http(): PendingRequest
    {
        $timeout = max(5, (int) ($this->settings->request_timeout_seconds ?: 30));

        return Http::baseUrl(rtrim($this->settings->base_url, '/'))
            ->timeout($timeout)
            ->connectTimeout(15)
            ->acceptJson()
            ->withUserAgent('UseStock/1.0 (+laravel)')
            ->retry(2, 500, throw: false);
    }

    private function authedHttp(): PendingRequest
    {
        $token = $this->ensureToken();

        return $this->http()
            ->withHeaders($this->authHeaders())
            ->withQueryParameters(['token' => $token]);
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->ensureToken(),
        ];
    }

    private function ensureToken(): string
    {
        if (! empty($this->settings->token)) {
            return $this->settings->token;
        }

        return $this->refreshToken();
    }

    private function decode(Response $response): ?array
    {
        try {
            return $response->json();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function log(
        string $endpoint,
        string $method,
        Response $response,
        array $payload = [],
        ?DownloadRequest $downloadRequest = null,
    ): void {
        try {
            GetstocksApiLog::create([
                'download_request_id' => $downloadRequest?->id,
                'endpoint' => $endpoint,
                'method' => $method,
                'response_status' => $response->status(),
                'request_payload' => $this->scrub($payload),
                'response_payload' => $this->decode($response),
                'duration_ms' => (int) ($response->transferStats?->getTransferTime() * 1000),
                'error' => $response->failed() ? $response->reason() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('GetstocksApiLog persist failed: '.$e->getMessage());
        }
    }

    /**
     * Strip credentials before persisting to the api log table.
     */
    private function scrub(array $payload): array
    {
        foreach (['password', 'token'] as $sensitive) {
            if (isset($payload[$sensitive])) {
                $payload[$sensitive] = '***redacted***';
            }
        }

        return $payload;
    }
}

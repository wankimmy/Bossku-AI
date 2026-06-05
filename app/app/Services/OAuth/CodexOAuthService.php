<?php

namespace App\Services\OAuth;

use App\Models\BosskuAi\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CodexOAuthService
{
    protected const CACHE_PREFIX = 'bossku_codex_oauth:';

    public function clientId(): string
    {
        $fromConfig = config('bossku_oauth.codex.client_id');
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        $fromEnv = env('CODEX_OAUTH_CLIENT_ID');

        return is_string($fromEnv) && $fromEnv !== ''
            ? $fromEnv
            : 'app_EMoamEEZ73f0CkXaXp7hrann';
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '';
    }

    /** @return array{connected: bool, expires_at: string|null, last_refresh: string|null, account_hint: string|null, auth_mode: string|null} */
    public function status(): array
    {
        $auth = $this->loadAuth();
        if ($auth === null) {
            return [
                'connected' => false,
                'expires_at' => null,
                'last_refresh' => null,
                'account_hint' => null,
                'auth_mode' => null,
            ];
        }

        $expiresAt = isset($auth['expires_at']) ? (int) $auth['expires_at'] : null;

        return [
            'connected' => $this->hasValidAccessToken($auth),
            'expires_at' => $expiresAt !== null ? date('c', $expiresAt) : null,
            'last_refresh' => isset($auth['last_refresh']) ? (string) $auth['last_refresh'] : null,
            'account_hint' => $auth['account_hint'] ?? null,
            'auth_mode' => $auth['auth_mode'] ?? 'chatgpt',
        ];
    }

    public function isConnected(): bool
    {
        return $this->status()['connected'];
    }

    /** @return array{url: string, state: string} */
    public function beginAuthorization(): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Codex OAuth is not configured. Set CODEX_OAUTH_CLIENT_ID in the environment.');
        }

        $verifier = $this->base64Url(random_bytes(32));
        $challenge = $this->base64Url(hash('sha256', $verifier, true));
        $state = Str::random(40);

        Cache::put(self::CACHE_PREFIX.$state, [
            'code_verifier' => $verifier,
            'created_at' => time(),
        ], now()->addMinutes(10));

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => (string) config('bossku_oauth.codex.scopes'),
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
            'id_token_add_organizations' => 'true',
            'codex_cli_simplified_flow' => 'true',
        ]);

        $authorizeUrl = rtrim((string) config('bossku_oauth.codex.authorize_url'), '?');

        return [
            'url' => $authorizeUrl.'?'.$params,
            'state' => $state,
        ];
    }

    public function handleCallback(string $code, string $state): void
    {
        $pending = Cache::pull(self::CACHE_PREFIX.$state);
        if (! is_array($pending) || empty($pending['code_verifier'])) {
            throw new \RuntimeException('Invalid or expired OAuth state.');
        }

        $response = Http::asForm()
            ->timeout(30)
            ->post((string) config('bossku_oauth.codex.token_url'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'client_id' => $this->clientId(),
                'code_verifier' => $pending['code_verifier'],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Codex token exchange failed: '.$response->body());
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $this->persistFromTokenResponse($data);
    }

    public function disconnect(): void
    {
        Setting::setValue('codex_auth_encrypted', null);
    }

    public function getAccessToken(): string
    {
        $auth = $this->loadAuth();
        if ($auth === null) {
            throw new \RuntimeException('Codex is not connected. Connect with ChatGPT in Settings → Models.');
        }

        if ($this->shouldRefresh($auth)) {
            $auth = $this->refreshTokens($auth);
        }

        $token = (string) ($auth['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Codex access token is missing. Reconnect with ChatGPT.');
        }

        return $token;
    }

    /** @param array<string, mixed> $auth */
    protected function shouldRefresh(array $auth): bool
    {
        $expiresAt = (int) ($auth['expires_at'] ?? 0);
        if ($expiresAt === 0) {
            return false;
        }

        return time() >= ($expiresAt - 300);
    }

    /**
     * @param  array<string, mixed>  $auth
     * @return array<string, mixed>
     */
    protected function refreshTokens(array $auth): array
    {
        $refresh = (string) ($auth['refresh_token'] ?? '');
        if ($refresh === '') {
            return $auth;
        }

        $response = Http::asForm()
            ->timeout(30)
            ->post((string) config('bossku_oauth.codex.token_url'), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
                'client_id' => $this->clientId(),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Codex token refresh failed. Reconnect with ChatGPT.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $this->persistFromTokenResponse($data, $auth);

        return $this->loadAuth() ?? $auth;
    }

    /** @param array<string, mixed> $data */
    protected function persistFromTokenResponse(array $data, ?array $previous = null): void
    {
        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $access = (string) ($data['access_token'] ?? '');
        $refresh = (string) ($data['refresh_token'] ?? ($previous['refresh_token'] ?? ''));

        $accountHint = null;
        if (isset($data['id_token']) && is_string($data['id_token'])) {
            $accountHint = $this->extractEmailFromJwt($data['id_token']);
        }

        $payload = [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_at' => time() + max(60, $expiresIn),
            'last_refresh' => now()->toIso8601String(),
            'auth_mode' => 'chatgpt',
            'account_hint' => $accountHint ?? ($previous['account_hint'] ?? null),
        ];

        Setting::setValue('codex_auth_encrypted', Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    /** @return array<string, mixed>|null */
    protected function loadAuth(): ?array
    {
        $encrypted = Setting::getValue('codex_auth_encrypted');
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        try {
            $json = Crypt::decryptString($encrypted);
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $auth */
    protected function hasValidAccessToken(array $auth): bool
    {
        $token = (string) ($auth['access_token'] ?? '');
        if ($token === '') {
            return false;
        }

        $expiresAt = (int) ($auth['expires_at'] ?? 0);

        return $expiresAt === 0 || time() < $expiresAt;
    }

    protected function redirectUri(): string
    {
        return (string) config('bossku_oauth.codex.redirect_uri');
    }

    protected function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    protected function extractEmailFromJwt(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        try {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                return null;
            }

            return is_string($payload['email'] ?? null) ? $payload['email'] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

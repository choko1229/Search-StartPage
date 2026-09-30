<?php
declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Http\HttpException;

final class DiscordOAuth
{
    private const AUTHORIZE = 'https://discord.com/oauth2/authorize';
    private const TOKEN = 'https://discord.com/api/oauth2/token';
    private const USER = 'https://discord.com/api/v10/users/@me';

    public function __construct(private readonly Config $config)
    {
    }

    public function configured(): bool
    {
        return preg_match('/^[0-9]{17,20}$/D', $this->config->get('discord.client_id', '')) === 1
            && $this->config->get('discord.client_secret', '') !== '';
    }

    public function callbackUrl(bool $api = false): string
    {
        return rtrim($this->config->get('site.url'), '/') . ($api ? '/api/auth/discord/callback' : '/auth/discord/callback');
    }

    public function authorizationUrl(string $state, bool $api = false): string
    {
        if (!$this->configured()) {
            throw new HttpException(503, 'OAUTH_NOT_CONFIGURED');
        }
        return self::AUTHORIZE . '?' . http_build_query([
            'client_id' => $this->config->get('discord.client_id'),
            'response_type' => 'code', 'scope' => 'identify',
            'redirect_uri' => $this->callbackUrl($api), 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function identify(string $code, bool $api = false): array
    {
        if (!$this->configured() || $code === '' || strlen($code) > 2048) {
            throw new HttpException(400, 'OAUTH_FAILED');
        }
        $token = $this->request(self::TOKEN, [
            'client_id' => $this->config->get('discord.client_id'),
            'client_secret' => $this->config->get('discord.client_secret'),
            'grant_type' => 'authorization_code', 'code' => $code,
            'redirect_uri' => $this->callbackUrl($api),
        ]);
        $accessToken = self::validateAccessToken($token);
        // Tokens are used only for this identity lookup and are never persisted.
        return self::validateIdentity($this->request(self::USER, null, $accessToken));
    }

    public static function validateAccessToken(array $token): string
    {
        if (!is_string($token['access_token'] ?? null)
            || !preg_match('/^[\x21-\x7E]{1,4096}$/D', $token['access_token'])
            || !is_string($token['token_type'] ?? null)
            || strcasecmp($token['token_type'], 'Bearer') !== 0) {
            throw new HttpException(502, 'OAUTH_FAILED');
        }
        return $token['access_token'];
    }

    public static function validateIdentity(array $user): array
    {
        if (!is_string($user['id'] ?? null) || !preg_match('/^[0-9]{17,20}$/D', $user['id'])
            || !is_string($user['username'] ?? null) || $user['username'] === '') {
            throw new HttpException(502, 'OAUTH_FAILED');
        }
        return [
            'id' => $user['id'], 'username' => mb_substr($user['username'], 0, 100),
            'display_name' => is_string($user['global_name'] ?? null) ? mb_substr($user['global_name'], 0, 100) : null,
            'avatar' => is_string($user['avatar'] ?? null) && preg_match('/^(a_)?[a-f0-9]{32}$/D', $user['avatar']) ? $user['avatar'] : null,
        ];
    }

    private function request(string $url, ?array $form = null, ?string $token = null): array
    {
        $curl = curl_init($url);
        $body = '';
        $headers = ['Accept: application/json'];
        if ($token !== null) { $headers[] = 'Authorization: Bearer ' . $token; }
        curl_setopt_array($curl, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 65536) { return 0; }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($form !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
        }
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($ok === false || $status !== 200) { throw new HttpException(502, 'OAUTH_FAILED'); }
        try { $result = json_decode($body, true, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new HttpException(502, 'OAUTH_FAILED'); }
        if (!is_array($result)) { throw new HttpException(502, 'OAUTH_FAILED'); }
        return $result;
    }
}

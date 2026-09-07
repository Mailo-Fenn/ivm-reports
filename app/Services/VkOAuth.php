<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

// авторизация администратора через VK ID (OAuth 2.1 + PKCE):
// access token живёт ~1 час и обновляется по refresh token (живёт 180 дней)
class VkOAuth
{
    private const AUTH_URL = 'https://id.vk.ru/authorize';
    private const TOKEN_URL = 'https://id.vk.ru/oauth2/auth';

    // stats — статистика сообществ, wall — посты, groups — данные сообществ
    public const SCOPE = 'stats wall groups';

    public static function redirectUri(): string
    {
        return url('/vk/callback');
    }

    public static function authUrl(string $state, string $verifier): ?string
    {
        $clientId = Setting::get('vkid_client_id');
        if (!$clientId) {
            return null;
        }

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return self::AUTH_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 's256',
            'scope' => self::SCOPE,
        ]);
    }

    public static function exchangeCode(string $code, string $verifier, string $deviceId, string $state): void
    {
        $r = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $verifier,
            'client_id' => Setting::get('vkid_client_id'),
            'device_id' => $deviceId,
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
        ])->json();

        if (!isset($r['access_token'])) {
            throw new VkApiException($r['error_description'] ?? $r['error'] ?? 'VK ID не вернул токен');
        }

        self::storeTokens($r, $deviceId);
    }

    // валидный access token; протухший прозрачно обновляем
    public static function validAccessToken(): ?string
    {
        $access = Setting::get('vkid_access_token');
        if (!$access) {
            return null;
        }

        if ((int) Setting::get('vkid_expires_at') > time() + 60) {
            return $access;
        }

        return self::refresh();
    }

    public static function refresh(): ?string
    {
        $refresh = Setting::get('vkid_refresh_token');
        $deviceId = Setting::get('vkid_device_id');
        if (!$refresh || !$deviceId) {
            return null;
        }

        $r = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id' => Setting::get('vkid_client_id'),
            'device_id' => $deviceId,
            'state' => bin2hex(random_bytes(16)),
        ])->json();

        // refresh token тоже истёк (180 дней) — нужно переподключение в настройках
        if (!isset($r['access_token'])) {
            return null;
        }

        self::storeTokens($r, $deviceId);

        return $r['access_token'];
    }

    private static function storeTokens(array $r, string $deviceId): void
    {
        Setting::set('vkid_access_token', $r['access_token']);
        // VK ID ротирует refresh token при каждом обновлении
        if (!empty($r['refresh_token'])) {
            Setting::set('vkid_refresh_token', $r['refresh_token']);
        }
        Setting::set('vkid_device_id', $deviceId);
        Setting::set('vkid_expires_at', (string) (time() + (int) ($r['expires_in'] ?? 3600)));
        if (!empty($r['user_id'])) {
            Setting::set('vkid_user_id', (string) $r['user_id']);
        }
    }

    public static function connected(): bool
    {
        return (bool) Setting::get('vkid_access_token');
    }

    public static function disconnect(): void
    {
        foreach (['vkid_access_token', 'vkid_refresh_token', 'vkid_device_id', 'vkid_expires_at', 'vkid_user_id'] as $k) {
            Setting::set($k, null);
        }
    }
}

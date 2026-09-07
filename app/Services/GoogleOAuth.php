<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

// авторизация аккаунта агентства в Google (OAuth 2.0, offline access):
// access token живёт ~1 час и обновляется по refresh token, который не истекает,
// пока приложение в Google Cloud опубликовано (в статусе Testing — 7 дней)
class GoogleOAuth
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    // youtube.readonly — данные каналов и видео, yt-analytics.readonly — статистика по дням
    public const SCOPE = 'https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/yt-analytics.readonly';

    public static function redirectUri(): string
    {
        return url('/google/callback');
    }

    public static function configured(): bool
    {
        return (bool) (Setting::get('google_client_id') && Setting::get('google_client_secret'));
    }

    public static function authUrl(string $state): ?string
    {
        if (!self::configured()) {
            return null;
        }

        return self::AUTH_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => Setting::get('google_client_id'),
            'redirect_uri' => self::redirectUri(),
            'scope' => self::SCOPE,
            'state' => $state,
            // offline + consent — иначе Google не выдаёт refresh token при повторном подключении
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ]);
    }

    public static function exchangeCode(string $code): void
    {
        $r = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => Setting::get('google_client_id'),
            'client_secret' => Setting::get('google_client_secret'),
            'redirect_uri' => self::redirectUri(),
        ])->json();

        if (!isset($r['access_token'])) {
            throw new YouTubeApiException($r['error_description'] ?? $r['error'] ?? 'Google не вернул токен');
        }

        self::storeTokens($r);

        // название канала подключённого аккаунта — только для отображения в настройках
        try {
            $mine = (new YouTubeApi($r['access_token']))->data('channels', ['part' => 'snippet', 'mine' => 'true']);
            Setting::set('google_account', $mine['items'][0]['snippet']['title'] ?? null);
        } catch (\Throwable) {
            Setting::set('google_account', null);
        }
    }

    // валидный access token; протухший прозрачно обновляем
    public static function validAccessToken(): ?string
    {
        $access = Setting::get('google_access_token');
        if (!$access) {
            return null;
        }

        if ((int) Setting::get('google_expires_at') > time() + 60) {
            return $access;
        }

        return self::refresh();
    }

    public static function refresh(): ?string
    {
        $refresh = Setting::get('google_refresh_token');
        if (!$refresh || !self::configured()) {
            return null;
        }

        $r = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id' => Setting::get('google_client_id'),
            'client_secret' => Setting::get('google_client_secret'),
        ])->json();

        // refresh token отозван или истёк — нужно переподключение в настройках
        if (!isset($r['access_token'])) {
            return null;
        }

        self::storeTokens($r);

        return $r['access_token'];
    }

    private static function storeTokens(array $r): void
    {
        Setting::set('google_access_token', $r['access_token']);
        // при обновлении Google refresh token не присылает — сохраняем только новый
        if (!empty($r['refresh_token'])) {
            Setting::set('google_refresh_token', $r['refresh_token']);
        }
        Setting::set('google_expires_at', (string) (time() + (int) ($r['expires_in'] ?? 3600)));
    }

    public static function connected(): bool
    {
        return (bool) Setting::get('google_refresh_token');
    }

    public static function disconnect(): void
    {
        foreach (['google_access_token', 'google_refresh_token', 'google_expires_at', 'google_account'] as $k) {
            Setting::set($k, null);
        }
    }
}

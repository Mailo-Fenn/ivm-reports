<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

// Подключение Instagram через «Instagram API with Instagram Login» (Meta).
// Токен выдаётся конкретному аккаунту Instagram, поэтому хранится у проекта:
// короткий (1 час) сразу меняем на длинный (60 дней), длинный продлеваем перед истечением.
class InstagramOAuth
{
    private const AUTH_URL = 'https://www.instagram.com/oauth/authorize';
    private const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    private const GRAPH = 'https://graph.instagram.com/';

    // basic — профиль и публикации, manage_insights — статистика
    public const SCOPE = 'instagram_business_basic,instagram_business_manage_insights';

    public static function redirectUri(): string
    {
        return url('/instagram/callback');
    }

    public static function configured(): bool
    {
        return (bool) (Setting::get('instagram_app_id') && Setting::get('instagram_app_secret'));
    }

    public static function authUrl(string $state): ?string
    {
        if (!self::configured()) {
            return null;
        }

        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => Setting::get('instagram_app_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            // всегда спрашивать логин: у агентства несколько клиентских аккаунтов в одном браузере
            'force_authentication' => '1',
        ]);
    }

    // обменивает code на длинный токен и сохраняет его вместе с данными аккаунта в проект
    public static function connect(Project $project, string $code): void
    {
        // Meta иногда добавляет к code хвост «#_»
        $code = preg_replace('~#_$~', '', $code);

        $short = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'client_id' => Setting::get('instagram_app_id'),
            'client_secret' => Setting::get('instagram_app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => self::redirectUri(),
            'code' => $code,
        ])->json();

        if (!isset($short['access_token'])) {
            throw new InstagramApiException($short['error_message'] ?? $short['error']['message'] ?? 'Instagram не вернул токен');
        }

        $long = Http::timeout(20)->get(self::GRAPH.'access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => Setting::get('instagram_app_secret'),
            'access_token' => $short['access_token'],
        ])->json();

        if (!isset($long['access_token'])) {
            throw new InstagramApiException($long['error']['message'] ?? 'Instagram не выдал долгосрочный токен');
        }

        $me = (new InstagramApi($long['access_token']))->me();

        $project->update([
            'instagram_token' => $long['access_token'],
            'instagram_token_expires_at' => now()->addSeconds((int) ($long['expires_in'] ?? 5184000)),
            'instagram_user_id' => (string) ($me['user_id'] ?? $short['user_id'] ?? ''),
            'instagram_username' => $me['username'] ?? null,
        ]);
    }

    // действующий токен проекта; если до истечения меньше недели — продлеваем.
    // null — токен истёк или отозван, нужно переподключение
    public static function validToken(Project $project): ?string
    {
        $token = $project->instagram_token;
        if (!$token) {
            return null;
        }

        $expires = $project->instagram_token_expires_at;
        if ($expires && $expires->isPast()) {
            return null;
        }

        if (!$expires || $expires->lt(now()->addDays(7))) {
            $r = Http::timeout(20)->get(self::GRAPH.'refresh_access_token', [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $token,
            ])->json();

            if (isset($r['access_token'])) {
                $project->update([
                    'instagram_token' => $r['access_token'],
                    'instagram_token_expires_at' => now()->addSeconds((int) ($r['expires_in'] ?? 5184000)),
                ]);

                return $r['access_token'];
            }
            // продлить не удалось, но старый токен ещё жив — работаем на нём
        }

        return $token;
    }

    public static function disconnect(Project $project): void
    {
        $project->update([
            'instagram_token' => null,
            'instagram_token_expires_at' => null,
            'instagram_user_id' => null,
            'instagram_username' => null,
        ]);
    }
}

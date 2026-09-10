<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\GoogleOAuth;
use App\Services\InstagramOAuth;
use App\Services\TelegramStats;
use App\Services\VkApi;
use App\Services\VkApiException;
use App\Services\VkOAuth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $token = Setting::get('vk_token');

        return Inertia::render('Settings/Index', [
            'vk' => [
                'has_token' => (bool) $token,
                'token_tail' => $token ? substr($token, -4) : null,
            ],
            'vkid' => [
                'client_id' => Setting::get('vkid_client_id'),
                'connected' => VkOAuth::connected(),
                'user_id' => Setting::get('vkid_user_id'),
                'redirect_uri' => VkOAuth::redirectUri(),
            ],
            'google' => [
                'client_id' => Setting::get('google_client_id'),
                'has_secret' => (bool) Setting::get('google_client_secret'),
                'connected' => GoogleOAuth::connected(),
                'account' => Setting::get('google_account'),
                'redirect_uri' => GoogleOAuth::redirectUri(),
            ],
            'instagram' => [
                'app_id' => Setting::get('instagram_app_id'),
                'has_secret' => (bool) Setting::get('instagram_app_secret'),
                'redirect_uri' => InstagramOAuth::redirectUri(),
            ],
            'telegram' => TelegramAuthController::state($request),
        ]);
    }

    public function telegram(Request $request)
    {
        $data = $request->validate([
            'api_id' => 'required|digits_between:1,12',
            'api_hash' => 'nullable|string|max:64',
        ]);

        Setting::set('telegram_api_id', $data['api_id']);
        // hash обратно не показываем, поэтому пустое поле означает «оставить прежний»
        if (trim($data['api_hash'] ?? '') !== '') {
            Setting::set('telegram_api_hash', trim($data['api_hash']));
        }

        if (!TelegramStats::configured()) {
            return back()->with('error', 'Укажите api_hash приложения Telegram');
        }

        return back()->with('success', 'Данные приложения Telegram сохранены — теперь войдите в аккаунт агентства');
    }

    public function instagram(Request $request)
    {
        $data = $request->validate([
            'app_id' => 'required|string|max:255',
            'app_secret' => 'nullable|string|max:255',
        ]);

        Setting::set('instagram_app_id', trim($data['app_id']));
        // секрет обратно не показываем, поэтому пустое поле означает «оставить прежний»
        if (trim($data['app_secret'] ?? '') !== '') {
            Setting::set('instagram_app_secret', trim($data['app_secret']));
        }

        if (!Setting::get('instagram_app_secret')) {
            return back()->with('error', 'Укажите App Secret приложения Meta');
        }

        return back()->with('success', 'Данные приложения Meta сохранены — теперь подключайте Instagram в настройках проектов');
    }

    public function google(Request $request)
    {
        if ($request->boolean('disconnect')) {
            GoogleOAuth::disconnect();

            return back()->with('success', 'Google отключён — токены удалены');
        }

        $data = $request->validate([
            'client_id' => 'required|string|max:255',
            'client_secret' => 'nullable|string|max:255',
        ]);

        Setting::set('google_client_id', trim($data['client_id']));
        // секрет обратно не показываем, поэтому пустое поле означает «оставить прежний»
        if (trim($data['client_secret'] ?? '') !== '') {
            Setting::set('google_client_secret', trim($data['client_secret']));
        }

        if (!Setting::get('google_client_secret')) {
            return back()->with('error', 'Укажите Client Secret приложения Google');
        }

        return back()->with('success', 'Данные приложения Google сохранены — теперь нажмите «Подключить Google»');
    }

    public function vkid(Request $request)
    {
        if ($request->boolean('disconnect')) {
            VkOAuth::disconnect();

            return back()->with('success', 'VK отключён — токены удалены');
        }

        $data = $request->validate(['client_id' => 'required|string|max:64']);
        Setting::set('vkid_client_id', trim($data['client_id']));

        return back()->with('success', 'ID приложения сохранён — теперь нажмите «Подключить VK»');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['vk_token' => 'nullable|string|max:1024']);
        $token = trim($data['vk_token'] ?? '');

        if ($token === '') {
            Setting::set('vk_token', null);

            return back()->with('success', 'Токен ВК удалён');
        }

        try {
            (new VkApi($token))->call('users.get');
        } catch (VkApiException $e) {
            if (str_contains($e->getMessage(), 'service token')) {
                return back()->with('error', 'Это сервисный ключ приложения — статистика сообществ ему недоступна. Нужен токен аккаунта-администратора, либо укажите ключ доступа сообщества в настройках проекта.');
            }
            // 27 — ключ сообщества: users.get недоступен, но для статистики он подходит
            if ($e->getCode() !== 27) {
                return back()->with('error', 'Токен не прошёл проверку: ' . $e->getMessage());
            }
        } catch (\Throwable) {
            return back()->with('error', 'Не удалось связаться с VK API — проверьте соединение');
        }

        Setting::set('vk_token', $token);

        return back()->with('success', 'Токен ВК сохранён и проверен');
    }
}

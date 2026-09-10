<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\TelegramException;
use App\Services\TelegramStats;
use Illuminate\Http\Request;

// вход аккаунта агентства в Telegram со страницы настроек: телефон → код → облачный пароль
class TelegramAuthController extends Controller
{
    private const SESSION = 'telegram_login';

    public function start(Request $request, TelegramStats $tg)
    {
        $data = $request->validate(['phone' => 'required|string|max:32']);
        $phone = preg_replace('~[^\d+]~', '', $data['phone']);

        try {
            $hash = $tg->loginStart($phone);
        } catch (TelegramException $e) {
            return back()->with('error', 'Telegram: '.$e->getMessage());
        }

        $request->session()->put(self::SESSION, ['phone' => $phone, 'hash' => $hash, 'step' => 'code']);

        return back()->with('success', 'Код отправлен в Telegram на номер '.$phone);
    }

    public function code(Request $request, TelegramStats $tg)
    {
        $data = $request->validate(['code' => 'required|string|max:16']);
        $pending = $request->session()->get(self::SESSION);
        if (!$pending || ($pending['step'] ?? '') !== 'code') {
            return back()->with('error', 'Сначала запросите код');
        }

        try {
            $r = $tg->loginCode($pending['phone'], $pending['hash'], preg_replace('~\D~', '', $data['code']));
        } catch (TelegramException $e) {
            return back()->with('error', 'Telegram: '.$e->getMessage());
        }

        if (!empty($r['need_password'])) {
            $request->session()->put(self::SESSION, $pending + ['step' => 'password']);
            $request->session()->put(self::SESSION.'.step', 'password');

            return back()->with('success', 'Код принят — введите облачный пароль (двухэтапная аутентификация)');
        }

        return $this->finish($request, $r['user'] ?? []);
    }

    public function password(Request $request, TelegramStats $tg)
    {
        $data = $request->validate(['password' => 'required|string|max:255']);
        $pending = $request->session()->get(self::SESSION);
        if (!$pending || ($pending['step'] ?? '') !== 'password') {
            return back()->with('error', 'Сначала введите код из Telegram');
        }

        try {
            $r = $tg->loginPassword($data['password']);
        } catch (TelegramException $e) {
            return back()->with('error', 'Telegram: '.$e->getMessage());
        }

        return $this->finish($request, $r['user'] ?? []);
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(self::SESSION);

        return back();
    }

    public function logout(Request $request, TelegramStats $tg)
    {
        try {
            $tg->logout();
        } catch (TelegramException) {
            // сессия уже недействительна — просто забываем её
        }
        Setting::set('telegram_user', null);
        $request->session()->forget(self::SESSION);

        return back()->with('success', 'Аккаунт Telegram отключён');
    }

    private function finish(Request $request, array $user)
    {
        $label = $user['username'] ? '@'.$user['username'] : ($user['name'] ?: ($user['phone'] ?? 'аккаунт'));
        Setting::set('telegram_user', $label);
        $request->session()->forget(self::SESSION);

        return back()->with('success', "Telegram подключён: {$label}");
    }

    // состояние для страницы настроек
    public static function state(Request $request): array
    {
        $pending = $request->session()->get(self::SESSION);

        return [
            'api_id' => Setting::get('telegram_api_id'),
            'has_hash' => (bool) Setting::get('telegram_api_hash'),
            'configured' => TelegramStats::configured(),
            'connected' => TelegramStats::connected(),
            'user' => Setting::get('telegram_user'),
            'step' => $pending['step'] ?? null,
            'phone' => $pending['phone'] ?? null,
        ];
    }
}

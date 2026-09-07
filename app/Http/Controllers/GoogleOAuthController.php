<?php

namespace App\Http\Controllers;

use App\Services\GoogleOAuth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoogleOAuthController extends Controller
{
    public function connect(Request $request)
    {
        $state = bin2hex(random_bytes(16));

        $url = GoogleOAuth::authUrl($state);
        if (!$url) {
            return redirect()->route('settings.index')->with('error', 'Сначала сохраните Client ID и Client Secret приложения Google');
        }

        $request->session()->put('google_state', $state);

        return Inertia::location($url);
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect()->route('settings.index')
                ->with('error', 'Google: '.($request->input('error_description') ?: $request->input('error')));
        }

        $state = $request->session()->pull('google_state');

        if (!$state || $state !== $request->input('state')) {
            return redirect()->route('settings.index')->with('error', 'Сессия авторизации устарела — нажмите «Подключить Google» ещё раз');
        }

        try {
            GoogleOAuth::exchangeCode($request->input('code', ''));
        } catch (\Throwable $e) {
            return redirect()->route('settings.index')->with('error', 'Не удалось получить токен Google: '.$e->getMessage());
        }

        return redirect()->route('settings.index')->with('success', 'Google подключён — токен будет обновляться автоматически');
    }
}

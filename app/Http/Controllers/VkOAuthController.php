<?php

namespace App\Http\Controllers;

use App\Services\VkOAuth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VkOAuthController extends Controller
{
    public function connect(Request $request)
    {
        $state = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        $url = VkOAuth::authUrl($state, $verifier);
        if (!$url) {
            return redirect()->route('settings.index')->with('error', 'Сначала сохраните ID приложения VK ID');
        }

        $request->session()->put('vkid_state', $state);
        $request->session()->put('vkid_verifier', $verifier);

        return Inertia::location($url);
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect()->route('settings.index')
                ->with('error', 'VK ID: '.($request->input('error_description') ?: $request->input('error')));
        }

        $state = $request->session()->pull('vkid_state');
        $verifier = $request->session()->pull('vkid_verifier');

        if (!$state || !$verifier || $state !== $request->input('state')) {
            return redirect()->route('settings.index')->with('error', 'Сессия авторизации устарела — нажмите «Подключить VK» ещё раз');
        }

        try {
            VkOAuth::exchangeCode(
                $request->input('code', ''),
                $verifier,
                $request->input('device_id', ''),
                $state
            );
        } catch (\Throwable $e) {
            return redirect()->route('settings.index')->with('error', 'Не удалось получить токен VK ID: '.$e->getMessage());
        }

        return redirect()->route('settings.index')->with('success', 'VK подключён — токен будет обновляться автоматически');
    }
}

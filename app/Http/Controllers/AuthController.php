<?php

namespace App\Http\Controllers;

use App\Http\Middleware\PortalAuth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function show(Request $request)
    {
        if ($request->session()->get(PortalAuth::SESSION_KEY) === true) {
            return redirect()->route('projects.index');
        }

        return Inertia::render('Auth/Login', [
            'configured' => (bool) (config('portal.login') && config('portal.password')),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required|string|max:255',
        ]);

        $login = (string) config('portal.login');
        $password = (string) config('portal.password');

        // сравнение за постоянное время, чтобы по скорости ответа нельзя было подобрать пароль
        $ok = $login !== '' && $password !== ''
            && hash_equals($login, $data['login'])
            && hash_equals($password, $data['password']);

        if (!$ok) {
            return back()->withErrors(['password' => 'Неверный логин или пароль'])->onlyInput('login');
        }

        $request->session()->regenerate();
        $request->session()->put(PortalAuth::SESSION_KEY, true);

        return redirect()->intended(route('projects.index'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget(PortalAuth::SESSION_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\InstagramOAuth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InstagramOAuthController extends Controller
{
    public function connect(Request $request, Project $project)
    {
        $state = bin2hex(random_bytes(16));

        $url = InstagramOAuth::authUrl($state);
        if (!$url) {
            return redirect()->route('projects.show', $project)->with('error', 'Сначала сохраните App ID и App Secret приложения Meta на странице «Настройки»');
        }

        // в state нельзя класть данные: он виден в адресной строке; проект держим в сессии
        $request->session()->put('instagram_oauth', ['state' => $state, 'project_id' => $project->id]);

        return Inertia::location($url);
    }

    public function callback(Request $request)
    {
        $pending = $request->session()->pull('instagram_oauth');
        $project = $pending ? Project::find($pending['project_id']) : null;
        $fallback = $project ? redirect()->route('projects.show', $project) : redirect()->route('projects.index');

        if ($request->filled('error')) {
            return $fallback->with('error', 'Instagram: '.($request->input('error_description') ?: $request->input('error_reason') ?: $request->input('error')));
        }

        if (!$project || !$pending || $pending['state'] !== $request->input('state')) {
            return $fallback->with('error', 'Сессия авторизации устарела — нажмите «Подключить Instagram» ещё раз');
        }

        try {
            InstagramOAuth::connect($project, $request->input('code', ''));
        } catch (\Throwable $e) {
            return $fallback->with('error', 'Не удалось подключить Instagram: '.$e->getMessage());
        }

        return $fallback->with('success', 'Instagram подключён: @'.$project->fresh()->instagram_username);
    }

    public function disconnect(Project $project)
    {
        InstagramOAuth::disconnect($project);

        return back()->with('success', 'Instagram отключён от проекта');
    }
}

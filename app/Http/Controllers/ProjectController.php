<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\InstagramOAuth;
use App\Services\VkApi;
use App\Services\VkApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::withCount('reports')
            ->with(['reports' => fn ($q) => $q->with('weeklyStats')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (Project $p) {
                $last = $p->reports->first();

                return [
                    'id'            => $p->id,
                    'name'          => $p->name,
                    'color'         => $p->color,
                    'reports_count' => $p->reports_count,
                    'last_period'   => $last?->period_label,
                    'last_totals'   => $last?->totals,
                    'is_active' => $p->is_active,
                ];
            });

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'nullable|string|max:9',
            'client_period' => 'nullable|string|max:255',
            'manager' => 'nullable|string|max:255',
            'vk_group' => 'nullable|string|max:255',
            'youtube_channel' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $project = Project::create([
            'name'  => $data['name'],
            'slug'  => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            'color' => $data['color'] ?? '#6C4CF0',
            'client_period'   => $data['client_period'] ?? null,
            'manager'         => $data['manager'] ?? null,
            'vk_group'        => $data['vk_group'] ?? null,
            'youtube_channel' => $data['youtube_channel'] ?? null,
            'is_active'       => $data['is_active'] ?? true,
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Проект создан');
    }

    public function show(Project $project)
    {
        $project->load(['reports.weeklyStats']);

        $reports = $project->reports->map(fn ($report) => [
            'id'           => $report->id,
            'year'         => $report->year,
            'month'        => $report->month,
            'period_label' => $report->period_label,
            'totals'       => $report->totals,
        ]);

        return Inertia::render('Projects/Show', [
            'project' => [
                'id'    => $project->id,
                'name'  => $project->name,
                'color' => $project->color,
                'client_period' => $project->client_period,
                'manager' => $project->manager,
                'vk_group' => $project->vk_group,
                'has_vk_token' => (bool) $project->vk_token,
                'youtube_channel' => $project->youtube_channel,
                'instagram_username' => $project->instagram_username,
                'instagram_connected' => (bool) $project->instagram_token,
                'instagram_expires_at' => $project->instagram_token_expires_at?->format('d.m.Y'),
                'instagram_configured' => InstagramOAuth::configured(),
                'is_active' => $project->is_active,
            ],
            'reports' => $reports,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'color'         => 'nullable|string|max:9',
            'client_period' => 'nullable|string|max:255',
            'manager'       => 'nullable|string|max:255',
            'vk_group'      => 'nullable|string|max:255',
            'vk_token'        => 'nullable|string|max:1024',
            'vk_token_remove' => 'boolean',
            'youtube_channel' => 'nullable|string|max:255',
            'is_active'     => 'boolean',
        ]);

        $project->update([
            'name'          => $data['name'],
            'color'         => $data['color'] ?? '#6C4CF0',
            'client_period' => $data['client_period'] ?? null,
            'manager'       => $data['manager'] ?? null,
            'vk_group'      => $data['vk_group'] ?? null,
            'youtube_channel' => trim($data['youtube_channel'] ?? '') ?: null,
            'is_active'     => $data['is_active'] ?? false,
        ]);

        if ($data['vk_token_remove'] ?? false) {
            $project->update(['vk_token' => null]);
        } elseif ($token = trim($data['vk_token'] ?? '')) {
            try {
                (new VkApi($token))->call('users.get');
            } catch (VkApiException $e) {
                if (str_contains($e->getMessage(), 'service token')) {
                    return back()->with('error', 'Это сервисный ключ приложения — он не даёт доступа к статистике. Нужен ключ доступа сообщества (Управление сообществом → Работа с API → Ключи доступа).');
                }
                // 27 — ключ сообщества: users.get недоступен, но для статистики он подходит
                if ($e->getCode() !== 27) {
                    return back()->with('error', 'Ключ ВК не прошёл проверку: ' . $e->getMessage());
                }
            } catch (\Throwable) {
                return back()->with('error', 'Не удалось связаться с VK API — ключ не сохранён');
            }
            $project->update(['vk_token' => $token]);
        }

        return back()->with('success', 'Проект обновлён');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Проект удалён');
    }
}

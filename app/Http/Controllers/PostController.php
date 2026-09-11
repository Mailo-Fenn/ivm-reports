<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Project;
use App\Services\Publishing\PostPublisher;
use Illuminate\Http\Request;
use Inertia\Inertia;

// контент-план проекта: публикации с текстом и файлами, отправка в соцсети по расписанию
class PostController extends Controller
{
    public function index(Project $project)
    {
        $posts = $project->posts()->with('publications')->orderByDesc('scheduled_at')->orderByDesc('id')->get()
            ->map(fn (Post $p) => $this->postData($p));

        return Inertia::render('Projects/Posts', [
            'project' => ['id' => $project->id, 'name' => $project->name, 'color' => $project->color],
            'posts' => $posts,
            'platformNames' => PostPublisher::PLATFORM_NAMES,
            // null — площадка доступна, строка — почему нет
            'availability' => PostPublisher::availability($project),
        ]);
    }

    public function store(Request $request, Project $project, PostPublisher $publisher)
    {
        [$data, $action] = $this->validated($request);
        $post = $project->posts()->create($data);

        return $this->afterSave($post, $action, $publisher);
    }

    public function update(Request $request, Post $post, PostPublisher $publisher)
    {
        if ($post->status === 'published') {
            return back()->with('error', 'Опубликованную запись изменить нельзя — создайте новую');
        }
        [$data, $action] = $this->validated($request);
        $post->update($data);
        // площадки могли поменяться — старые результаты по убранным площадкам не нужны
        $post->publications()->whereNotIn('platform', $data['platforms'])->delete();

        return $this->afterSave($post, $action, $publisher);
    }

    // опубликовать сейчас (или повторить упавшие площадки)
    public function publish(Post $post, PostPublisher $publisher)
    {
        if (!$post->platforms) {
            return back()->with('error', 'Не выбраны площадки для публикации');
        }
        $post->update(['scheduled_at' => $post->scheduled_at ?? now()]);
        $pubs = $publisher->publish($post);

        return back()->with($this->flashFor($pubs));
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return back()->with('success', 'Публикация удалена');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'text' => 'nullable|string|max:10000',
            'media' => 'nullable|array|max:10',
            'media.*.path' => 'required|string|max:255',
            'media.*.type' => 'required|in:image,video',
            'media.*.name' => 'nullable|string|max:255',
            'platforms' => 'array',
            'platforms.*' => 'in:'.implode(',', array_keys(PostPublisher::PLATFORM_NAMES)),
            'scheduled_at' => 'nullable|date',
            // draft — сохранить черновик, schedule — поставить в план, now — опубликовать сразу
            'action' => 'required|in:draft,schedule,now',
        ]);

        $action = $data['action'];
        $platforms = array_values(array_unique($data['platforms'] ?? []));
        $text = trim($data['text'] ?? '');
        $media = array_values($data['media'] ?? []);

        if ($text === '' && !$media) {
            abort(back()->with('error', 'Добавьте текст или хотя бы один файл'));
        }
        if ($action !== 'draft' && !$platforms) {
            abort(back()->with('error', 'Выберите хотя бы одну площадку'));
        }
        if ($action === 'schedule' && empty($data['scheduled_at'])) {
            abort(back()->with('error', 'Укажите дату и время публикации'));
        }

        return [[
            'text' => $text !== '' ? $text : null,
            'media' => $media ?: null,
            'platforms' => $platforms,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => $action === 'draft' ? 'draft' : 'scheduled',
        ], $action];
    }

    private function afterSave(Post $post, string $action, PostPublisher $publisher)
    {
        if ($action === 'now') {
            $post->update(['scheduled_at' => now()]);

            return back()->with($this->flashFor($publisher->publish($post)));
        }
        if ($action === 'schedule') {
            return back()->with('success', 'Публикация запланирована на '.$post->scheduled_at->timezone(config('app.timezone'))->format('d.m.Y H:i'));
        }

        return back()->with('success', 'Черновик сохранён');
    }

    private function flashFor(array $pubs): array
    {
        $names = PostPublisher::PLATFORM_NAMES;
        $ok = collect($pubs)->where('status', 'published')->map(fn ($p) => $names[$p->platform] ?? $p->platform)->implode(', ');
        $bad = collect($pubs)->where('status', 'failed')->map(fn ($p) => ($names[$p->platform] ?? $p->platform).' — '.$p->error)->implode('; ');

        if ($bad && $ok) {
            return ['error' => "Опубликовано: {$ok}. Не удалось: {$bad}"];
        }
        if ($bad) {
            return ['error' => 'Не удалось опубликовать: '.$bad];
        }

        return ['success' => 'Опубликовано: '.$ok];
    }

    private function postData(Post $p): array
    {
        return [
            'id' => $p->id,
            'text' => $p->text,
            'media' => array_map(fn ($m) => ['path' => $m['path'], 'type' => $m['type'], 'name' => $m['name'], 'url' => $m['url']], $p->mediaFiles()),
            'platforms' => $p->platforms ?? [],
            'scheduled_at' => $p->scheduled_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'scheduled_label' => $p->scheduled_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'status' => $p->status,
            'publications' => $p->publications->map(fn ($x) => [
                'platform' => $x->platform,
                'status' => $x->status,
                'url' => $x->url,
                'error' => $x->error,
                'published_at' => $x->published_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            ])->values()->all(),
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\Publishing\PostPublisher;
use Illuminate\Console\Command;

// планировщик контент-плана: раз в минуту отправляет публикации, чьё время пришло
class PublishPosts extends Command
{
    protected $signature = 'posts:publish';

    protected $description = 'Опубликовать запланированные публикации, время которых наступило';

    public function handle(PostPublisher $publisher): int
    {
        $due = Post::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        if ($due->isEmpty()) {
            $this->info('Нет публикаций к отправке.');

            return self::SUCCESS;
        }

        foreach ($due as $post) {
            $pubs = $publisher->publish($post);
            $line = collect($pubs)->map(fn ($p) => "{$p->platform}: {$p->status}".($p->error ? " ({$p->error})" : ''))->implode(', ');
            $this->info("#{$post->id} {$post->project->name}: {$line}");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\Publishing;

use App\Models\Post;
use App\Models\PostPublication;
use App\Models\Setting;
use App\Models\Project;

// Отправка публикации во все выбранные площадки. Каждая площадка — отдельная запись
// post_publications: уже опубликованные не трогаем, упавшие можно повторить.
class PostPublisher
{
    public const PLATFORM_NAMES = ['vk' => 'ВКонтакте', 'tg' => 'Телеграм', 'ig' => 'Инстаграм'];

    // какие площадки доступны проекту для публикации и почему недоступны остальные
    public static function availability(Project $project): array
    {
        return [
            'vk' => $project->vk_token && $project->vk_group
                ? null
                : 'нужны сообщество и ключ доступа сообщества с правами «Стена», «Фото», «Видео» в проекте',
            'tg' => Setting::get('telegram_bot_token')
                ? ($project->telegram_channel ? null : 'укажите канал Telegram в проекте и добавьте бота его администратором')
                : 'задайте ключ Telegram-бота на странице «Настройки»',
            'ig' => 'публикация в Instagram появится следующим этапом',
        ];
    }

    private function publisherFor(string $platform): object
    {
        return match ($platform) {
            'vk' => new VkPublisher(),
            'tg' => new TelegramPublisher(),
            default => throw new PublishException('Публикация в эту площадку пока не поддерживается'),
        };
    }

    // публикует во все площадки поста, возвращает свежие записи по площадкам
    public function publish(Post $post): array
    {
        $post->load('project');
        $post->update(['status' => 'publishing']);

        foreach ($post->platforms ?? [] as $platform) {
            $pub = PostPublication::firstOrCreate(['post_id' => $post->id, 'platform' => $platform]);
            if ($pub->status === 'published') {
                continue;
            }

            try {
                $r = $this->publisherFor($platform)->publish($post);
                $pub->update([
                    'status' => 'published',
                    'external_id' => $r['id'] ?? null,
                    'url' => $r['url'] ?? null,
                    'error' => null,
                    'attempts' => $pub->attempts + 1,
                    'published_at' => now(),
                ]);
            } catch (PublishException $e) {
                $pub->update(['status' => 'failed', 'error' => $e->getMessage(), 'attempts' => $pub->attempts + 1]);
            } catch (\Throwable $e) {
                $pub->update(['status' => 'failed', 'error' => 'Сбой при отправке: '.mb_substr($e->getMessage(), 0, 300), 'attempts' => $pub->attempts + 1]);
            }
        }

        $post->refreshStatus();

        return $post->publications()->get()->all();
    }
}

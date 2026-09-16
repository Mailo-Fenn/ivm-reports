<?php

namespace App\Services\Publishing;

use App\Models\Post;
use App\Services\Max\MaxChats;
use App\Services\Max\MaxException;

// Публикация в канал MAX через бота агентства: канал выбирается в проекте из списка каналов,
// куда бот добавлен администратором. Фото и видео загружаются заранее и прикрепляются к посту.
class MaxPublisher
{
    private const TEXT_LIMIT = 4000;

    public static function available(Post $post): bool
    {
        return (bool) (MaxChats::configured() && $post->project->max_channel_id);
    }

    // возвращает ['id' => mid, 'url' => публичная ссылка на пост или null]
    public function publish(Post $post): array
    {
        $chatId = (int) $post->project->max_channel_id;
        if (!$chatId) {
            throw new PublishException('У проекта не выбран канал MAX');
        }

        $text = trim((string) $post->text);
        $files = $post->mediaFiles();
        if ($text === '' && !$files) {
            throw new PublishException('Пустая публикация: нет ни текста, ни файлов');
        }

        try {
            $api = MaxChats::api();

            $attachments = [];
            foreach (array_slice($files, 0, 10) as $f) {
                if (!is_file($f['abs'])) {
                    throw new PublishException("Файл «{$f['name']}» не найден на сервере");
                }
                $attachments[] = $api->uploadAttachment($f['type'] === 'video' ? 'video' : 'image', $f['abs'], $f['name']);
            }

            // текст длиннее лимита — остаток уходит вторым сообщением
            $first = mb_substr($text, 0, self::TEXT_LIMIT);
            $rest = mb_substr($text, self::TEXT_LIMIT);
            $msg = $api->sendMessage($chatId, $first, $attachments);
            if ($rest !== '') {
                $api->sendMessage($chatId, $rest, []);
            }
        } catch (MaxException $e) {
            throw new PublishException('MAX: '.self::humanize($e));
        }

        $m = $msg['message'] ?? $msg;

        return ['id' => (string) ($m['body']['mid'] ?? ''), 'url' => $m['url'] ?? null];
    }

    private static function humanize(MaxException $e): string
    {
        return match (true) {
            $e->getCode() === 401 => 'ключ бота недействителен — проверьте его в настройках',
            $e->getCode() === 403 => 'у бота нет прав публиковать — сделайте его администратором канала',
            $e->getCode() === 404 => 'канал не найден — обновите список каналов в настройках и выберите его в проекте заново',
            default => $e->getMessage(),
        };
    }
}

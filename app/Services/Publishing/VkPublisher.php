<?php

namespace App\Services\Publishing;

use App\Models\Post;
use App\Services\VkApi;
use App\Services\VkApiException;
use Illuminate\Support\Facades\Http;

// Публикация на стену сообщества ВКонтакте от имени сообщества. Нужен ключ доступа сообщества
// (поле «Ключ доступа сообщества ВК» в проекте) с правами «Стена», «Фото» и «Видео».
class VkPublisher
{
    public static function available(Post $post): bool
    {
        return (bool) ($post->project->vk_token && $post->project->vk_group);
    }

    // возвращает ['id' => post_id, 'url' => ссылка на пост]
    public function publish(Post $post): array
    {
        $project = $post->project;
        if (!$project->vk_token) {
            throw new PublishException('Нужен ключ доступа сообщества ВК с правами «Стена», «Фото», «Видео» — укажите его в проекте');
        }
        if (!$project->vk_group) {
            throw new PublishException('У проекта не указано сообщество ВК');
        }

        $text = trim((string) $post->text);
        $files = $post->mediaFiles();
        if ($text === '' && !$files) {
            throw new PublishException('Пустая публикация: нет ни текста, ни файлов');
        }

        $vk = new VkApi($project->vk_token);

        try {
            $group = $vk->group($project->vk_group);
            $gid = (int) $group['id'];

            $attachments = [];
            foreach (array_slice($files, 0, 10) as $f) {
                if (!is_file($f['abs'])) {
                    throw new PublishException("Файл «{$f['name']}» не найден на сервере");
                }
                $attachments[] = $f['type'] === 'video'
                    ? $this->uploadVideo($vk, $gid, $f, $text)
                    : $this->uploadPhoto($vk, $gid, $f);
            }

            $r = $vk->call('wall.post', array_filter([
                'owner_id' => -$gid,
                'from_group' => 1,
                'message' => $text,
                'attachments' => implode(',', $attachments),
            ], fn ($v) => $v !== '' && $v !== null));
        } catch (VkApiException $e) {
            throw new PublishException('ВКонтакте: '.self::humanize($e));
        }

        $id = (string) ($r['post_id'] ?? '');

        return ['id' => $id, 'url' => $id ? "https://vk.com/wall-{$gid}_{$id}" : null];
    }

    // фото на стену: getWallUploadServer → загрузка файла → saveWallPhoto
    private function uploadPhoto(VkApi $vk, int $gid, array $f): string
    {
        $server = $vk->call('photos.getWallUploadServer', ['group_id' => $gid]);
        $up = Http::timeout(120)
            ->attach('photo', fopen($f['abs'], 'r'), $f['name'])
            ->post($server['upload_url'])
            ->json() ?? [];
        if (empty($up['photo']) || $up['photo'] === '[]') {
            throw new PublishException('ВКонтакте не принял фото «'.$f['name'].'»');
        }
        $saved = $vk->call('photos.saveWallPhoto', [
            'group_id' => $gid,
            'server' => $up['server'],
            'photo' => $up['photo'],
            'hash' => $up['hash'],
        ]);
        $p = $saved[0] ?? null;
        if (!$p) {
            throw new PublishException('ВКонтакте не сохранил фото «'.$f['name'].'»');
        }

        return "photo{$p['owner_id']}_{$p['id']}";
    }

    // видео: video.save в сообщество → загрузка файла на upload_url
    private function uploadVideo(VkApi $vk, int $gid, array $f, string $text): string
    {
        $save = $vk->call('video.save', [
            'group_id' => $gid,
            'name' => mb_substr($text !== '' ? preg_split('~\R~', $text)[0] : pathinfo($f['name'], PATHINFO_FILENAME), 0, 100),
            'wallpost' => 0,
        ]);
        $up = Http::timeout(600)
            ->attach('video_file', fopen($f['abs'], 'r'), $f['name'])
            ->post($save['upload_url'])
            ->json() ?? [];
        if (!empty($up['error'])) {
            throw new PublishException('ВКонтакте не принял видео «'.$f['name'].'»: '.($up['error_descr'] ?? $up['error']));
        }
        $videoId = $up['video_id'] ?? $save['video_id'] ?? null;
        $ownerId = $up['owner_id'] ?? $save['owner_id'] ?? -$gid;
        if (!$videoId) {
            throw new PublishException('ВКонтакте не вернул id видео «'.$f['name'].'»');
        }

        return "video{$ownerId}_{$videoId}";
    }

    private static function humanize(VkApiException $e): string
    {
        return match ($e->getCode()) {
            5 => 'ключ сообщества недействителен — выпустите новый в настройках сообщества',
            7, 15 => 'у ключа сообщества нет нужных прав — включите «Стена», «Фото» и «Видео»',
            27 => 'ключ сообщества не подходит для этого действия',
            214 => 'публикация запрещена настройками сообщества',
            default => $e->getMessage(),
        };
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// колонка reports.community переходит с плоского текста на JSON-массив [{image, caption}];
// старый текст сохраняем как один элемент с подписью без картинки
return new class extends Migration {
    public function up(): void
    {
        foreach (DB::table('reports')->whereNotNull('community')->get(['id', 'community']) as $r) {
            $text = trim($r->community);

            if ($text === '') {
                DB::table('reports')->where('id', $r->id)->update(['community' => null]);
                continue;
            }

            if (str_starts_with($text, '[') && json_decode($text) !== null) {
                continue;
            }

            DB::table('reports')->where('id', $r->id)->update([
                'community' => json_encode([['image' => null, 'caption' => $text]], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('reports')->whereNotNull('community')->get(['id', 'community']) as $r) {
            $items = json_decode($r->community, true);
            if (!is_array($items)) {
                continue;
            }

            $text = implode("\n", array_filter(array_map(fn ($i) => trim($i['caption'] ?? ''), $items)));
            DB::table('reports')->where('id', $r->id)->update(['community' => $text !== '' ? $text : null]);
        }
    }
};

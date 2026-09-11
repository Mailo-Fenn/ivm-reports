<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// «Работа с сообществом» ведётся по площадкам: community из плоского списка
// [{image, caption}] становится объектом {vk: [...], ig: [...]}. Старые записи относим к ВК.
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('reports')->whereNotNull('community')->get(['id', 'community']) as $r) {
            $items = json_decode($r->community, true);
            if (!is_array($items) || !array_is_list($items)) {
                continue;
            }
            DB::table('reports')->where('id', $r->id)->update([
                'community' => $items ? json_encode(['vk' => array_values($items)], JSON_UNESCAPED_UNICODE) : null,
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('reports')->whereNotNull('community')->get(['id', 'community']) as $r) {
            $data = json_decode($r->community, true);
            if (!is_array($data) || array_is_list($data)) {
                continue;
            }
            $flat = array_merge(...array_values(array_map('array_values', $data)));
            DB::table('reports')->where('id', $r->id)->update([
                'community' => $flat ? json_encode($flat, JSON_UNESCAPED_UNICODE) : null,
            ]);
        }
    }
};

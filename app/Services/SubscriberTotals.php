<?php

namespace App\Services;

use Carbon\Carbon;

// В отчёте поле «Подписчики» — общее число на конец недели (в итог месяца идёт последняя
// заполненная неделя). API площадок отдают только текущее число и прирост по дням, поэтому
// прошлые значения восстанавливаем: число на дату = число сейчас − прирост после этой даты.
class SubscriberTotals
{
    /**
     * @param  int         $current   число подписчиков сейчас
     * @param  array       $netByDay  чистый прирост по дням ['Y-m-d' => int], желательно от начала месяца до сегодня
     * @param  Carbon      $start     первый день месяца отчёта (в зоне отчёта)
     * @param  Carbon      $end       последний день месяца отчёта
     * @param  Carbon|null $dataFrom  с какой даты прирост известен; недели, закончившиеся раньше, не восстанавливаются
     * @return array<int, int|null>|null  [1..4 => число на конец недели]; null у недели — данных нет или она ещё не началась
     */
    public static function byWeek(int $current, array $netByDay, Carbon $start, Carbon $end, ?Carbon $dataFrom = null): ?array
    {
        $tz = $start->getTimezone();
        $today = Carbon::now($tz)->startOfDay();
        if ($start->copy()->startOfDay()->gt($today)) {
            return null;
        }

        // месяц уже закончился — нужен прирост за всё время после него до сегодня;
        // если данные обрываются раньше, восстановить прошлое нельзя
        $monthOver = $end->copy()->startOfDay()->lt($today);
        if ($monthOver) {
            $lastDay = $netByDay ? max(array_keys($netByDay)) : null;
            if ($lastDay === null || Carbon::parse($lastDay, $tz)->lt($today->copy()->subDays(3))) {
                return null;
            }
        }

        $weeks = [];
        foreach (range(1, 4) as $i) {
            $weekStart = $start->copy()->startOfDay()->addDays(($i - 1) * 7);
            $weekEnd = $i === 4 ? $end->copy()->startOfDay() : $weekStart->copy()->addDays(6);
            if ($weekStart->gt($today)) {
                $weeks[$i] = null;
                continue;
            }
            $edge = $weekEnd->min($today);
            if ($dataFrom && $edge->lt($dataFrom->copy()->startOfDay())) {
                $weeks[$i] = null;
                continue;
            }
            $after = 0;
            $edgeDate = $edge->toDateString();
            foreach ($netByDay as $day => $net) {
                if ($day > $edgeDate) {
                    $after += (int) $net;
                }
            }
            $weeks[$i] = max(0, $current - $after);
        }

        return $weeks;
    }
}

<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Истина в маркетинге.');
})->purpose('Display an inspiring quote');

// сторис живут 24 часа — забираем их несколько раз в сутки, чтобы ничего не пропустить.
// Требует cron на сервере: * * * * * cd /var/www/ivm && php artisan schedule:run
Schedule::command('instagram:collect-stories')->everyFourHours()->withoutOverlapping();

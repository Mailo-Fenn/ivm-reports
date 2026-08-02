<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Истина в маркетинге.');
})->purpose('Display an inspiring quote');

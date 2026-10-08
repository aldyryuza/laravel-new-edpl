<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Aktivasi DPL yang Date Start-nya tiba & expire DPL yang lewat Date End (zona waktu kantor).
Schedule::command('dpl:refresh-program-status')
    ->dailyAt('00:05')
    ->timezone(config('dpl.timezone'))
    ->withoutOverlapping();

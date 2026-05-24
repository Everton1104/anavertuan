<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envia lembretes de consulta via WhatsApp (24h antes e 1h antes).
// Garanta que o cron do servidor esteja configurado:
//   * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('lembretes:enviar')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

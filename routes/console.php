<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envia lembretes de consulta via WhatsApp.
// Garanta que o cron do servidor esteja configurado:
//   * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1

// Lembrete diário: todo dia às 17h, para todas as consultas do dia seguinte.
Schedule::command('lembretes:enviar diario')
    ->dailyAt('17:00')
    ->withoutOverlapping()
    ->runInBackground();

// Lembrete de 2h antes: a cada 15 min (cobre horários com intervalos de 15 minutos).
Schedule::command('lembretes:enviar 2h')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

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

// Lembrete da véspera: todo dia às 19h, para todas as consultas do dia seguinte.
Schedule::command('lembretes:enviar')
    ->dailyAt('19:00')
    ->withoutOverlapping()
    ->runInBackground();

// Reconciliação InfinitePay: backstop para webhook perdido. A cada 5 min consulta
// (payment_check) as ordens pagáveis com link gerado e aplica o status real.
Schedule::command('infinitepay:reconciliar')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

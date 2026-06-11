<?php
// Teste de idempotência dos botões confirmar/reagendar do webhook do WhatsApp.
// Cria um agendamento de teste, simula cliques assinados e confere o estado.
// Uso: php test_idempotencia.php   (rodar na raiz do app, em produção)

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AgendamentoModel;
use App\Models\User;
use App\Models\ServicosModel;
use App\Models\WhatsappLog;
use App\Models\Aviso;
use Illuminate\Support\Facades\Http;

$TEST   = '5511997646569';
$secret = env('WHATSAPP_APP_SECRET');
$phone  = env('PHONE_NUMBER_ID');
$url    = 'https://anavertuan.com.br/whatsapp/webhook';

$logs = fn() => WhatsappLog::where('number', $TEST)->count();
$avisosAbertos = fn($ag) => Aviso::where('tipo', 'reagendamento_solicitado')
    ->where('user_id', $ag->user_id)
    ->where('data_antiga', $ag->data_inicio)
    ->whereNull('dispensado_at')->count();

$user = User::where('excluido', 0)->orderBy('id')->first();
$serv = ServicosModel::where('excluido', 0)->orderBy('id')->first();

$ag = AgendamentoModel::create([
    'user_id'         => $user->id,
    'servico_id'      => $serv->id,
    'data_inicio'     => now()->addDay()->setTime(12, 0, 0),
    'data_fim'        => now()->addDay()->setTime(13, 0, 0),
    'confirmado'      => false,
    'consome_credito' => false,
]);
$id = $ag->id;
echo "WHATSAPP_ADMIN_NUMBER no teste = " . env('WHATSAPP_ADMIN_NUMBER') . "\n";
echo "Agendamento de teste criado: id={$id} (cliente={$user->name})\n\n";

$fire = function (string $payload) use ($url, $phone, $TEST, $secret) {
    $body = json_encode([
        'entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => $phone],
            'messages' => [['from' => $TEST, 'type' => 'button', 'button' => ['payload' => $payload]]],
        ]]]]],
    ]);
    $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);
    $r = Http::withBody($body, 'application/json')
        ->withHeaders(['X-Hub-Signature-256' => $sig])
        ->post($url);
    return $r->status();
};

$linhas = [];
$passo = function (string $nome, string $payload) use (&$linhas, $fire, $logs, $avisosAbertos, &$ag) {
    $antes = $logs();
    $http  = $fire($payload);
    $ag->refresh();
    $delta = $logs() - $antes;
    $linhas[] = sprintf(
        "%-26s | %-4s | msgs +%d | confirmado=%s, avisos=%d",
        $nome, $http, $delta, $ag->confirmado ? 'true' : 'false', $avisosAbertos($ag)
    );
};

$passo('Confirmar (1o)',            'confirmar_' . $id);
$passo('Confirmar (2o, repetido)',  'confirmar_' . $id);
$passo('Reagendar (1o)',            'reagendar_' . $id);
$passo('Reagendar (2o, repetido)',  'reagendar_' . $id);
$passo('Confirmar (apos reagendar)','confirmar_' . $id);

echo "Passo                      | HTTP | Mensagens | Estado\n";
echo str_repeat('-', 78) . "\n";
foreach ($linhas as $l) echo $l . "\n";

// Limpeza
Aviso::where('tipo', 'reagendamento_solicitado')
    ->where('user_id', $ag->user_id)
    ->where('data_antiga', $ag->data_inicio)->delete();
$ag->delete();
echo "\nLimpeza OK: agendamento de teste e avisos removidos.\n";

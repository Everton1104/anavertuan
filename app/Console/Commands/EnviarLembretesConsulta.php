<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsappController;
use App\Models\AgendamentoModel;
use App\Models\LembreteConsulta;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EnviarLembretesConsulta extends Command
{
    protected $signature   = 'lembretes:enviar';
    protected $description = 'Envia lembretes de consulta via WhatsApp (24h e 2h antes)';

    public function handle(): void
    {
        $phoneId = env('PHONE_NUMBER_ID');
        $template = env('WHATSAPP_TEMPLATE_LEMBRETE');

        if (!$phoneId) {
            $this->error('PHONE_NUMBER_ID não configurado no .env');
            return;
        }

        if (!$template) {
            $this->error('WHATSAPP_TEMPLATE_LEMBRETE não configurado no .env');
            return;
        }

        $this->enviarLembretes('24h', now()->addHours(22), now()->addHours(26), $phoneId, $template);
        $this->enviarLembretes('2h',  now()->addHours(1),  now()->addHours(3),  $phoneId, $template);
    }

    private function enviarLembretes(string $tipo, Carbon $de, Carbon $ate, string $phoneId, string $template): void
    {
        $agendamentos = AgendamentoModel::with(['user', 'servico'])
            ->whereBetween('data_inicio', [$de, $ate])
            ->whereDoesntHave('lembretes', fn($q) => $q->where('tipo', $tipo))
            ->get();

        foreach ($agendamentos as $agendamento) {
            $user = $agendamento->user;

            if (!$user || !$user->whatsapp) {
                continue;
            }

            $nome          = ucfirst($user->name);
            $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
            $data          = Carbon::parse($agendamento->data_inicio)
                                ->locale('pt_BR')
                                ->translatedFormat('d \d\e F \d\e Y');
            $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');

            $resultado = WhatsappController::enviarModelo($phoneId, $user->whatsapp, $template, [
                ['type' => 'text', 'text' => $nome],
                ['type' => 'text', 'text' => $nomeComercial],
                ['type' => 'text', 'text' => $data],
                ['type' => 'text', 'text' => $hora],
            ], 'pt_BR', [
                'confirmar_' . $agendamento->id,
                'reagendar_' . $agendamento->id,
            ]);

            $erro    = isset($resultado['erro']);
            $erroMsg = $erro ? ($resultado['msg'] ?? 'Erro desconhecido') : null;

            LembreteConsulta::create([
                'agendamento_id' => $agendamento->id,
                'tipo'           => $tipo,
                'status'         => $erro ? 'erro' : 'enviado',
                'erro_msg'       => $erroMsg,
            ]);

            $this->line($erro
                ? "  [ERRO] {$tipo} → {$user->whatsapp}: {$erroMsg}"
                : "  [OK]   {$tipo} → {$user->whatsapp} ({$nome} — {$data} às {$hora})"
            );
        }

        $this->info("Lembretes {$tipo}: {$agendamentos->count()} processado(s).");
    }
}

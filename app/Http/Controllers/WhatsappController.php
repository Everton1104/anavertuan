<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\Aviso;
use App\Models\WhatsappLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class WhatsappController extends Controller
{
    // ── Webhook ──────────────────────────────────────────────────────────────

    public function verifyToken(Request $request)
    {
        $verifyToken = env('WEBHOOK_VERIFY_TOKEN');
        $challenge   = $request['hub_challenge'];
        $token       = $request['hub_verify_token'];

        if ($token === $verifyToken) {
            return response($challenge, 200);
        }

        return response('Token de verificação inválido', 403);
    }

    public function getMsgs(Request $request)
    {
        if (!$this->assinaturaValida($request)) {
            \Log::channel('single')->warning('[WA-WEBHOOK] assinatura X-Hub-Signature-256 inválida');
            return response()->json(['error' => 'invalid signature'], 403);
        }

        $business_phone_number_id = $request['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? 0;
        $msg                      = $request['entry'][0]['changes'][0]['value']['messages'][0] ?? '';
        $status                   = $request['entry'][0]['changes'][0]['value']['statuses'] ?? null;
        $number                   = $msg['from'] ?? 0;

        // Log enxuto: só o necessário para depurar o fluxo de botões. Evita gravar o
        // payload inteiro (texto livre do paciente é PII) a cada evento da Meta.
        \Log::channel('single')->info('[WA-WEBHOOK]', [
            'from'    => $number,
            'type'    => $msg['type'] ?? ($status ? 'status' : 'desconhecido'),
            'payload' => $msg['button']['payload'] ?? null,
        ]);

        try {
            if ($status) {
                return response()->json([], 200);
            }

            $msgType = $msg['type'] ?? '';
            $payload = $msg['button']['payload'] ?? '';

            if ($msgType === 'button' && $payload) {
                if (str_starts_with($payload, 'confirmar_pre_')) {
                    $agendamentoId = (int) str_replace('confirmar_pre_', '', $payload);
                    $this->tratarPreConfirmacao($business_phone_number_id, $number, $agendamentoId);
                } elseif (str_starts_with($payload, 'confirmar_')) {
                    $agendamentoId = (int) str_replace('confirmar_', '', $payload);
                    $this->tratarConfirmacao($business_phone_number_id, $number, $agendamentoId);
                } elseif (str_starts_with($payload, 'reagendar_')) {
                    $agendamentoId = (int) str_replace('reagendar_', '', $payload);
                    $this->tratarReagendamento($business_phone_number_id, $number, $agendamentoId);
                }
            }

            return response()->json([], 200);
        } catch (\Throwable $th) {
            $this->enviarMsg($business_phone_number_id, $number, 'Erro interno. Tente novamente.');
            return response()->json([], 200);
        }
    }

    // Confirma que o POST veio mesmo da Meta: o corpo bruto é assinado por ela
    // (HMAC-SHA256 com o App Secret) no header X-Hub-Signature-256. Sem isso,
    // qualquer um que descubra a URL poderia injetar eventos falsos.
    // Se WHATSAPP_APP_SECRET não estiver configurado, não bloqueia (fail-open),
    // para não derrubar ambientes ainda não configurados.
    private function assinaturaValida(Request $request): bool
    {
        $appSecret = env('WHATSAPP_APP_SECRET');
        if (!$appSecret) {
            return true;
        }

        $assinatura = (string) $request->header('X-Hub-Signature-256', '');
        if (!str_starts_with($assinatura, 'sha256=')) {
            return false;
        }

        $esperado = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
        return hash_equals($esperado, $assinatura);
    }

    // ── Tratamento de respostas de botões ────────────────────────────────────

    // Pré-confirmação: cliente respondeu o lembrete da véspera.
    private function tratarPreConfirmacao(string $phoneId, string $number, int $agendamentoId): void
    {
        $agendamento = AgendamentoModel::with('user')->find($agendamentoId);
        if (!$agendamento) return;

        // Mantém o primeiro horário de pré-confirmação; ignora cliques repetidos.
        if (!$agendamento->pre_confirmado_em) {
            $agendamento->pre_confirmado_em = now();
            $agendamento->save();
        }

        $nome          = ucfirst($agendamento->user->name ?? 'você');
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
        $data          = Carbon::parse($agendamento->data_inicio)
                             ->locale('pt_BR')
                             ->translatedFormat('d \d\e F');
        $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');

        self::enviarMsg($phoneId, $number,
            "Obrigado por confirmar, {$nome}! ✅ Sua presença para o dia *{$data}* às *{$hora}* está pré-confirmada.\n\n"
            . "No dia da consulta enviaremos uma última confirmação. Até lá! 😊"
        );
    }

    // Confirmação oficial: cliente respondeu o lembrete de 2h antes (ou staff confirmou manualmente).
    private function tratarConfirmacao(string $phoneId, string $number, int $agendamentoId): void
    {
        $agendamento = AgendamentoModel::with('user')->find($agendamentoId);
        if (!$agendamento) return;

        // Idempotência: o WhatsApp mantém o botão clicável após o clique. Se já
        // está confirmado, ignora cliques repetidos (não reenvia as mensagens).
        if ($agendamento->confirmado) return;

        $agendamento->confirmado    = true;
        $agendamento->confirmado_em = now();
        $agendamento->save();

        // Cliente decidiu confirmar: dispensa qualquer pedido de reagendamento em
        // aberto (evita estado contraditório se antes havia clicado em "reagendar").
        Aviso::where('tipo', 'reagendamento_solicitado')
            ->where('user_id', $agendamento->user_id)
            ->where('data_antiga', $agendamento->data_inicio)
            ->whereNull('dispensado_at')
            ->update(['dispensado_at' => now()]);

        $nome          = ucfirst($agendamento->user->name ?? 'você');
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
        $data          = Carbon::parse($agendamento->data_inicio)
                             ->locale('pt_BR')
                             ->translatedFormat('d \d\e F');
        $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');
        $endereco      = env('WHATSAPP_ENDERECO', 'Rua 23 de Maio, n° 790, Vila Vianelo, Jundiaí - Sala n.º 35 - 3° andar Bloco A "Condomínio Centro Comercial Tebas"');
        $maps          = env('WHATSAPP_MAPS_LINK', 'https://maps.app.goo.gl/gPbt9ZuejqqJRrA56');

        self::enviarMsg($phoneId, $number,
            "Perfeito, {$nome}! ✅ Sua presença está confirmada para o dia *{$data}* às *{$hora}*.\n\n"
            . "Muito obrigado por confirmar! Estamos te esperando na {$nomeComercial}. Até lá! 😊"
        );

        // Mensagem separada com o endereço da clínica + link do Google Maps em texto.
        // (O botão interativo cta_url não estava sendo entregue; link em texto é
        // clicável no WhatsApp e usa o mesmo enviarMsg que funciona de forma confiável.)
        self::enviarMsg($phoneId, $number,
            "📍 *Endereço da clínica:*\n{$endereco}\n\n"
            . "🗺️ Como chegar: {$maps}"
        );
    }

    private function tratarReagendamento(string $phoneId, string $number, int $agendamentoId): void
    {
        $agendamento = AgendamentoModel::with(['user', 'servico'])->find($agendamentoId);
        if (!$agendamento) return;

        // Idempotência: se já há um pedido de reagendamento em aberto para esta
        // consulta, ignora o clique repetido (não duplica o aviso nem re-notifica a admin).
        $jaSolicitado = Aviso::where('tipo', 'reagendamento_solicitado')
            ->where('user_id', $agendamento->user_id)
            ->where('data_antiga', $agendamento->data_inicio)
            ->whereNull('dispensado_at')
            ->exists();
        if ($jaSolicitado) return;

        // Cliente quer trocar de horário: desfaz uma eventual confirmação anterior
        // (evita estado contraditório se antes havia clicado em "confirmar").
        if ($agendamento->confirmado) {
            $agendamento->confirmado    = false;
            $agendamento->confirmado_em = null;
            $agendamento->save();
        }

        Aviso::create([
            'tipo'        => 'reagendamento_solicitado',
            'user_id'     => $agendamento->user_id,
            'servico_id'  => $agendamento->servico_id,
            'especial'    => $agendamento->especial,
            'data_antiga' => $agendamento->data_inicio,
        ]);

        $nome   = ucfirst($agendamento->user->name ?? 'você');
        $appUrl = rtrim(env('APP_URL', config('app.url')), '/');

        self::enviarMsg($phoneId, $number,
            "Tudo bem, {$nome}! 🔄 Para escolher um novo horário, acesse o link abaixo e faça login com o seu WhatsApp:\n\n{$appUrl}/dashboard\n\nSe preferir, nossa equipe pode te ajudar a encontrar o melhor horário. 😊"
        );

        // O aviso ao número de atendimento é enviado automaticamente pelo AvisoObserver
        // ao criar o Aviso 'reagendamento_solicitado' acima (evita duplicar aqui).
    }

    // ── Envio de mensagens ────────────────────────────────────────────────────

    public static function enviarMsg($business_phone_number_id, $numero, $msg)
    {
        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $numero,
                    'type'              => 'text',
                    'text'              => ['body' => $msg],
                ],
            ]);

            $body      = json_decode($response->getBody(), true);
            $messageId = $body['messages'][0]['id'] ?? null;

            self::log($numero, Auth::id(), $msg, null, $messageId);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $err = json_decode($e->getResponse()->getBody(), true);
            return ['erro' => 1, 'msg' => $err['error']['message'] ?? 'Erro desconhecido'];
        } catch (\Exception $e) {
            return ['erro' => 1, 'msg' => $e->getMessage()];
        }
    }

    public static function enviarModelo($business_phone_number_id, $numero, $templateName, $parametros = [], $language = 'pt_BR', $botoes = [])
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'template',
                'template'          => [
                    'name'     => $templateName,
                    'language' => ['code' => $language],
                ],
            ];

            $components = [];

            if (!empty($parametros)) {
                $components[] = ['type' => 'body', 'parameters' => $parametros];
            }

            foreach ($botoes as $index => $payloadBotao) {
                $components[] = [
                    'type'       => 'button',
                    'sub_type'   => 'quick_reply',
                    'index'      => $index,
                    'parameters' => [['type' => 'payload', 'payload' => $payloadBotao]],
                ];
            }

            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }

            $client   = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['error'])) {
                return ['erro' => 1, 'msg' => $body['error']['message'] ?? 'Erro na API do WhatsApp'];
            }

            self::log($numero, Auth::id(), $templateName, null, $business_phone_number_id);
            return ['msg' => 'Modelo enviado com sucesso'];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $err = json_decode($e->getResponse()->getBody(), true);
            return ['erro' => 1, 'msg' => $err['error']['message'] ?? 'Erro desconhecido'];
        } catch (\Exception $e) {
            return ['erro' => 1, 'msg' => $e->getMessage()];
        }
    }

    // ── Verificação de número ─────────────────────────────────────────────────

    public static function enviarCodigoVerificacao(\App\Models\User $user): void
    {
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->whatsapp_code            = $codigo;
        $user->whatsapp_code_expires_at = now()->addMinutes(10);
        $user->save();

        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/v25.0/" . env('PHONE_NUMBER_ID') . "/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $user->whatsapp,
                    'type'              => 'template',
                    'template'          => [
                        'name'       => 'user_code',
                        'language'   => ['code' => 'pt_BR'],
                        'components' => [
                            [
                                'type'       => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $codigo],
                                ],
                            ],
                            [
                                'type'       => 'button',
                                'sub_type'   => 'url',
                                'index'      => 0,
                                'parameters' => [
                                    ['type' => 'text', 'text' => $codigo],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            \Illuminate\Support\Facades\Log::info('WhatsApp user_code enviado', ['para' => $user->whatsapp, 'resp' => $body]);
            self::log($user->whatsapp, $user->id, 'Código de verificação enviado', null, env('PHONE_NUMBER_ID'));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $erro = json_decode($e->getResponse()->getBody(), true);
            \Illuminate\Support\Facades\Log::error('WhatsApp user_code ERRO', ['para' => $user->whatsapp, 'erro' => $erro]);
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error('WhatsApp user_code ERRO', ['para' => $user->whatsapp, 'msg' => $th->getMessage()]);
        }
    }

    // ── Log ───────────────────────────────────────────────────────────────────

    public static function log($number = null, $user_id = null, $msg = null, $dep_id = null, $business_phone_number_id = null)
    {
        WhatsappLog::create([
            'number'                   => $number,
            'user_id'                  => $user_id,
            'msg'                      => $msg,
            'dep_id'                   => $dep_id,
            'business_phone_number_id' => $business_phone_number_id,
        ]);
    }
}

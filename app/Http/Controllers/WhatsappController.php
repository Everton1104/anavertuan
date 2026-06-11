<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\Aviso;
use App\Models\WhatsappLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WhatsappController extends Controller
{
    // ── Webhook ──────────────────────────────────────────────────────────────

    public function virifyToken(Request $request)
    {
        $request     = Request::capture();
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

        \Log::channel('single')->info('[WA-WEBHOOK]', ['payload' => $request->all()]);

        $business_phone_number_id = $request['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? 0;
        $msg                      = $request['entry'][0]['changes'][0]['value']['messages'][0] ?? '';
        $status                   = $request['entry'][0]['changes'][0]['value']['statuses'] ?? null;
        $number                   = $msg['from'] ?? 0;
        $msgTxt                   = $msg['text']['body'] ?? '';

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

    public static function enviarMsgSimNao($business_phone_number_id, $numero, $msg, $id = 0, $title1 = 'Sim', $title2 = 'Não', $unico = false)
    {
        $btns = [
            ['type' => 'reply', 'reply' => ['id' => 'sim' . $id, 'title' => $title1]],
            ['type' => 'reply', 'reply' => ['id' => 'nao' . $id, 'title' => $title2]],
        ];

        if ($unico) {
            $btns = [['type' => 'reply', 'reply' => ['id' => 'sim' . $id, 'title' => $title1]]];
        }

        $client = new \GuzzleHttp\Client();
        $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
            'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            'json'    => [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'interactive',
                'interactive'       => [
                    'type'   => 'button',
                    'body'   => ['text' => $msg],
                    'action' => ['buttons' => $btns],
                ],
            ],
        ]);

        self::log($numero, Auth::id(), $msg, null, $business_phone_number_id);
    }

    // Mensagem interativa com um botão de URL (cta_url) — ex.: abrir rota no Google Maps.
    public static function enviarMsgBotaoUrl($business_phone_number_id, $numero, $msg, $url, $tituloBotao = 'Abrir')
    {
        try {
            $client = new \GuzzleHttp\Client();
            $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
                'json'    => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $numero,
                    'type'              => 'interactive',
                    'interactive'       => [
                        'type'   => 'cta_url',
                        'body'   => ['text' => $msg],
                        'action' => [
                            'name'       => 'cta_url',
                            'parameters' => [
                                'display_text' => $tituloBotao,
                                'url'          => $url,
                            ],
                        ],
                    ],
                ],
            ]);

            self::log($numero, Auth::id(), $msg, null, $business_phone_number_id);
            return ['sucesso' => 1];
        } catch (\Throwable $e) {
            return ['erro' => 1, 'msg' => $e->getMessage()];
        }
    }

    public static function enviarMsgSimNaoCancel($business_phone_number_id, $numero, $msg, $id = 0, $title1 = 'Sim', $title2 = 'Não', $title3 = 'Cancelar')
    {
        $client = new \GuzzleHttp\Client();
        $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
            'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            'json'    => [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'interactive',
                'interactive'       => [
                    'type'   => 'button',
                    'body'   => ['text' => $msg],
                    'action' => [
                        'buttons' => [
                            ['type' => 'reply', 'reply' => ['id' => 'sim' . $id,    'title' => $title1]],
                            ['type' => 'reply', 'reply' => ['id' => 'nao' . $id,    'title' => $title2]],
                            ['type' => 'reply', 'reply' => ['id' => 'cancel' . $id, 'title' => $title3]],
                        ],
                    ],
                ],
            ],
        ]);

        self::log($numero, Auth::id(), $msg, null, $business_phone_number_id);
    }

    public static function enviarMsgLista($business_phone_number_id, $numero, $msg, $lista = [], $secoes = null)
    {
        $sections = $secoes ?? [['rows' => $lista]];

        $client = new \GuzzleHttp\Client();
        $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
            'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            'json'    => [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'interactive',
                'interactive'       => [
                    'type'   => 'list',
                    'body'   => ['text' => $msg],
                    'action' => [
                        'button'   => 'Selecione uma opção:',
                        'sections' => $sections,
                    ],
                ],
            ],
        ]);

        self::log($numero, Auth::id(), $msg, null, $business_phone_number_id);
    }

    public static function enviarImg($business_phone_number_id, $numero, $link, $desc = 'imagem')
    {
        $client = new \GuzzleHttp\Client();
        $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
            'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            'json'    => [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'image',
                'image'             => ['link' => $link, 'caption' => $desc],
            ],
        ]);

        self::log($numero, Auth::id(), $desc, null, $business_phone_number_id);
    }

    public static function enviarAudio($business_phone_number_id, $numero, $link)
    {
        $client = new \GuzzleHttp\Client();
        $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
            'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            'json'    => [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'audio',
                'audio'             => ['link' => $link],
            ],
        ]);

        self::log($numero, Auth::id(), 'audio', null, $business_phone_number_id);
    }

    public static function enviarAudioId($business_phone_number_id, $numero, $mediaId)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
                'json'    => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $numero,
                    'type'              => 'audio',
                    'audio'             => ['id' => $mediaId],
                ],
            ]);

            self::log($numero, Auth::id(), 'audio', null, $business_phone_number_id);
            return [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $err = json_decode($e->getResponse()->getBody(), true);
            return ['erro' => 1, 'msg' => $err['error']['message'] ?? 'Erro ao enviar áudio'];
        } catch (\Exception $e) {
            return ['erro' => 1, 'msg' => $e->getMessage()];
        }
    }

    public static function enviarVideo($business_phone_number_id, $numero, $link, $desc = 'video')
    {
        $client = new \GuzzleHttp\Client();
        $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
            'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            'json'    => [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'video',
                'video'             => ['link' => $link, 'caption' => $desc],
            ],
        ]);

        self::log($numero, Auth::id(), $desc, null, $business_phone_number_id);
    }

    public static function enviarDoc($business_phone_number_id, $numero, $link, $desc = 'arquivo')
    {
        $client = new \GuzzleHttp\Client();
        $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/messages", [
            'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            'json'    => [
                'messaging_product' => 'whatsapp',
                'to'                => $numero,
                'type'              => 'document',
                'document'          => ['link' => $link, 'caption' => $desc],
            ],
        ]);

        self::log($numero, Auth::id(), $desc, null, $business_phone_number_id);
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

    // ── Notificações do sistema ───────────────────────────────────────────────

    public static function notificarAgendamento(string $nomeCliente, string $dataHora, string $servico): void
    {
        self::enviarTemplateAdmin('agendamento_novo', [
            ['type' => 'text', 'text' => $nomeCliente],
            ['type' => 'text', 'text' => $dataHora],
            ['type' => 'text', 'text' => $servico],
        ]);
    }

    public static function notificarCancelamento(string $nomeCliente, string $dataHora): void
    {
        self::enviarTemplateAdmin('agendamento_cancelado', [
            ['type' => 'text', 'text' => $nomeCliente],
            ['type' => 'text', 'text' => $dataHora],
        ]);
    }

    private static function enviarTemplateAdmin(string $template, array $parametros): void
    {
        $admin = env('WHATSAPP_ADMIN_NUMBER');
        if (!$admin) return;

        try {
            $client = new \GuzzleHttp\Client();
            $client->request('POST', "https://graph.facebook.com/v25.0/" . env('PHONE_NUMBER_ID') . "/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to'                => $admin,
                    'type'              => 'template',
                    'template'          => [
                        'name'       => $template,
                        'language'   => ['code' => 'pt_BR'],
                        'components' => [
                            ['type' => 'body', 'parameters' => $parametros],
                        ],
                    ],
                ],
            ]);

            self::log($admin, null, "Template {$template}", null, env('PHONE_NUMBER_ID'));
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error("WhatsApp {$template}: " . $th->getMessage());
        }
    }

    // ── Upload de mídia ───────────────────────────────────────────────────────

    public static function uploadMidia($business_phone_number_id, $filePath, $mimeType)
    {
        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->request('POST', "https://graph.facebook.com/v25.0/{$business_phone_number_id}/media", [
                'headers'   => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
                'multipart' => [
                    ['name' => 'messaging_product', 'contents' => 'whatsapp'],
                    ['name' => 'type',              'contents' => $mimeType],
                    ['name' => 'file',              'contents' => fopen($filePath, 'r'), 'filename' => basename($filePath)],
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            return ['id' => $body['id'] ?? null];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $err = json_decode($e->getResponse()->getBody(), true);
            return ['erro' => 1, 'msg' => $err['error']['message'] ?? 'Erro no upload de mídia'];
        } catch (\Exception $e) {
            return ['erro' => 1, 'msg' => $e->getMessage()];
        }
    }

    // ── Download de mídias recebidas ──────────────────────────────────────────

    public static function getImage($msg)
    {
        try {
            $imgId    = $msg['image']['id'];
            $imgMime  = explode('/', $msg['image']['mime_type'])[1];
            $filename = "{$imgId}.{$imgMime}";

            $client   = new \GuzzleHttp\Client();
            $response = $client->request('GET', "https://graph.facebook.com/v25.0/{$imgId}", [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            ]);

            $mediaData = json_decode($response->getBody(), true);
            $imagem    = $client->get($mediaData['url'], [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            ]);

            Storage::disk('public')->put('whatsapp/' . $filename, $imagem->getBody());
            return $filename;
        } catch (\Throwable $th) {}
    }

    public static function getAudio($msg)
    {
        try {
            $audId    = $msg['audio']['id'];
            $audMime  = explode('/', $msg['audio']['mime_type'])[1];
            $filename = "{$audId}.{$audMime}";

            $client   = new \GuzzleHttp\Client();
            $response = $client->request('GET', "https://graph.facebook.com/v25.0/{$audId}", [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            ]);

            $mediaData = json_decode($response->getBody(), true);
            $audio     = $client->get($mediaData['url'], [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            ]);

            Storage::disk('public')->put('whatsapp/' . $filename, $audio->getBody());
            return $filename;
        } catch (\Throwable $th) {}
    }

    public static function getVideo($msg)
    {
        try {
            $vidId    = $msg['video']['id'];
            $vidMime  = explode('/', $msg['video']['mime_type'])[1];
            $filename = "{$vidId}.{$vidMime}";

            $client   = new \GuzzleHttp\Client();
            $response = $client->request('GET', "https://graph.facebook.com/v25.0/{$vidId}", [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            ]);

            $mediaData = json_decode($response->getBody(), true);
            $video     = $client->get($mediaData['url'], [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            ]);

            Storage::disk('public')->put('whatsapp/' . $filename, $video->getBody());
            return $filename;
        } catch (\Throwable $th) {}
    }

    public static function getDocument($msg)
    {
        try {
            $docId    = $msg['document']['id'];
            $docMime  = explode('/', $msg['document']['mime_type'])[1];
            $filename = "{$docId}.{$docMime}";

            $client   = new \GuzzleHttp\Client();
            $response = $client->request('GET', "https://graph.facebook.com/v25.0/{$docId}", [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            ]);

            $mediaData = json_decode($response->getBody(), true);
            $document  = $client->get($mediaData['url'], [
                'headers' => ['Authorization' => 'Bearer ' . env('GRAPH_API_TOKEN')],
            ]);

            Storage::disk('public')->put('whatsapp/' . $filename, $document->getBody());
            return $filename;
        } catch (\Throwable $th) {}
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

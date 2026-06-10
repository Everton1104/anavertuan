<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsappController;
use App\Models\AgendamentoModel;
use App\Models\Aviso;
use App\Models\CreditoServico;
use App\Models\DisponibilidadeModel;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgendaController extends Controller
{
    public function timeToMinutes($time)
    {
        $parts = explode(':', $time);
        return ($parts[0] * 60) + $parts[1];
    }

    // ── API: dias com pelo menos 1 slot disponível no mês ────────────────────
    public function diasDisponiveis($ano, $mes)
    {
        $dias = DisponibilidadeModel::whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->selectRaw('DAY(data) as dia')
            ->distinct()
            ->pluck('dia')
            ->values();

        return response()->json($dias);
    }

    // ── API: todos os slots do dia com status e quem está agendado ───────────
    public function getSlotsDia($data)
    {
        $user = auth()->user();
        if (!$user || (!$user->adm && !$user->func)) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $slotsDisponiveis = DisponibilidadeModel::where('data', $data)
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        $consultas = AgendamentoModel::with(['user', 'servico', 'creditoServico.servico'])
            ->whereDate('data_inicio', $data)
            ->orderBy('data_inicio')
            ->get();

        $slots = [];

        // Exibir das 06:00 às 22:00 em intervalos de 15 min (64 slots)
        $cursor = Carbon::parse($data . ' 06:00');
        $fim    = Carbon::parse($data . ' 22:00');

        while ($cursor < $fim) {
            $hora         = $cursor->format('H:i');
            $disponivel   = in_array($hora, $slotsDisponiveis);
            $agendamentos = [];

            foreach ($consultas as $c) {
                $inicioC = Carbon::parse($c->data_inicio);
                $fimC    = Carbon::parse($c->data_fim);
                if ($cursor >= $inicioC && $cursor < $fimC) {
                    $agendamentos[] = [
                        'paciente'  => $c->user->name,
                        'servico'   => $c->servico_display,
                        'inicio'    => $inicioC->format('H:i'),
                        'fim'       => $fimC->format('H:i'),
                        // Serviços de staff são "transparentes": não bloqueiam o slot.
                        'is_staff'  => !(optional($c->servico)->visivel_cliente),
                    ];
                }
            }

            $slots[] = [
                'hora'         => $hora,
                'disponivel'   => $disponivel,
                'agendamentos' => $agendamentos,
            ];

            $cursor->addMinutes(15);
        }

        return response()->json($slots);
    }

    // ── API: salvar/substituir todos os slots de um dia ──────────────────────
    public function salvarSlots(Request $request, $data)
    {
        $user = auth()->user();
        if (!$user || (!$user->adm && !$user->func)) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $slots = $request->slots ?? [];

        // Remove todos os slots do dia e recria com os selecionados
        DisponibilidadeModel::where('data', $data)->delete();

        foreach ($slots as $hora) {
            if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
                DisponibilidadeModel::create([
                    'data'       => $data,
                    'hora'       => $hora . ':00',
                    'created_by' => $user->id,
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    // ── API: horários disponíveis para agendamento (para o calendário) ───────
    public function horarios($data)
    {
        $authUser = auth()->user();
        $isStaff  = $authUser && ($authUser->adm || $authUser->func);

        $slotsDisponiveis = DisponibilidadeModel::where('data', $data)
            ->orderBy('hora')
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        if (empty($slotsDisponiveis)) {
            return response()->json([]);
        }

        $servico = ServicosModel::find(request('servico_id'));

        if (!$servico) {
            return response()->json(['error' => 'Serviço não encontrado'], 422);
        }

        // Clientes não podem agendar serviços restritos ao staff
        if (!$isStaff && !$servico->visivel_cliente) {
            return response()->json(['error' => 'Serviço não disponível'], 403);
        }

        // Serviços de staff podem ser colocados sobre outros horários sem conflitar com eles
        $servicoEhStaff = !$servico->visivel_cliente;

        // Clientes só enxergam slots em :00 e :30
        if (!$isStaff) {
            $slotsDisponiveis = array_values(array_filter(
                $slotsDisponiveis,
                fn($h) => in_array(substr($h, 3, 2), ['00', '30'])
            ));
        }

        $ignoreId  = request('ignore_id');
        $consultas = AgendamentoModel::with('servico')
            ->whereDate('data_inicio', $data)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('data_inicio')
            ->get();

        $duracaoMin      = $this->timeToMinutes($servico->duracao);
        $intervalo       = $isStaff ? 15 : 30;
        $slotsNecessarios = (int) ceil($duracaoMin / $intervalo);

        $agora = Carbon::now();

        $horarios = [];

        foreach ($slotsDisponiveis as $hora) {
            $inicio = Carbon::parse($data . ' ' . $hora);

            if ($inicio <= $agora) {
                $horarios[] = [
                    'hora'    => $hora,
                    'ocupado' => true,
                    'motivo'  => 'Horário já passou',
                ];
                continue;
            }

            // Clientes só podem agendar com no mínimo 2h de antecedência (tempo de deslocamento da nutri).
            if (!$isStaff && $inicio < $agora->copy()->addHours(2)) {
                $horarios[] = [
                    'hora'    => $hora,
                    'ocupado' => true,
                    'motivo'  => 'Antecedência mínima de 2h',
                ];
                continue;
            }

            $fimTeorico = $inicio->copy()->addMinutes($duracaoMin);
            $ocupado    = false;
            $motivo     = null;

            // 1. Slot diretamente ocupado por um agendamento
            // Serviços de staff não conflitam: nem bloqueiam nem são bloqueados por outros agendamentos.
            if (!$servicoEhStaff) {
                foreach ($consultas as $c) {
                    if (!optional($c->servico)->visivel_cliente) continue; // agendamento de staff não bloqueia
                    $inicioC = Carbon::parse($c->data_inicio);
                    $fimC    = Carbon::parse($c->data_fim);
                    if ($inicio >= $inicioC && $inicio < $fimC) {
                        $ocupado = true;
                        $motivo  = 'Horário já reservado';
                        break;
                    }
                }
            }

            // 2. Slots consecutivos necessários estão todos disponíveis?
            if (!$ocupado && $slotsNecessarios > 1) {
                for ($i = 1; $i < $slotsNecessarios; $i++) {
                    $slotCheck = $inicio->copy()->addMinutes($intervalo * $i)->format('H:i');
                    if (!in_array($slotCheck, $slotsDisponiveis)) {
                        $ocupado = true;
                        $motivo  = 'Não comporta a duração do serviço';
                        break;
                    }
                }
            }

            // 3. Duração conflita com outro agendamento?
            if (!$ocupado && !$servicoEhStaff) {
                foreach ($consultas as $c) {
                    if (!optional($c->servico)->visivel_cliente) continue; // agendamento de staff não bloqueia
                    $inicioC = Carbon::parse($c->data_inicio);
                    $fimC    = Carbon::parse($c->data_fim);
                    if ($inicio < $fimC && $fimTeorico > $inicioC) {
                        $ocupado = true;
                        $motivo  = 'A duração conflita com outro agendamento';
                        break;
                    }
                }
            }

            $horarios[] = [
                'hora'    => $hora,
                'ocupado' => $ocupado,
                'motivo'  => $motivo,
            ];
        }

        return response()->json($horarios);
    }

    // ── Criar / editar agendamento ────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate(
            [
                'user_id'     => ['required'],
                'servico_id'  => ['required', \Illuminate\Validation\Rule::exists('servicos', 'id')->where('excluido', 0)],
                'data_inicio' => ['required', 'date', 'after:now'],
            ],
            [
                'user_id.required'     => 'Selecione o cliente.',
                'servico_id.required'  => 'Selecione o serviço.',
                'servico_id.exists'    => 'Serviço inválido.',
                'data_inicio.required' => 'Selecione um dia e horário.',
                'data_inicio.date'     => 'Data inválida.',
                'data_inicio.after'    => 'O agendamento deve ser em uma data futura.',
            ]
        );

        $authUser = auth()->user();
        $isStaff  = $authUser->adm || $authUser->func;
        $ignoreId = $request->agendamento_id;

        $servico = ServicosModel::where('excluido', 0)->find($request->servico_id);
        if (!$servico) {
            return back()->withErrors(['servico_id' => 'Serviço inválido.'])->withInput();
        }

        // Clientes: buscar agendamento original uma única vez (reutilizado nas validações abaixo)
        $agendamentoOriginal = null;
        if (!$isStaff) {
            $request->merge(['user_id' => $authUser->id]);

            if (!$ignoreId) {
                abort(403); // Clientes só podem reagendar
            }

            $agendamentoOriginal = AgendamentoModel::where('id', $ignoreId)
                ->where('user_id', $authUser->id)
                ->firstOrFail();

            // Serviço não pode ser alterado no reagendamento
            if ((int) $request->servico_id !== (int) $agendamentoOriginal->servico_id) {
                return back()
                    ->withErrors(['data_inicio' => 'Não é possível alterar o serviço no reagendamento.'])
                    ->withInput();
            }
        }

        // Clientes só podem usar serviços visíveis; exceção: reagendar com o serviço original
        if (!$isStaff && !$servico->visivel_cliente) {
            if (!$agendamentoOriginal || (int) $agendamentoOriginal->servico_id !== (int) $servico->id) {
                abort(403);
            }
        }

        $duracao   = $this->timeToMinutes($servico->duracao);
        $intervalo = $isStaff ? 15 : 30;
        $inicio    = Carbon::parse($request->data_inicio);
        $fim       = $inicio->copy()->addMinutes($duracao);

        // Clientes só podem agendar com no mínimo 2h de antecedência (tempo de deslocamento da nutri).
        if (!$isStaff && $inicio < Carbon::now()->addHours(2)) {
            return back()
                ->withErrors(['data_inicio' => 'Só é possível agendar com no mínimo 2 horas de antecedência.'])
                ->withInput();
        }

        // Buscar todos os slots disponíveis do dia
        $slotsDisponiveis = DisponibilidadeModel::where('data', $inicio->toDateString())
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        // Clientes só podem usar slots em :00 e :30
        if (!$isStaff) {
            $slotsDisponiveis = array_values(array_filter(
                $slotsDisponiveis,
                fn($h) => in_array(substr($h, 3, 2), ['00', '30'])
            ));
        }

        // Verificar se o slot inicial está disponível
        if (!in_array($inicio->format('H:i'), $slotsDisponiveis)) {
            return back()->withErrors(['data_inicio' => 'Este horário não está disponível'])->withInput();
        }

        // Verificar se todos os slots necessários estão disponíveis
        $slotsNecessarios = (int) ceil($duracao / $intervalo);
        for ($i = 1; $i < $slotsNecessarios; $i++) {
            $slotCheck = $inicio->copy()->addMinutes($intervalo * $i)->format('H:i');
            if (!in_array($slotCheck, $slotsDisponiveis)) {
                return back()->withErrors(['data_inicio' => 'O serviço ultrapassa os horários disponíveis'])->withInput();
            }
        }

        // Serviços de staff podem ser colocados sobre outros horários sem conflitar.
        // Demais serviços só conflitam com agendamentos de clientes (os de staff são "transparentes").
        $existeConflito = $servico->visivel_cliente && AgendamentoModel::where(function ($q) use ($inicio, $fim) {
                $q->where('data_inicio', '<', $fim)
                  ->where('data_fim', '>', $inicio);
            })
            ->whereHas('servico', fn($q) => $q->where('visivel_cliente', 1))
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($existeConflito) {
            return back()
                ->withErrors(['data_inicio' => 'A duração deste serviço conflita com outro agendamento'])
                ->withInput();
        }

        $dataAntiga = null;
        if ($ignoreId) {
            $agendamento = $agendamentoOriginal ?? AgendamentoModel::findOrFail($ignoreId);
            $dataAntiga  = $agendamento->data_inicio;
        } else {
            $agendamento = new AgendamentoModel();
        }

        $isEdicao = !empty($ignoreId);

        // Créditos de serviço: consome 1 unidade do pacote (credito_id = linha de
        // creditos_servico; o cliente pode ter vários pacotes do mesmo serviço).
        // Cancelar (destroy) devolve automaticamente (a linha some da contagem).
        // - Serviços visíveis ao cliente EXIGEM um pacote com saldo (do próprio serviço).
        // - Serviços de staff (ex.: especial) podem descontar de qualquer pacote do
        //   cliente ou ser livres (sem desconto).
        // Na edição, só o staff pode trocar o pacote; mantido o mesmo, não revalida
        // (o pacote pode estar zerado justamente por este agendamento). Cliente
        // reagendando não envia credito_id e não altera o desconto.
        $creditoAtualId = $isEdicao ? $agendamento->credito_servico_id : null;
        $creditoNovoId  = $request->credito_id ?: null;
        $alterarCredito = !$isEdicao
            || ($isStaff && (string) ($creditoNovoId ?? '') !== (string) ($creditoAtualId ?? ''));

        if ($alterarCredito) {
            $credito = null;

            if ($creditoNovoId) {
                $credito = CreditoServico::where('user_id', $request->user_id)->find($creditoNovoId);

                if (!$credito || $credito->restantes() <= 0) {
                    return back()
                        ->withErrors(['servico_id' => 'O pacote escolhido para desconto não possui saldo.'])
                        ->withInput();
                }
            }

            if ($servico->visivel_cliente) {
                // Serviço visível: o pacote deve ser do próprio serviço.
                if (!$credito || (int) $credito->servico_id !== (int) $servico->id) {
                    return back()
                        ->withErrors(['servico_id' => 'O cliente não possui saldo disponível para este serviço.'])
                        ->withInput();
                }
            }

            $agendamento->credito_servico_id = $credito?->id;
            $agendamento->consome_credito    = (bool) $credito;
        }

        $agendamento->user_id     = $request->user_id;
        $agendamento->servico_id  = $servico->id;
        $agendamento->data_inicio = $inicio;
        $agendamento->data_fim    = $fim;
        $agendamento->confirmado  = 0;
        $agendamento->save();

        if ($isStaff) {
            $agendamento->load(['user', 'servico']);
            $this->notificarWhatsApp($agendamento, $isEdicao);
        } elseif ($isEdicao && $dataAntiga) {
            Aviso::create([
                'tipo'        => 'reagendamento',
                'user_id'     => $authUser->id,
                'servico_id'  => $servico->id,
                'data_antiga' => $dataAntiga,
                'data_nova'   => $inicio,
            ]);
        }

        return redirect()->back()->with('msg', 'Agendamento salvo com sucesso');
    }

    private function notificarWhatsApp(AgendamentoModel $agendamento, bool $isEdicao): void
    {
        $user    = $agendamento->user;
        $phoneId = env('PHONE_NUMBER_ID');

        if (!$user || !$user->whatsapp || !$phoneId) return;

        $nome    = ucfirst($user->name);
        $data    = Carbon::parse($agendamento->data_inicio)
                       ->locale('pt_BR')
                       ->translatedFormat('d \d\e F \d\e Y');
        $hora    = Carbon::parse($agendamento->data_inicio)->format('H:i');
        $servico = $agendamento->servico->descricao ?? '';

        // Pacote descontado: mostra o saldo restante "Serviço X (2/5)". Especial que
        // descontou de outro serviço mostra o nome do serviço descontado. Cortesia
        // (sem desconto) mantém o nome original sem contador.
        $credito = $agendamento->creditoServico;
        if ($credito) {
            if ($credito->servico_id != $agendamento->servico_id) {
                $servico = $credito->servico->descricao ?? $servico;
            }
            $servico .= ' (' . $credito->restantes() . '/' . $credito->quantidade . ')';
        }

        if ($isEdicao) {
            // confirmacao_reagendamento: nome, nome_comercial, data, hora
            WhatsappController::enviarModelo($phoneId, $user->whatsapp, 'confirmacao_reagendamento', [
                ['type' => 'text', 'text' => $nome],
                ['type' => 'text', 'text' => env('WHATSAPP_NOME_COMERCIAL', config('app.name'))],
                ['type' => 'text', 'text' => $data],
                ['type' => 'text', 'text' => $hora],
            ]);
        } else {
            // confirmacao_agendamento: nome, data+hora, servico, numero de confirmacao
            WhatsappController::enviarModelo($phoneId, $user->whatsapp, 'confirmacao_agendamento', [
                ['type' => 'text', 'text' => $nome],
                ['type' => 'text', 'text' => $data . ' às ' . $hora],
                ['type' => 'text', 'text' => $servico],
                ['type' => 'text', 'text' => (string) $agendamento->id],
            ]);
        }
    }

    // Reenvia manualmente (pela equipe) o lembrete com os botões confirmar/reagendar.
    public function reenviarLembrete($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $agendamento = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);
        $cliente     = $agendamento->user;
        $phoneId     = env('PHONE_NUMBER_ID');
        $template    = env('WHATSAPP_TEMPLATE_LEMBRETE');

        if (!$cliente || !$cliente->whatsapp) {
            return response()->json(['error' => 'Cliente sem WhatsApp cadastrado.'], 422);
        }
        if (!$phoneId || !$template) {
            return response()->json(['error' => 'WhatsApp não configurado.'], 422);
        }

        $nome          = ucfirst($cliente->name);
        $nomeComercial = env('WHATSAPP_NOME_COMERCIAL', config('app.name'));
        $data          = Carbon::parse($agendamento->data_inicio)
                             ->locale('pt_BR')
                             ->translatedFormat('d \d\e F \d\e Y');
        $hora          = Carbon::parse($agendamento->data_inicio)->format('H:i');

        $resultado = WhatsappController::enviarModelo($phoneId, $cliente->whatsapp, $template, [
            ['type' => 'text', 'text' => $nome],
            ['type' => 'text', 'text' => $nomeComercial],
            ['type' => 'text', 'text' => $data],
            ['type' => 'text', 'text' => $hora],
        ], 'pt_BR', [
            'confirmar_' . $agendamento->id,
            'reagendar_' . $agendamento->id,
        ]);

        if (isset($resultado['erro'])) {
            return response()->json(['error' => $resultado['msg'] ?? 'Falha ao enviar lembrete.'], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function confirmar($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $agendamento = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);
        $agendamento->confirmado    = 1;
        $agendamento->confirmado_em = now();
        $agendamento->save();

        $this->notificarWhatsApp($agendamento, false);

        return response()->json(['ok' => true, 'confirmado_em' => $agendamento->confirmado_em->format('d/m H:i')]);
    }

    public function edit($id)
    {
        $user        = auth()->user();
        $agendamento = AgendamentoModel::findOrFail($id);

        if (!$user->adm && !$user->func && $agendamento->user_id !== $user->id) {
            abort(403);
        }

        return $agendamento;
    }

    public function destroy($id)
    {
        $user        = auth()->user();
        $agendamento = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);

        if (!$user->adm && !$user->func) {
            if ($agendamento->user_id !== $user->id) {
                abort(403);
            }
            if (!$agendamento->data_inicio->isFuture()) {
                return response()->json(['error' => 'Não é possível cancelar consultas passadas.'], 422);
            }
            Aviso::create([
                'tipo'        => 'cancelamento',
                'user_id'     => $user->id,
                'servico_id'  => $agendamento->servico_id,
                'data_antiga' => $agendamento->data_inicio,
            ]);
            $this->notificarStaffCancelamento($agendamento);
        }

        $agendamento->delete();
        return response()->json(['ok' => true]);
    }

    // Retorna apenas o HTML da lista de avisos (para atualização parcial via axios).
    public function avisosParcial()
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $avisos = Aviso::with(['user', 'servico'])
            ->whereNull('dispensado_at')
            ->latest()
            ->get();

        return response()->json([
            'count' => $avisos->count(),
            'html'  => view('partials.avisos', compact('avisos'))->render(),
        ]);
    }

    public function dispensarAviso($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }
        Aviso::findOrFail($id)->update(['dispensado_at' => now()]);
        return response()->json(['ok' => true]);
    }

    private function notificarStaffCancelamento(AgendamentoModel $agendamento): void
    {
        $phoneId = env('PHONE_NUMBER_ID');
        if (!$phoneId) return;

        $staffUsers = User::where(function ($q) {
                $q->where('adm', 1)->orWhere('func', 1);
            })
            ->whereNotNull('whatsapp')
            ->where('excluido', 0)
            ->get();

        $nomePaciente = ucfirst($agendamento->user->name ?? '');
        $data    = Carbon::parse($agendamento->data_inicio)->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y');
        $hora    = Carbon::parse($agendamento->data_inicio)->format('H:i');
        $servico = $agendamento->servico->descricao ?? '';

        foreach ($staffUsers as $staff) {
            WhatsappController::enviarModelo($phoneId, $staff->whatsapp, 'aviso_cancelamento', [
                ['type' => 'text', 'text' => $nomePaciente],
                ['type' => 'text', 'text' => $data . ' às ' . $hora],
                ['type' => 'text', 'text' => $servico],
            ]);
        }
    }

    public function search(Request $request)
    {
        $user = auth()->user();
        $q    = trim($request->q);

        if (!$user->adm && !$user->func) {
            abort(403);
        }

        $consultas = AgendamentoModel::with([
                'user',
                'servico',
                'lembretes',
                'creditoServico' => fn($q) => $q->with('servico')->withCount('agendamentos'),
            ])
            ->when($q !== '', fn($query) =>
                $query->whereHas('user', fn($u) => $u->where('name', 'like', "%{$q}%"))
            )
            ->orderBy('data_inicio')
            ->get()
            ->groupBy(fn($item) =>
                Carbon::parse($item->data_inicio)->locale('pt_BR')->translatedFormat('F Y')
            );

        return response()->json($consultas);
    }
}

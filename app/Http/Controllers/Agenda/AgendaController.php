<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsappController;
use App\Models\AgendamentoModel;
use App\Models\Aviso;
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

        $consultas = AgendamentoModel::with(['user', 'servico'])
            ->whereDate('data_inicio', $data)
            ->orderBy('data_inicio')
            ->get();

        $slots = [];

        // Exibir das 06:00 às 22:00 em intervalos de 15 min (64 slots)
        $cursor = Carbon::parse($data . ' 06:00');
        $fim    = Carbon::parse($data . ' 22:00');

        while ($cursor < $fim) {
            $hora        = $cursor->format('H:i');
            $disponivel  = in_array($hora, $slotsDisponiveis);
            $agendamento = null;

            foreach ($consultas as $c) {
                $inicioC = Carbon::parse($c->data_inicio);
                $fimC    = Carbon::parse($c->data_fim);
                if ($cursor >= $inicioC && $cursor < $fimC) {
                    $agendamento = [
                        'paciente' => $c->user->name,
                        'servico'  => $c->servico->descricao ?? '',
                        'inicio'   => $inicioC->format('H:i'),
                        'fim'      => $fimC->format('H:i'),
                    ];
                    break;
                }
            }

            $slots[] = [
                'hora'        => $hora,
                'disponivel'  => $disponivel,
                'agendamento' => $agendamento,
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

        // Clientes só enxergam slots em :00 e :30
        if (!$isStaff) {
            $slotsDisponiveis = array_values(array_filter(
                $slotsDisponiveis,
                fn($h) => in_array(substr($h, 3, 2), ['00', '30'])
            ));
        }

        $ignoreId  = request('ignore_id');
        $consultas = AgendamentoModel::whereDate('data_inicio', $data)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('data_inicio')
            ->get();

        $duracaoMin      = $this->timeToMinutes($servico->duracao);
        $intervalo       = $isStaff ? 15 : 30;
        $slotsNecessarios = (int) ceil($duracaoMin / $intervalo);

        $agora = Carbon::now();
        $hoje  = $agora->toDateString();

        $horarios = [];

        foreach ($slotsDisponiveis as $hora) {
            $inicio = Carbon::parse($data . ' ' . $hora);

            if ($data === $hoje && $inicio <= $agora) {
                $horarios[] = [
                    'hora'    => $hora,
                    'ocupado' => true,
                    'motivo'  => 'Horário já passou',
                ];
                continue;
            }

            $fimTeorico = $inicio->copy()->addMinutes($duracaoMin);
            $ocupado    = false;
            $motivo     = null;

            // 1. Slot diretamente ocupado por um agendamento
            foreach ($consultas as $c) {
                $inicioC = Carbon::parse($c->data_inicio);
                $fimC    = Carbon::parse($c->data_fim);
                if ($inicio >= $inicioC && $inicio < $fimC) {
                    $ocupado = true;
                    $motivo  = 'Horário já reservado';
                    break;
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
            if (!$ocupado) {
                foreach ($consultas as $c) {
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

        $existeConflito = AgendamentoModel::where(function ($q) use ($inicio, $fim) {
                $q->where('data_inicio', '<', $fim)
                  ->where('data_fim', '>', $inicio);
            })
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

    public function confirmar($id)
    {
        $user = auth()->user();
        if (!$user->adm && !$user->func) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $agendamento = AgendamentoModel::with(['user', 'servico'])->findOrFail($id);
        $agendamento->confirmado = 1;
        $agendamento->save();

        $this->notificarWhatsApp($agendamento, false);

        return response()->json(['ok' => true]);
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

        $consultas = AgendamentoModel::with(['user', 'servico'])
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

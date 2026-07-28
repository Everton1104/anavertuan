<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\AplicacaoMounjaro;
use App\Models\DoseMounjaro;
use App\Models\FornecedorMounjaro;
use Illuminate\Http\Request;

class AplicacoesController extends Controller
{
    private function adminOnly(): void
    {
        if (!auth()->user()?->adm) abort(403);
    }

    public function index()
    {
        $this->adminOnly();

        // ── Fornecedores (compras) + custo médio global (referência/fallback) ──
        $fornecedores = FornecedorMounjaro::orderBy('data_compra', 'desc')->get();

        $uiComprado      = $fornecedores->sum(fn($f) => $f->total_ui);
        $totalFornecedor = $fornecedores->sum('valor_total');
        // Custo médio ponderado global: usado só como FALLBACK para doses sem lote
        // amarrado. O custo real de cada consulta vem do lote (custo_por_ui do
        // fornecedor), então o lucro não é mais reescrito retroativamente.
        $custoPorUi = $uiComprado > 0 ? $totalFornecedor / $uiComprado : 0;

        // Saldo disponível por lote: total_ui do lote − UI já aplicada nas doses
        // amarradas a ele. Usado na coluna "UI disponível" da tabela de fornecedores.
        $uiPorFornecedor = DoseMounjaro::whereNotNull('fornecedor_id')
            ->whereNotNull('ui')
            ->selectRaw('fornecedor_id, SUM(ui) as usada')
            ->groupBy('fornecedor_id')
            ->pluck('usada', 'fornecedor_id');
        $saldoPorFornecedor = $fornecedores->mapWithKeys(
            fn($f) => [$f->id => $f->total_ui - ($uiPorFornecedor[$f->id] ?? 0)]
        );

        // Lista de lotes para o <select> de cada consulta (label com saldo).
        $fornecedoresLista = $fornecedores->map(fn($f) => [
            'id'    => $f->id,
            'label' => $f->fornecedor . ' — ' . $f->data_compra->format('d/m/Y')
                     . ' (' . ($saldoPorFornecedor[$f->id] ?? 0) . ' UI)',
        ])->values();

        // ── Consultas (doses) vindas da agenda ────────────────────────────────
        // Cada agendamento de um serviço Mounjaro é uma consulta; o valor pago e o
        // UI aplicado são lançados manualmente (variam por paciente/semana), pois
        // preço e dosagem são independentes.
        $agendamentos = AgendamentoModel::with(['servico', 'user', 'creditoServico.agendamentos'])
            ->whereHas('servico', fn($q) => $q->where('mounjaro', true))
            ->orderBy('data_inicio')
            ->get()
            ->filter(fn($ag) => $ag->user); // ignora agendamentos órfãos de usuário

        $dosesPorAgendamento = DoseMounjaro::with('fornecedor')
            ->whereNotNull('agendamento_id')
            ->get()->keyBy('agendamento_id');

        // Custo unitário efetivo de uma dose: do lote amarrado, ou fallback global.
        $custoEfetivo = function ($dose) use ($custoPorUi) {
            if ($dose && $dose->fornecedor_id) {
                return $dose->fornecedor?->custo_por_ui ?? $custoPorUi;
            }
            return $custoPorUi;
        };

        // ── Agrupamento por mês (compras + vendas + lucro por margem real) ────
        $mesesConsultas = $agendamentos->map(fn($ag) => $ag->data_inicio->format('Y-m'));
        $mesesCompras   = $fornecedores->map(fn($f) => $f->data_compra->format('Y-m'));
        $meses          = $mesesConsultas->merge($mesesCompras)->unique()->sortDesc()->values();

        $balancoMeses = $meses->map(function ($ym) use ($agendamentos, $fornecedores, $dosesPorAgendamento, $custoEfetivo) {
            $ref = \Carbon\Carbon::createFromFormat('Y-m-d', $ym . '-01');

            $consultas = $agendamentos
                ->filter(fn($ag) => $ag->data_inicio->format('Y-m') === $ym)
                ->sortBy('data_inicio')
                ->map(function ($ag) use ($dosesPorAgendamento, $custoEfetivo) {
                    $dose    = $dosesPorAgendamento->get($ag->id);
                    $credito = $ag->creditoServico;
                    $ui      = $dose?->ui ?? 0;
                    $cu      = $custoEfetivo($dose);            // lote ou fallback
                    $custo   = $ui * $cu;                        // custo do medicamento desta consulta
                    $valor   = $dose?->valor_pago ?? 0;
                    return (object) [
                        'agendamento_id' => $ag->id,
                        'user_id'        => $ag->user_id,
                        'data'           => $ag->data_inicio,
                        'cliente'        => ucfirst($ag->user->name),
                        'servico'        => $ag->servico->descricao ?? '—',
                        // Posição da consulta no pacote / total comprado (ex.: 2/5).
                        'unidades'       => $credito
                            ? $credito->ordinalDe($ag) . '/' . $credito->quantidade
                            : '1/1',
                        'ui'             => $dose?->ui,
                        'valor_pago'     => $dose?->valor_pago,
                        'fornecedor_id'  => $dose?->fornecedor_id,
                        'custo_unitario' => $cu,
                        'custo'          => $custo,
                        'margem'         => $valor - $custo,     // lucro real desta consulta
                    ];
                })->values();

            $compras       = $fornecedores->filter(fn($f) => $f->data_compra->format('Y-m') === $ym);
            $compradoValor = $compras->sum('valor_total');
            $compradoUi    = $compras->sum(fn($f) => $f->total_ui);

            $vendidoValor = $consultas->sum(fn($c) => $c->valor_pago ?? 0);
            $uiVendido    = $consultas->sum(fn($c) => $c->ui ?? 0);
            // Lucro do mês = Σ margens (custo do medicamento por consulta, pelo lote).
            $custoMes = $consultas->sum('custo');
            $lucro    = $vendidoValor - $custoMes;

            return (object) [
                'ym'             => $ym,
                'label'          => ucfirst($ref->locale('pt_BR')->translatedFormat('F Y')),
                'consultas'      => $consultas,
                'comprado_valor' => $compradoValor,
                'comprado_ui'    => $compradoUi,
                'vendido_valor'  => $vendidoValor,
                'ui_vendido'     => $uiVendido,
                'custo'          => $custoMes,
                'lucro'          => $lucro,
            ];
        });

        $mesAtual = now()->format('Y-m');

        // ── Rentabilidade por paciente (todos os meses) ───────────────────────
        // Como preço e dosagem descasam, o lucro varia muito entre pacientes —
        // esta visão mostra quem subsidia quem. Nome já vem da consulta (sem N+1).
        $rentabilidadePorPaciente = $balancoMeses->flatMap(fn($m) => $m->consultas)
            ->groupBy('user_id')
            ->map(function ($cs) {
                $receita = $cs->sum(fn($c) => $c->valor_pago ?? 0);
                $custo   = $cs->sum('custo');
                $lucro   = $receita - $custo;
                return (object) [
                    'cliente'    => $cs->first()->cliente,
                    'consultas'  => $cs->count(),
                    'ui'         => $cs->sum(fn($c) => $c->ui ?? 0),
                    'receita'    => $receita,
                    'custo'      => $custo,
                    'lucro'      => $lucro,
                    'margem_pct' => $receita > 0 ? ($lucro / $receita) * 100 : 0,
                ];
            })
            ->sortByDesc('lucro')
            ->values();

        // ── Resumo global ─────────────────────────────────────────────────────
        $uiVendido     = $balancoMeses->sum('ui_vendido');
        $totalRecebido = $balancoMeses->sum('vendido_valor');
        $custoTotal    = $balancoMeses->sum('custo');
        $lucroTotal    = $totalRecebido - $custoTotal;
        $uiRestante    = $uiComprado - $uiVendido;

        // Mapa lote => custo_por_ui para o JS recalcular o custo ao trocar o select.
        $custoFornecedorJs = $fornecedores->mapWithKeys(fn($f) => [$f->id => $f->custo_por_ui]);

        return view('aplicacoes.index', compact(
            'fornecedores', 'fornecedoresLista', 'saldoPorFornecedor', 'balancoMeses', 'mesAtual',
            'custoPorUi', 'uiComprado', 'uiVendido', 'uiRestante',
            'totalRecebido', 'totalFornecedor', 'custoTotal', 'lucroTotal',
            'rentabilidadePorPaciente', 'custoFornecedorJs'
        ));
    }

    // ── Fornecedores ──────────────────────────────────────────────────────────

    public function storeFornecedor(Request $request)
    {
        $this->adminOnly();

        $data = $request->validate([
            'fornecedor'        => 'required|string|max:100',
            'data_compra'       => 'required|date',
            'produto'           => 'required|string|max:100',
            'ampolas_compradas' => 'required|integer|min:1',
            'ui_por_ampola'     => 'required|integer|min:1',
            'valor_total'       => 'required|numeric|min:0',
        ]);

        $id = $request->fornecedor_id;
        if ($id) {
            FornecedorMounjaro::findOrFail($id)->update($data);
            $msg = 'Fornecedor atualizado.';
        } else {
            FornecedorMounjaro::create($data);
            $msg = 'Fornecedor cadastrado.';
        }

        return redirect()->route('aplicacoes.index')->with('msg', $msg);
    }

    public function destroyFornecedor($id)
    {
        $this->adminOnly();
        // fornecedor_id nas doses é nullOnDelete; excluir não quebra nada.
        FornecedorMounjaro::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── Doses (valor pago + UI aplicado, por agendamento) ───────────────────────

    // Lança/atualiza o valor pago, o UI aplicado e o LOTE (fornecedor) de uma
    // consulta Mounjaro. Aceita atualização parcial (só UI, só valor ou só lote).
    // UI e valor vazios removem a dose. Sem lote informado, atribui FIFO (lote
    // mais antigo com saldo) para que o custo real fique amarrado automaticamente.
    public function salvarDose(Request $request, $agendamentoId)
    {
        $this->adminOnly();

        $data = $request->validate([
            'ui'           => 'nullable|integer|min:1',
            'valor_pago'   => 'nullable|numeric|min:0',
            'fornecedor_id'=> 'nullable|integer|exists:fornecedores_mounjaro,id',
        ]);

        $ag = AgendamentoModel::with('servico')->findOrFail($agendamentoId);
        abort_unless($ag->servico && $ag->servico->mounjaro, 422, 'Agendamento não é de serviço Mounjaro.');

        $dose = DoseMounjaro::where('agendamento_id', $agendamentoId)->first();

        // Mescla os campos enviados com o que já existe (atualização parcial).
        $ui         = $request->has('ui')           ? ($data['ui'] ?? null)          : $dose?->ui;
        $valor      = $request->has('valor_pago')   ? ($data['valor_pago'] ?? null)  : $dose?->valor_pago;
        $fornecedor = $request->has('fornecedor_id') ? ($data['fornecedor_id'] ?? null) : $dose?->fornecedor_id;

        // Nada lançado: limpa a dose.
        if (empty($ui) && ($valor === null || $valor === '')) {
            $dose?->delete();
            return response()->json(['ok' => true, 'ui' => null, 'valor_pago' => null, 'fornecedor_id' => null]);
        }

        // Sem lote definido: atribui o mais antigo com saldo disponível (FIFO).
        if (!$fornecedor) {
            $fornecedor = $this->loteFifo($ui ?? 0);
        }

        $aplicacao = AplicacaoMounjaro::firstOrCreate(
            ['user_id' => $ag->user_id],
            ['total_pago' => 0]
        );

        $payload = [
            'ui'           => $ui ?: null,
            'valor_pago'   => ($valor === null || $valor === '') ? null : $valor,
            'fornecedor_id'=> $fornecedor,
        ];

        if ($dose) {
            $dose->update($payload);
        } else {
            $dose = DoseMounjaro::create(array_merge($payload, [
                'aplicacao_id'   => $aplicacao->id,
                'agendamento_id' => $agendamentoId,
                'data_aplicacao' => $ag->data_inicio->toDateString(),
            ]));
        }

        return response()->json([
            'ok'            => true,
            'ui'            => $dose->ui,
            'valor_pago'    => $dose->valor_pago,
            'fornecedor_id' => $dose->fornecedor_id,
        ]);
    }

    // Lote mais antigo (FIFO) com saldo suficiente. Saldo = total_ui do lote − UI
    // já aplicada nas doses a ele amarradas. Retorna o id, ou null se nenhum lote
    // comportar (a dose fica sem lote e cai no fallback de custo médio global).
    private function loteFifo(int $uiNecessaria = 0): ?int
    {
        $usado = DoseMounjaro::whereNotNull('fornecedor_id')
            ->whereNotNull('ui')
            ->selectRaw('fornecedor_id, SUM(ui) as usada')
            ->groupBy('fornecedor_id')
            ->pluck('usada', 'fornecedor_id');

        $lote = FornecedorMounjaro::orderBy('data_compra', 'asc')->get()
            ->first(fn($f) => ($f->total_ui - ($usado[$f->id] ?? 0)) >= max(1, $uiNecessaria));

        return $lote?->id;
    }
}

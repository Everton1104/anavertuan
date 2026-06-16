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

        // ── Fornecedores (compras) + custo médio único ────────────────────────
        $fornecedores = FornecedorMounjaro::orderBy('data_compra', 'desc')->get();

        $uiComprado      = $fornecedores->sum(fn($f) => $f->total_ui);
        $totalFornecedor = $fornecedores->sum('valor_total');
        $custoPorUi      = $uiComprado > 0 ? $totalFornecedor / $uiComprado : 0;

        // ── Consultas (doses) vindas da agenda ────────────────────────────────
        // Cada agendamento de um serviço Mounjaro é uma consulta; o valor pago e o
        // UI aplicado são lançados manualmente (variam por paciente/semana).
        $agendamentos = AgendamentoModel::with(['servico', 'user', 'creditoServico.agendamentos'])
            ->whereHas('servico', fn($q) => $q->where('mounjaro', true))
            ->orderBy('data_inicio')
            ->get()
            ->filter(fn($ag) => $ag->user); // ignora agendamentos órfãos de usuário

        $dosesPorAgendamento = DoseMounjaro::whereNotNull('agendamento_id')
            ->get()->keyBy('agendamento_id');

        // ── Agrupamento por mês (compras + vendas + balanço) ──────────────────
        $mesesConsultas = $agendamentos->map(fn($ag) => $ag->data_inicio->format('Y-m'));
        $mesesCompras   = $fornecedores->map(fn($f) => $f->data_compra->format('Y-m'));
        $meses          = $mesesConsultas->merge($mesesCompras)->unique()->sortDesc()->values();

        $balancoMeses = $meses->map(function ($ym) use ($agendamentos, $fornecedores, $dosesPorAgendamento, $custoPorUi) {
            $ref = \Carbon\Carbon::createFromFormat('Y-m-d', $ym . '-01');

            $consultas = $agendamentos
                ->filter(fn($ag) => $ag->data_inicio->format('Y-m') === $ym)
                ->sortBy('data_inicio')
                ->map(function ($ag) use ($dosesPorAgendamento) {
                    $dose    = $dosesPorAgendamento->get($ag->id);
                    $credito = $ag->creditoServico;
                    return (object) [
                        'agendamento_id' => $ag->id,
                        'data'           => $ag->data_inicio,
                        'cliente'        => ucfirst($ag->user->name),
                        'servico'        => $ag->servico->descricao ?? '—',
                        // Posição da consulta no pacote / total comprado (ex.: 2/5).
                        'unidades'       => $credito
                            ? $credito->ordinalDe($ag) . '/' . $credito->quantidade
                            : '1/1',
                        'ui'             => $dose->ui ?? null,
                        'valor_pago'     => $dose->valor_pago ?? null,
                    ];
                })->values();

            $compras       = $fornecedores->filter(fn($f) => $f->data_compra->format('Y-m') === $ym);
            $compradoValor = $compras->sum('valor_total');
            $compradoUi    = $compras->sum(fn($f) => $f->total_ui);

            $vendidoValor  = $consultas->sum(fn($c) => $c->valor_pago ?? 0);
            $uiVendido     = $consultas->sum(fn($c) => $c->ui ?? 0);
            $lucro         = $vendidoValor - $uiVendido * $custoPorUi;

            return (object) [
                'ym'             => $ym,
                'label'          => ucfirst($ref->locale('pt_BR')->translatedFormat('F Y')),
                'consultas'      => $consultas,
                'comprado_valor' => $compradoValor,
                'comprado_ui'    => $compradoUi,
                'vendido_valor'  => $vendidoValor,
                'ui_vendido'     => $uiVendido,
                'lucro'          => $lucro,
            ];
        });

        $mesAtual = now()->format('Y-m');

        // ── Resumo global ─────────────────────────────────────────────────────
        $uiVendido     = $balancoMeses->sum('ui_vendido');
        $totalRecebido = $balancoMeses->sum('vendido_valor');
        $custoTotal    = $uiVendido * $custoPorUi;
        $lucroTotal    = $totalRecebido - $custoTotal;
        $uiRestante    = $uiComprado - $uiVendido;

        return view('aplicacoes.index', compact(
            'fornecedores', 'balancoMeses', 'mesAtual',
            'custoPorUi', 'uiComprado', 'uiVendido', 'uiRestante',
            'totalRecebido', 'totalFornecedor',
            'custoTotal', 'lucroTotal'
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

    // Lança/atualiza o valor pago e o UI aplicado de uma consulta Mounjaro.
    // Aceita atualização parcial (só UI ou só valor). Ambos vazios remove a dose.
    public function salvarDose(Request $request, $agendamentoId)
    {
        $this->adminOnly();

        $data = $request->validate([
            'ui'         => 'nullable|integer|min:1',
            'valor_pago' => 'nullable|numeric|min:0',
        ]);

        $ag = AgendamentoModel::with('servico')->findOrFail($agendamentoId);
        abort_unless($ag->servico && $ag->servico->mounjaro, 422, 'Agendamento não é de serviço Mounjaro.');

        $dose = DoseMounjaro::where('agendamento_id', $agendamentoId)->first();

        // Mescla os campos enviados com o que já existe (atualização parcial).
        $ui    = $request->has('ui')         ? ($data['ui'] ?? null)         : $dose?->ui;
        $valor = $request->has('valor_pago') ? ($data['valor_pago'] ?? null) : $dose?->valor_pago;

        // Nada lançado: limpa a dose.
        if (empty($ui) && ($valor === null || $valor === '')) {
            $dose?->delete();
            return response()->json(['ok' => true, 'ui' => null, 'valor_pago' => null]);
        }

        $aplicacao = AplicacaoMounjaro::firstOrCreate(
            ['user_id' => $ag->user_id],
            ['total_pago' => 0]
        );

        $payload = [
            'ui'         => $ui ?: null,
            'valor_pago' => ($valor === null || $valor === '') ? null : $valor,
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
            'ok'         => true,
            'ui'         => $dose->ui,
            'valor_pago' => $dose->valor_pago,
        ]);
    }
}

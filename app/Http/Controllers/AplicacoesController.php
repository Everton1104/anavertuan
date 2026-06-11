<?php

namespace App\Http\Controllers;

use App\Models\AgendamentoModel;
use App\Models\AplicacaoMounjaro;
use App\Models\DoseMounjaro;
use App\Models\FornecedorMounjaro;
use App\Models\User;
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

        $fornecedores = FornecedorMounjaro::with(['doses.aplicacao.doses'])
            ->orderBy('data_compra', 'desc')
            ->get()
            ->each(function ($f) {
                $f->ui_sobrando = $f->total_ui - $f->doses->sum('ui');
                $f->lucro_acumulado = $f->doses->sum(function ($dose) use ($f) {
                    $apl = $dose->aplicacao;
                    $totalUiApl = $apl->doses->sum('ui');
                    if ($totalUiApl == 0) return 0;
                    $receita = ($dose->ui / $totalUiApl) * $apl->total_pago;
                    return $receita - ($dose->ui * $f->custo_por_ui);
                });
            });

        $aplicacoes = AplicacaoMounjaro::with(['user', 'doses.fornecedor'])
            ->orderBy('created_at', 'desc')->get();
        $clientes = User::where('excluido', 0)->where('adm', 0)->where('func', 0)
            ->orderBy('name')->get();

        // Resumo
        $totalRecebido  = $aplicacoes->sum('total_pago');
        $totalFornecedor = $fornecedores->sum('valor_total');
        $totalAmpolas   = $fornecedores->sum('ampolas_compradas');
        $totalUI        = $fornecedores->sum(fn($f) => $f->total_ui);
        $custoTotal     = $aplicacoes->sum('custo_medicamento');
        $lucroTotal     = $aplicacoes->sum('lucro');

        return view('aplicacoes.index', compact(
            'fornecedores', 'aplicacoes', 'clientes',
            'totalRecebido', 'totalFornecedor', 'totalAmpolas',
            'totalUI', 'custoTotal', 'lucroTotal'
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
        FornecedorMounjaro::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── Aplicações ────────────────────────────────────────────────────────────

    public function storeAplicacao(Request $request)
    {
        $this->adminOnly();

        $data = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'total_pago' => 'required|numeric|min:0',
        ]);

        $id = $request->aplicacao_id;
        if ($id) {
            AplicacaoMounjaro::findOrFail($id)->update($data);
            $msg = 'Aplicação atualizada.';
        } else {
            AplicacaoMounjaro::create($data);
            $msg = 'Aplicação cadastrada.';
        }

        return redirect()->route('aplicacoes.index')->with('msg', $msg);
    }

    public function destroyAplicacao($id)
    {
        $this->adminOnly();
        AplicacaoMounjaro::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── Doses ─────────────────────────────────────────────────────────────────

    public function storeDose(Request $request, $aplicacaoId)
    {
        $this->adminOnly();

        $aplicacao = AplicacaoMounjaro::findOrFail($aplicacaoId);

        $data = $request->validate([
            'ui'            => 'required|integer|min:1',
            'fornecedor_id' => 'required|exists:fornecedores_mounjaro,id',
            'agendamento_id' => 'nullable|exists:agendamentos,id',
        ]);

        // Se veio de um agendamento, extrai dose e data
        $numeroDose  = $aplicacao->doses()->max('numero_dose') + 1;
        $dataAplicacao = null;

        if (!empty($data['agendamento_id'])) {
            $ag = AgendamentoModel::with('creditoServico')->find($data['agendamento_id']);
            if ($ag) {
                $dataAplicacao = $ag->data_inicio->toDateString();
                // Número da dose = posição do agendamento no pacote (ex.: 2ª de 5 → 2).
                // A posição passou a ser calculada pelo crédito; antes saía do nome do
                // serviço (regex "(X/5)"), que não existe mais na descrição.
                if ($ag->creditoServico) {
                    $numeroDose = $ag->creditoServico->ordinalDe($ag);
                }
            }
        }

        DoseMounjaro::create([
            'aplicacao_id'  => $aplicacao->id,
            'numero_dose'   => $numeroDose,
            'ui'            => $data['ui'],
            'fornecedor_id' => $data['fornecedor_id'],
            'data_aplicacao' => $dataAplicacao,
            'agendamento_id' => $data['agendamento_id'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function agendamentosCliente($userId)
    {
        $this->adminOnly();

        $agendamentos = AgendamentoModel::with('servico')
            ->where('user_id', $userId)
            ->whereHas('servico', fn($q) => $q->where('descricao', 'like', '%Pacote%'))
            ->orderBy('data_inicio')
            ->get()
            ->map(fn($ag) => [
                'id'      => $ag->id,
                'label'   => $ag->servico->descricao . ' — ' . $ag->data_inicio->format('d/m/Y'),
                'data'    => $ag->data_inicio->toDateString(),
                'servico' => $ag->servico->descricao,
            ]);

        return response()->json($agendamentos);
    }

    public function destroyDose($id)
    {
        $this->adminOnly();
        $dose = DoseMounjaro::findOrFail($id);
        $aplicacaoId = $dose->aplicacao_id;
        $dose->delete();

        // Renumerar doses
        DoseMounjaro::where('aplicacao_id', $aplicacaoId)
            ->orderBy('numero_dose')
            ->get()
            ->each(function ($d, $i) {
                $d->update(['numero_dose' => $i + 1]);
            });

        return response()->json(['ok' => true]);
    }
}

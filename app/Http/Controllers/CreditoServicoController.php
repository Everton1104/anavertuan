<?php

namespace App\Http\Controllers;

use App\Models\CreditoServico;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Http\Request;

class CreditoServicoController extends Controller
{
    private function autorizar(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->adm || $u->func), 403);
    }

    // Lista os serviços contratados de um cliente, com usadas/restantes.
    public function index($userId)
    {
        $this->autorizar();

        $creditos = CreditoServico::with('servico')
            ->where('user_id', $userId)
            ->get()
            ->map(function (CreditoServico $c) {
                $usadas = $c->usadas();
                return [
                    'id'          => $c->id,
                    'servico_id'  => $c->servico_id,
                    'descricao'   => $c->servico->descricao ?? '—',
                    'quantidade'  => $c->quantidade,
                    'usadas'      => $usadas,
                    'restantes'   => max(0, $c->quantidade - $usadas),
                ];
            })
            ->values();

        return response()->json($creditos);
    }

    // Adiciona (incrementa) unidades de um serviço ao cliente.
    public function store(Request $request, $userId)
    {
        $this->autorizar();

        $request->validate([
            'servico_id' => ['required', 'integer'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:999'],
        ], [
            'servico_id.required' => 'Selecione o serviço.',
            'quantidade.required' => 'Informe a quantidade.',
            'quantidade.min'      => 'A quantidade deve ser no mínimo 1.',
        ]);

        $user    = User::findOrFail($userId);
        $servico = ServicosModel::where('excluido', 0)->findOrFail($request->servico_id);

        $credito = CreditoServico::firstOrNew([
            'user_id'    => $user->id,
            'servico_id' => $servico->id,
        ]);
        $credito->quantidade = ($credito->quantidade ?? 0) + (int) $request->quantidade;
        $credito->save();

        return response()->json(['ok' => true]);
    }

    // Remove o crédito de um serviço do cliente.
    public function destroy($userId, $servicoId)
    {
        $this->autorizar();

        CreditoServico::where('user_id', $userId)
            ->where('servico_id', $servicoId)
            ->delete();

        return response()->json(['ok' => true]);
    }
}

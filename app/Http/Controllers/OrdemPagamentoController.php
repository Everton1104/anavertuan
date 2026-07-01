<?php

namespace App\Http\Controllers;

use App\Models\OrdemPagamento;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Ordens de pagamento: o staff (adm/func) cria/cancela; o paciente paga via
// Checkout Transparente (Mercado Pago). Valor e parcelas são sempre lidos do
// banco no momento do pagamento — nunca do front.
class OrdemPagamentoController extends Controller
{
    public function __construct(private readonly MercadoPagoService $mp) {}

    // ── Staff: criar ordem ───────────────────────────────────────────────────
    public function store(Request $request)
    {
        abort_unless(auth()->user()->adm, 403);

        $dados = $request->validate([
            'user_id'   => ['required', 'integer', 'exists:users,id'],
            'valor'     => ['required', 'numeric', 'min:0.01'],
            'descricao' => ['required', 'string', 'max:255'],
        ], [
            'user_id.exists'     => 'Paciente inválido.',
            'valor.min'          => 'O valor deve ser maior que zero.',
            'descricao.required' => 'Informe uma descrição.',
        ]);

        // Paciente deve ser um cliente (não adm/func) e não excluído.
        $paciente = \App\Models\User::where('id', $dados['user_id'])
            ->where('excluido', 0)
            ->where(fn($q) => $q->where('adm', 0)->orWhere('func', 0))
            ->first();
        if (!$paciente) {
            return redirect()->back()->withErrors(['user_id' => 'Paciente inválido.'])->withInput();
        }

        $ordem = DB::transaction(function () use ($dados) {
            $ordem = OrdemPagamento::create([
                'user_id'           => $dados['user_id'],
                'criado_por'        => auth()->id(),
                'valor'             => $dados['valor'],
                'descricao'         => $dados['descricao'],
                'max_parcelas'      => OrdemPagamento::MAX_PARCELAS,
                'status'            => 'aberta',
                // Gerado antes do insert: a coluna é NOT NULL (modo estrito do
                // MySQL) e não temos o id ainda. O webhook ancora por este campo.
                'external_reference' => (string) \Illuminate\Support\Str::uuid(),
            ]);

            $ordem->eventos()->create([
                'status' => 'aberta',
                'origem' => 'manual',
            ]);
            return $ordem;
        });

        $this->avisarPaciente($paciente, $ordem);

        return redirect()->back()->with('msg', 'Ordem de pagamento criada com sucesso!');
    }

    // ── Staff: cancelar ordem (só se ainda não aprovada) ────────────────────
    public function cancelar(Request $request, $id)
    {
        abort_unless(auth()->user()->adm, 403);

        $ordem = OrdemPagamento::findOrFail($id);
        if ($ordem->status === 'approved') {
            return redirect()->back()->with('msgErro', 'Não é possível cancelar uma ordem já aprovada.');
        }
        if ($ordem->status === 'cancelled') {
            return redirect()->back()->with('msg', 'A ordem já estava cancelada.');
        }

        $ordem->status = 'cancelled';
        $ordem->save();
        $ordem->eventos()->create(['status' => 'cancelled', 'origem' => 'manual']);

        return redirect()->back()->with('msg', 'Ordem cancelada.');
    }

    // ── Staff: excluir (apagar definitivamente) uma ordem criada por engano.
    // Bloqueada para ordens já aprovadas — não apagamos histórico de pagamento.
    public function destroy($id)
    {
        abort_unless(auth()->user()->adm, 403);

        $ordem = OrdemPagamento::findOrFail($id);
        if ($ordem->status === 'approved') {
            return redirect()->back()->with('msgErro', 'Não é possível excluir uma ordem já aprovada.');
        }

        $ordem->delete(); // hard delete — os eventos saem em cascade (FK)

        return redirect()->back()->with('msg', 'Ordem excluída.');
    }

    // ── Paciente: tela de checkout (cartão) ─────────────────────────────────
    public function pagar(OrdemPagamento $ordem)
    {
        abort_unless($ordem->user_id === auth()->id(), 403, 'Ordem não encontrada.');
        abort_if($ordem->status === 'approved', 403, 'Esta ordem já foi paga.');
        abort_if($ordem->status === 'cancelled', 403, 'Esta ordem foi cancelada.');

        return view('pagamentos.pagar', [
            'ordem'      => $ordem,
            'publicKey'  => config('services.mercadopago.public_key'),
        ]);
    }

    // ── Paciente: criar o pagamento no MP (chamado pelo onSubmit do Brick) ──
    public function cobrar(Request $request, OrdemPagamento $ordem)
    {
        abort_unless($ordem->user_id === auth()->id(), 403);

        if ($ordem->status === 'approved') {
            return response()->json(['erro' => 1, 'msg' => 'Esta ordem já foi paga.'], 422);
        }
        if ($ordem->status === 'cancelled') {
            return response()->json(['erro' => 1, 'msg' => 'Esta ordem foi cancelada.'], 422);
        }

        $dados = $request->validate([
            'token'             => ['required', 'string'],
            'payment_method_id' => ['required', 'string'],
            'issuer_id'         => ['nullable', 'string'],
            'installments'      => ['required', 'integer', 'min:1'],
            'payer_email'       => ['nullable', 'email'],
            'payer_doc_type'    => ['nullable', 'string'],
            'payer_doc_number'  => ['nullable', 'string'],
        ]);

        $res = $this->mp->criarPayment($ordem, $dados);
        if (isset($res['erro'])) {
            return response()->json($res, 422);
        }

        $status = $res['status'] ?? 'pending';
        $ordem->status            = $status;
        $ordem->status_detail     = $res['status_detail'] ?? null;
        $ordem->payment_id_mp     = $res['id'] ?? ($ordem->payment_id_mp);
        $ordem->payment_method_id = $dados['payment_method_id'];
        $ordem->installments      = (int) $dados['installments'];
        if ($status === 'approved') {
            $ordem->pago_em = now();
        }
        $ordem->save();

        $ordem->eventos()->create([
            'payment_id_mp' => $ordem->payment_id_mp,
            'status'        => $status,
            'origem'        => 'checkout',
            'payload'       => ['status_detail' => $res['status_detail'] ?? null],
        ]);

        if ($status === 'approved') {
            $this->confirmarPagamentoPaciente($ordem);
        }

        return response()->json(['status' => $status, 'message' => $this->mensagemStatus($status)]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function mensagemStatus(string $status): string
    {
        return match ($status) {
            'approved'  => 'Pagamento aprovado! 🎉',
            'pending'   => 'Pagamento em análise. Avisaremos quando for confirmado.',
            'in_process'=> 'Pagamento em processamento. Aguarde.',
            'rejected'  => 'Pagamento recusado. Verifique os dados do cartão e tente novamente.',
            default     => 'Status: ' . $status,
        };
    }

    // Avisa o paciente (por WhatsApp, se tiver número) que uma ordem foi criada.
    // Usa TEMPLATE (não texto livre): fora da janela de 24h a Meta só entrega
    // mensagem iniciada pela empresa se for um template aprovado.
    private function avisarPaciente(\App\Models\User $paciente, OrdemPagamento $ordem): void
    {
        if (!$paciente->whatsapp) {
            return;
        }

        $valor    = 'R$ ' . number_format((float) $ordem->valor, 2, ',', '.');
        $parcelas = (string) $ordem->max_parcelas;
        $link     = rtrim((string) env('APP_URL', config('app.url')), '/') . '/pagamentos/' . $ordem->id . '/pagar';
        $nome     = ucfirst($paciente->name);

        try {
            $r = WhatsappController::enviarModelo(
                env('PHONE_NUMBER_ID'),
                $paciente->whatsapp,
                env('WHATSAPP_TEMPLATE_ORDEM_PAGAMENTO', 'ordem_pagamento_disponivel'),
                [
                    ['type' => 'text', 'text' => $nome],
                    ['type' => 'text', 'text' => $ordem->descricao],
                    ['type' => 'text', 'text' => $valor],
                    ['type' => 'text', 'text' => $parcelas],
                    ['type' => 'text', 'text' => $link],
                ]
            );
            if (isset($r['erro'])) {
                \Illuminate\Support\Facades\Log::warning('[OrdemPagamento] template de ordem falhou', [
                    'ordem' => $ordem->id, 'resp' => $r,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[OrdemPagamento] falha ao avisar paciente', [
                'ordem' => $ordem->id, 'msg' => $e->getMessage(),
            ]);
        }
    }

    // Confirmação por WhatsApp quando o pagamento é aprovado. Também usa template,
    // pois o paciente pode estar fora da janela de 24h.
    private function confirmarPagamentoPaciente(OrdemPagamento $ordem): void
    {
        $paciente = $ordem->user;
        if (!$paciente || !$paciente->whatsapp) {
            return;
        }

        $valor = 'R$ ' . number_format((float) $ordem->valor, 2, ',', '.');
        $nome  = ucfirst($paciente->name ?? 'você');

        try {
            $r = WhatsappController::enviarModelo(
                env('PHONE_NUMBER_ID'),
                $paciente->whatsapp,
                env('WHATSAPP_TEMPLATE_PAGAMENTO_APROVADO', 'pagamento_confirmado'),
                [
                    ['type' => 'text', 'text' => $nome],
                    ['type' => 'text', 'text' => $valor],
                    ['type' => 'text', 'text' => $ordem->descricao],
                ]
            );
            if (isset($r['erro'])) {
                \Illuminate\Support\Facades\Log::warning('[OrdemPagamento] template de aprovação falhou', [
                    'ordem' => $ordem->id, 'resp' => $r,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[OrdemPagamento] falha ao confirmar pagamento', [
                'ordem' => $ordem->id, 'msg' => $e->getMessage(),
            ]);
        }
    }
}

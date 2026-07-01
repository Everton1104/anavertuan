<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Ordem de pagamento criada pelo staff e quitável pelo paciente via Mercado Pago.
// O status segue os estados do pagamento no MP (approved, pending, rejected...)
// mais os internos "aberta" (aguardando o paciente pagar) e "cancelled".
class OrdemPagamento extends Model
{
    protected $table = 'ordem_pagamentos';

    // Teto fixo de parcelas sem juros oferecidas a TODOS os clientes. O número
    // exato de parcelas é escolhido por eles no momento do pagamento (1 a 6x).
    public const MAX_PARCELAS = 6;

    protected $fillable = [
        'user_id',
        'criado_por',
        'valor',
        'descricao',
        'max_parcelas',
        'status',
        'external_reference',
        'payment_id_mp',
        'payment_method_id',
        'installments',
        'status_detail',
        'pago_em',
    ];

    protected $casts = [
        'valor'        => 'float',
        'max_parcelas' => 'integer',
        'installments' => 'integer',
        'pago_em'      => 'datetime',
    ];

    // Paciente que deve pagar a ordem.
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Staff (adm/func) que criou a ordem.
    public function criador()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function eventos()
    {
        return $this->hasMany(OrdemPagamentoEvent::class, 'ordem_id');
    }

    // Ordens que o paciente ainda pode tentar pagar no checkout.
    public function pagavel(): bool
    {
        return in_array($this->status, ['aberta', 'rejected', 'pending'], true);
    }

    // Rótulo legível + classe Bootstrap do status para a tabela/cards.
    public function statusBadge(): array
    {
        return match ($this->status) {
            'approved'   => ['Aprovado', 'bg-success'],
            'pending'    => ['Em análise', 'bg-warning text-dark'],
            'aberta'     => ['Aguardando pagamento', 'bg-info text-dark'],
            'rejected'   => ['Recusado', 'bg-danger'],
            'cancelled'  => ['Cancelada', 'bg-secondary'],
            'refunded'   => ['Estornada', 'bg-dark'],
            default      => [ucfirst($this->status), 'bg-secondary'],
        };
    }
}

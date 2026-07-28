<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoseMounjaro extends Model
{
    protected $table = 'doses_mounjaro';

    protected $fillable = ['aplicacao_id', 'numero_dose', 'ui', 'valor_pago', 'fornecedor_id', 'data_aplicacao', 'agendamento_id'];

    protected $casts = ['data_aplicacao' => 'date', 'valor_pago' => 'float'];

    public function aplicacao()
    {
        return $this->belongsTo(AplicacaoMounjaro::class, 'aplicacao_id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(FornecedorMounjaro::class, 'fornecedor_id');
    }

    public function agendamento()
    {
        return $this->belongsTo(AgendamentoModel::class, 'agendamento_id');
    }

    // Custo unitário específico do lote amarrado à dose (custo_por_ui do fornecedor).
    // NULL quando não há lote — nesse caso o cálculo usa o custo médio global como fallback.
    public function getCustoUnitarioAttribute(): ?float
    {
        return $this->fornecedor?->custo_por_ui;
    }
}

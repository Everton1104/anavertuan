<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoseMounjaro extends Model
{
    protected $table = 'doses_mounjaro';

    protected $fillable = ['aplicacao_id', 'numero_dose', 'ui', 'fornecedor_id', 'data_aplicacao', 'agendamento_id'];

    protected $casts = ['data_aplicacao' => 'date'];

    public function aplicacao()
    {
        return $this->belongsTo(AplicacaoMounjaro::class, 'aplicacao_id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(FornecedorMounjaro::class, 'fornecedor_id');
    }

    public function getCustoDoseAttribute(): float
    {
        return $this->ui * $this->fornecedor->custo_por_ui;
    }
}

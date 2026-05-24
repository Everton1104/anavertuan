<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AplicacaoMounjaro extends Model
{
    protected $table = 'aplicacoes_mounjaro';

    protected $fillable = ['user_id', 'total_pago'];

    protected $casts = ['total_pago' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function doses()
    {
        return $this->hasMany(DoseMounjaro::class, 'aplicacao_id')->orderBy('numero_dose');
    }

    public function getCustoMedicamentoAttribute(): float
    {
        return $this->doses->sum(function ($dose) {
            return $dose->ui * $dose->fornecedor->custo_por_ui;
        });
    }

    public function getLucroAttribute(): float
    {
        return $this->total_pago - $this->custo_medicamento;
    }
}

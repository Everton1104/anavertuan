<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FornecedorMounjaro extends Model
{
    protected $table = 'fornecedores_mounjaro';

    protected $fillable = [
        'fornecedor', 'data_compra', 'produto',
        'ampolas_compradas', 'ui_por_ampola', 'valor_total',
    ];

    protected $casts = ['data_compra' => 'date'];

    public function doses()
    {
        return $this->hasMany(DoseMounjaro::class, 'fornecedor_id');
    }

    public function getTotalUiAttribute(): int
    {
        return $this->ampolas_compradas * $this->ui_por_ampola;
    }

    public function getValorPorAmpolaAttribute(): float
    {
        return $this->ampolas_compradas > 0
            ? $this->valor_total / $this->ampolas_compradas
            : 0;
    }

    public function getCustoPorUiAttribute(): float
    {
        $totalUi = $this->total_ui;
        return $totalUi > 0 ? $this->valor_total / $totalUi : 0;
    }

    // Chave usada para cruzar com doses (igual à planilha)
    public function getChaveAttribute(): string
    {
        return $this->fornecedor . ' - ' . $this->data_compra->format('d/m/Y');
    }
}

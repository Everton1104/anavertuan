<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Ficha de anamnese preenchida pelo staff durante a consulta. Um paciente pode
// ter várias fichas ao longo do tempo. Os campos clínicos ficam em `dados`
// (JSON, cast para array) — flexível para evoluir sem migration. Exclusão é
// lógica via flag `excluido` (mesmo padrão do restante do sistema).
class FichaAnamnese extends Model
{
    protected $table = 'fichas_anamnese';

    protected $fillable = ['user_id', 'criado_por', 'dados', 'excluido'];

    protected $casts = [
        'dados'    => 'array',
        'excluido' => 'boolean',
    ];

    // Paciente dono da ficha.
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Staff que criou a ficha.
    public function criador()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    // IMC derivado de dados.peso (kg) e dados.altura (cm). Null se faltar dado.
    public function imc(): ?float
    {
        $dados  = $this->dados ?? [];
        $peso   = isset($dados['peso']) ? (float) $dados['peso'] : 0;
        $altura = isset($dados['altura']) ? (float) $dados['altura'] : 0;
        if ($peso <= 0 || $altura <= 0) {
            return null;
        }
        $alturaM = $altura / 100;

        return round($peso / ($alturaM * $alturaM), 1);
    }
}

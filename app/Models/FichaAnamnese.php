<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Ficha do paciente preenchida pelo staff durante a consulta. Um paciente pode
// ter várias fichas ao longo do tempo. Os campos clínicos ficam em `dados`
// (JSON, cast para array) — flexível para evoluir sem migration. Exclusão é
// lógica via flag `excluido` (mesmo padrão do restante do sistema).
//
// A coluna `tipo` distingue os dois formatos: TIPO_ANAMNESE (formulário clínico
// estruturado em `dados`) e TIPO_NOTA (anotação livre com título/observação e
// anexos — ver FichaAnexo).
class FichaAnamnese extends Model
{
    public const TIPO_ANAMNESE = 'anamnese';
    public const TIPO_NOTA     = 'nota';

    protected $table = 'fichas_anamnese';

    protected $fillable = ['user_id', 'criado_por', 'tipo', 'dados', 'excluido'];

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

    // Arquivos anexados à ficha (notas livres, por enquanto).
    public function anexos()
    {
        return $this->hasMany(FichaAnexo::class, 'ficha_id');
    }

    // Atalho: a ficha é uma anotação livre?
    public function ehNota(): bool
    {
        return $this->tipo === self::TIPO_NOTA;
    }

    // Título da nota (em dados.titulo). Vazio se não houver.
    public function titulo(): string
    {
        return trim((string) ($this->dados['titulo'] ?? ''));
    }

    // Observação livre da nota (em dados.observacao). Vazio se não houver.
    public function observacao(): string
    {
        return trim((string) ($this->dados['observacao'] ?? ''));
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

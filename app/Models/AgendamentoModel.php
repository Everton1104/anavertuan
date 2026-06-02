<?php

namespace App\Models;

use App\Models\LembreteConsulta;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AgendamentoModel extends Model
{
    protected $table = 'agendamentos';
    protected $fillable = [
        'user_id',
        'servico_id',
        'data_inicio',
        'data_fim',
        'confirmado',
        'pre_confirmado_em',
        'confirmado_em',
    ];

    protected $casts = [
        'data_inicio'        => 'datetime',
        'data_fim'           => 'datetime',
        'confirmado'         => 'boolean',
        'pre_confirmado_em'  => 'datetime',
        'confirmado_em'      => 'datetime',
    ];

    // Expõe o status de cada lembrete no JSON (consumido pelos cards do dashboard).
    protected $appends = ['lembrete_24h', 'lembrete_2h'];

    // 'enviado' | 'erro' | null (ainda não disparado)
    public function getLembrete24hAttribute(): ?string
    {
        return $this->lembretes->firstWhere('tipo', '24h')?->status;
    }

    public function getLembrete2hAttribute(): ?string
    {
        return $this->lembretes->firstWhere('tipo', '2h')?->status;
    }

    public function servico()
    {
        return $this->belongsTo(ServicosModel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lembretes()
    {
        return $this->hasMany(LembreteConsulta::class, 'agendamento_id');
    }
}
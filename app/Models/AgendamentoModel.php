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
    ];

    protected $casts = [
        'data_inicio' => 'datetime',
        'data_fim'    => 'datetime',
        'confirmado'  => 'boolean',
    ];

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
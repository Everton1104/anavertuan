<?php

namespace App\Models;

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
    ];

    protected $dates = ['data_inicio', 'data_fim'];

    public function servico()
    {
        return $this->belongsTo(ServicosModel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
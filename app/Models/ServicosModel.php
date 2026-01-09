<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicosModel extends Model
{
    protected $table = 'servicos';
    protected $fillable = ['descricao', 'duracao', 'status', 'excluido'];

    public function agendamentos()
    {
        return $this->hasMany(AgendamentoModel::class);
    }
}
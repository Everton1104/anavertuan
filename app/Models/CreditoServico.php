<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditoServico extends Model
{
    protected $table = 'creditos_servico';

    protected $fillable = ['user_id', 'servico_id', 'quantidade'];

    protected $casts = ['quantidade' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servico()
    {
        return $this->belongsTo(ServicosModel::class, 'servico_id');
    }

    // Quantas unidades deste serviço o cliente já consumiu (agendamentos vivos).
    // Conta pelo serviço de desconto (credito_servico_id), que pode vir de um
    // agendamento de outro serviço (ex.: um "Encaixe" descontando deste pacote).
    public function usadas(): int
    {
        return AgendamentoModel::where('user_id', $this->user_id)
            ->where('credito_servico_id', $this->servico_id)
            ->count();
    }

    public function restantes(): int
    {
        return max(0, $this->quantidade - $this->usadas());
    }
}

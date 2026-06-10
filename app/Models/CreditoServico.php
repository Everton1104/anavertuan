<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Um pacote de unidades de um serviço contratado pelo cliente. O mesmo cliente
// pode ter mais de um pacote do mesmo serviço (cada linha é um pacote).
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

    // Agendamentos vivos que consomem deste pacote (inclui encaixes de outros serviços).
    public function agendamentos()
    {
        return $this->hasMany(AgendamentoModel::class, 'credito_servico_id');
    }

    public function usadas(): int
    {
        // Usa o withCount quando carregado em lote, para evitar 1 consulta por pacote.
        return $this->agendamentos_count ?? $this->agendamentos()->count();
    }

    public function restantes(): int
    {
        return max(0, $this->quantidade - $this->usadas());
    }
}

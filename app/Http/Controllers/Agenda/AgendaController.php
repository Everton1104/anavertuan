<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\AgendamentoModel;
use App\Models\ServicosModel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgendaController extends Controller
{
    public function timeToMinutes($time)
    {
        list($h, $m, $s) = explode(':', $time);
        return ($h * 60) + $m;
    }

    public function horarios($data)
    {
        // ID da consulta que está sendo editada (se houver)
        $ignoreId = request('ignore_id');

        // Buscar consultas do dia, exceto a que está sendo editada
        $consultas = AgendamentoModel::whereDate('data_inicio', $data)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('data_inicio')
            ->get();

        // Serviço selecionado
        $servico = ServicosModel::find(request('servico_id'));
        $duracaoMin = $this->timeToMinutes($servico->duracao);

        // Horário de funcionamento
        $inicio = Carbon::parse($data . ' 08:00');
        $fim = Carbon::parse($data . ' 18:00');

        $horarios = [];

        while ($inicio <= $fim) {

            $hora = $inicio->format('H:i');
            $ocupado = false;
            $motivo = null;

            // 1. Verificar se o horário está dentro de alguma consulta existente
            foreach ($consultas as $c) {
                $inicioConsulta = Carbon::parse($c->data_inicio);
                $fimConsulta = Carbon::parse($c->data_fim);

                if ($inicio >= $inicioConsulta && $inicio < $fimConsulta) {
                    $ocupado = true;
                    $motivo = 'Horário já reservado';
                    break;
                }
            }

            // 2. Verificar se o serviço cabe até o próximo agendamento
            if (!$ocupado) {

                $proximaConsulta = AgendamentoModel::where('data_inicio', '>', $inicio)
                    ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                    ->orderBy('data_inicio')
                    ->first();

                $fimTeorico = $inicio->copy()->addMinutes($duracaoMin);

                if ($proximaConsulta && $fimTeorico > $proximaConsulta->data_inicio) {
                    $ocupado = true;
                    $motivo = 'Não comporta a duração do serviço';
                }
            }

            // 3. Adicionar ao array final
            $horarios[] = [
                'hora' => $hora,
                'ocupado' => $ocupado,
                'motivo' => $motivo,
            ];

            $inicio->addMinutes(30);
        }

        return response()->json($horarios);
    }



    public function store(Request $request)
    {
        $request->validate(
            [
                'user_id' => ['required'],
                'servico_id' => ['required'],
                'data_inicio' => ['required'],
            ],
            [
                'user_id.required' => 'Selecione o cliente.',
                'servico_id.required' => 'Selecione o serviço.',
            ]
        );

        $servico = ServicosModel::find($request->servico_id);

        $duracao = $this->timeToMinutes($servico->duracao);

        $inicio = Carbon::parse($request->data_inicio);
        $fim = $inicio->copy()->addMinutes($duracao);

        $existeConflito = AgendamentoModel::where(function($q) use ($inicio, $fim) {
            $q->where('data_inicio', '<', $fim)
            ->where('data_fim', '>', $inicio);
        })->exists();

        if ($existeConflito) {
            return back()
                ->withErrors(['data_inicio' => 'A duração deste serviço conflita com outro agendamento'])
                ->withInput();
        }

        AgendamentoModel::create([
            'user_id' => $request->user_id,
            'servico_id' => $servico->id,
            'data_inicio' => $inicio,
            'data_fim' => $fim,
        ]);

        return redirect()->back()->with('msg', 'Agendamento criado com sucesso');
    }

    public function edit($id)
    {
        return AgendamentoModel::findOrFail($id);
    }

}
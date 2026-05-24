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
        $parts = explode(':', $time);
        return ($parts[0] * 60) + $parts[1];
    }

    public function horarios($data)
    {
        $servico = ServicosModel::find(request('servico_id'));

        if (!$servico) {
            return response()->json(['error' => 'Serviço não encontrado'], 422);
        }

        $ignoreId = request('ignore_id');
        $consultas = AgendamentoModel::whereDate('data_inicio', $data)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('data_inicio')
            ->get();

        $duracaoMin = $this->timeToMinutes($servico->duracao);

        // Horário de funcionamento
        $inicio = Carbon::parse($data . ' 08:00');
        $fim = Carbon::parse($data . ' 18:00');

        $horarios = [];

        while ($inicio < $fim) {
            $hora = $inicio->format('H:i');
            $ocupado = false;
            $motivo = null;

            // 1. Verificar conflito com outras consultas
            foreach ($consultas as $c) {
                $inicioConsulta = Carbon::parse($c->data_inicio);
                $fimConsulta = Carbon::parse($c->data_fim);

                if ($inicio >= $inicioConsulta && $inicio < $fimConsulta) {
                    $ocupado = true;
                    $motivo = 'Horário já reservado';
                    break;
                }
            }

            // 2. Verificar se o serviço cabe até o fim do expediente
            $fimExpediente = $fim;
            $fimTeorico = $inicio->copy()->addMinutes($duracaoMin);

            if (!$ocupado && $fimTeorico > $fimExpediente) {
                $ocupado = true;
                $motivo = 'Não comporta a duração do serviço';
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
                'user_id'     => ['required'],
                'servico_id'  => ['required', 'exists:servicos,id'],
                'data_inicio' => ['required', 'date', 'after:now'],
            ],
            [
                'user_id.required'      => 'Selecione o cliente.',
                'servico_id.required'   => 'Selecione o serviço.',
                'servico_id.exists'     => 'Serviço inválido.',
                'data_inicio.required'  => 'Selecione um dia e horário.',
                'data_inicio.date'      => 'Data inválida.',
                'data_inicio.after'     => 'O agendamento deve ser em uma data futura.',
            ]
        );

        $servico = ServicosModel::find($request->servico_id);

        $duracao = $this->timeToMinutes($servico->duracao);

        $inicio = Carbon::parse($request->data_inicio);
        $fim = $inicio->copy()->addMinutes($duracao);

        // Se estiver editando, ignorar o próprio agendamento
        $ignoreId = $request->agendamento_id;

        // Verificar conflito
        $existeConflito = AgendamentoModel::where(function($q) use ($inicio, $fim) {
                $q->where('data_inicio', '<', $fim)
                ->where('data_fim', '>', $inicio);
            })
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($existeConflito) {
            return back()
                ->withErrors(['data_inicio' => 'A duração deste serviço conflita com outro agendamento'])
                ->withInput();
        }

        // Se for edição → atualizar
        if ($ignoreId) {
            $agendamento = AgendamentoModel::findOrFail($ignoreId);
        } else {
            // Se for criação → novo
            $agendamento = new AgendamentoModel();
        }

        $agendamento->user_id = $request->user_id;
        $agendamento->servico_id = $servico->id;
        $agendamento->data_inicio = $inicio;
        $agendamento->data_fim = $fim;
        $agendamento->save();

        return redirect()->back()->with('msg', 'Agendamento salvo com sucesso');
    }


    public function edit($id)
    {
        return AgendamentoModel::findOrFail($id);
    }

    public function destroy($id)
    {
        $consulta = AgendamentoModel::findOrFail($id);
        $consulta->delete();
        return response()->json(['ok' => true]);
    }
    
    public function search(Request $request)
    {
        $q = trim($request->q);

        $consultas = AgendamentoModel::with(['user', 'servico'])
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('user', fn($u) => $u->where('name', 'like', "%{$q}%"));
            })
            ->orderBy('data_inicio')
            ->get()
            ->groupBy(function ($item) {
                return \Carbon\Carbon::parse($item->data_inicio)
                    ->locale('pt_BR')
                    ->translatedFormat('F Y');
            });

        return response()->json($consultas);
    }
}
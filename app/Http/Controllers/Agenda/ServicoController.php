<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\AgendamentoModel;
use App\Models\ServicosModel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServicoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'descricao' => ['required', 'string', 'max:255', 'min:5'],
            'duracao_h' => ['required', 'numeric'],
            'duracao_m' => ['required', 'numeric'],
        ]);
        
        if(($request->duracao_h + $request->duracao_m) <= 0){
            return redirect()->back()->withErrors([
                'duracao_m' => 'O tempo mínimo é de 30 minutos.'
            ])->withInput($request->all());
        }
        
        ServicosModel::create([
            'descricao' => $request->descricao,
            'duracao' => $request->duracao_h . ':' . $request->duracao_m . ':' . '00',
        ]);

        return redirect()->back()->with('msg', 'Servico criado com sucesso!');
    }

    public function delete(Request $request)
    {
        $request->validate([
            'excluir-servico-id' => 'required|integer',
        ]);

        $consultas = AgendamentoModel::with(['user', 'servico'])
            ->where('servico_id','=',$request['excluir-servico-id'])
            ->get();
        if($consultas->count() > 0){
            return redirect()->back()->with('msgErro', 'Ainda existem clientes registrados com esse serviço!');
        }
        
        try {
            ServicosModel::find($request['excluir-servico-id'])->update(['excluido' => 1]);
            return redirect()->back()->with('msg', 'Serviço excluído com sucesso!');
        } catch (\Throwable $th) {
            return redirect()->back()->with('msgErro', 'Erro ao excluir serviço!');
        }
    }

    public function editar(Request $request)
    {
        $request->validate([
            'id_edt_servico' => 'required|numeric',
            'descricao_edt_servico' => ['required', 'string', 'max:255', 'min:5'],
            'duracao_h_edt_servico' => ['required', 'numeric'],
            'duracao_m_edt_servico' => ['required', 'numeric'],
            'status_servico' => ['required', 'numeric'],
        ]);

        if(($request->duracao_h_edt_servico + $request->duracao_m_edt_servico) <= 0){
            return redirect()->back()->withErrors([
                'duracao_m_edt_servico' => 'O tempo mínimo é de 30 minutos.'
            ])->withInput($request->all());
        }

        ServicosModel::find($request['id_edt_servico'])->update([
            'descricao' => $request['descricao_edt_servico'],
            'duracao' => $request['duracao_h_edt_servico'] . ':' . $request['duracao_m_edt_servico'] . ':' . '00',
            'status' => $request['status_servico']
        ]);
        
        return redirect()->back()->with('msg', 'Serviço atualizado com sucesso!');
    }

}

<?php

namespace App\Http\Controllers;

use App\Models\FichaAnamnese;
use App\Models\User;
use Illuminate\Http\Request;

// Fichas de anamnese: acesso exclusivo de staff (adm/func). O staff cria a ficha
// (escolhendo o paciente), preenche durante a consulta numa página dedicada, e
// pode reabrir para visualizar/editar. Um paciente pode ter várias fichas.
// Exclusão é lógica via flag `excluido`.
class FichaAnamneseController extends Controller
{
    private function autorizar(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->adm || $u->func), 403);
    }

    // Lista as fichas de um cliente (para o modal "Fichas" por paciente).
    public function indexPorCliente($userId)
    {
        $this->autorizar();

        $fichas = FichaAnamnese::where('user_id', $userId)
            ->where('excluido', 0)
            ->latest()
            ->get()
            ->map(function (FichaAnamnese $f) {
                $prev = trim((string) ($f->dados['queixas'] ?? ''));
                if ($prev === '') {
                    $prev = trim((string) ($f->dados['objetivo'] ?? ''));
                }
                return [
                    'id'           => $f->id,
                    'criada_em'    => $f->created_at?->format('d/m/Y H:i'),
                    'atualizada_em'=> $f->updated_at?->format('d/m/Y H:i'),
                    'preview'      => mb_substr($prev, 0, 80) . (mb_strlen($prev) > 80 ? '…' : ''),
                    'imc'          => $f->imc(),
                    'criador'      => $f->criador?->name,
                ];
            })
            ->values();

        return response()->json($fichas);
    }

    // Cria uma ficha vazia para o paciente escolhido e redireciona para a página
    // de preenchimento.
    public function store(Request $request)
    {
        $this->autorizar();

        $dados = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ], [
            'user_id.required' => 'Selecione o paciente.',
            'user_id.exists'   => 'Paciente inválido.',
        ]);

        // Confirma que é um cliente (não adm/func) e não excluído.
        $paciente = User::where('id', $dados['user_id'])
            ->where('excluido', 0)
            ->where('adm', 0)
            ->where('func', 0)
            ->first();
        if (!$paciente) {
            return redirect()->back()->withErrors(['user_id' => 'Paciente inválido.'])->withInput();
        }

        $ficha = FichaAnamnese::create([
            'user_id'    => $paciente->id,
            'criado_por' => auth()->id(),
            'dados'      => [],
        ]);

        return redirect()->route('anamneses.edit', $ficha->id);
    }

    // Página dedicada de preenchimento/edição da ficha.
    public function edit($id)
    {
        $this->autorizar();

        $ficha = FichaAnamnese::with('user', 'criador')
            ->where('excluido', 0)
            ->findOrFail($id);

        return view('anamneses.editar', [
            'ficha'   => $ficha,
            'paciente'=> $ficha->user,
        ]);
    }

    // Salva os campos preenchidos (em JSON `dados`). Estrutura espelha o Google
    // Forms de anamnese; campos vetoriais (checkboxes) em arrays, demais escalares.
    public function update(Request $request, $id)
    {
        $this->autorizar();

        $ficha = FichaAnamnese::where('excluido', 0)->findOrFail($id);

        $escalares = ['sexo', 'nome', 'idade', 'ocupacao', 'objetivo', 'queixas',
            'ant_pessoais', 'ant_familiares', 'medicamentos', 'medicamentos_quais',
            'antibioticos', 'antibioticos_quais', 'gestante', 'tpm', 'ciclo',
            'atividade', 'modalidade', 'postura', 'sono', 'horas_sono', 'energia',
            'memoria', 'alergia_desc', 'fumante', 'alcool', 'fezes', 'urina',
            'agua_qtd', 'al_nao_gosta', 'al_gosta_nao_come', 'al_essencial',
            'rotina_cafe', 'rotina_almoco', 'rotina_jantar'];
        $vetores = ['dificuldade', 'humor', 'alergias', 'sintomas', 'comum', 'agua_sede'];

        $rules = [];
        foreach ($escalares as $k) {
            $rules[$k] = ['nullable', 'string', 'max:5000'];
        }
        foreach ($vetores as $k) {
            $rules[$k]     = ['nullable', 'array'];
            $rules[$k.'.*'] = ['string'];
        }
        $rules['peso']   = ['nullable', 'numeric', 'min:0', 'max:600'];
        $rules['altura'] = ['nullable', 'numeric', 'min:0', 'max:300'];
        $v = $request->validate($rules);

        $dados = [];
        foreach ($escalares as $k) {
            $dados[$k] = $v[$k] ?? null;
        }
        $dados['peso']   = $v['peso'] ?? null;
        $dados['altura'] = $v['altura'] ?? null;
        foreach ($vetores as $k) {
            $dados[$k] = $v[$k] ?? [];
        }

        $ficha->dados = $dados;
        $ficha->save();

        return redirect()->route('anamneses.edit', $ficha->id)->with('msg', 'Ficha salva!');
    }

    // Soft delete (exclusão lógica).
    public function destroy($id)
    {
        $this->autorizar();

        $ficha = FichaAnamnese::where('excluido', 0)->findOrFail($id);
        $ficha->update(['excluido' => 1]);

        return redirect()->route('dashboard')->with('msg', 'Ficha de anamnese excluída.');
    }
}

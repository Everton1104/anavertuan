<?php

namespace App\Http\Controllers;

use App\Models\FichaAnamnese;
use App\Models\FichaAnexo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// Fichas do paciente (anamnese e anotação livre): acesso exclusivo de staff
// (adm/func). O staff cria a ficha (escolhendo o paciente), preenche durante a
// consulta numa página dedicada, e pode reabrir para visualizar/editar. Um
// paciente pode ter várias fichas. Exclusão é lógica via flag `excluido`.
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

        $fichas = FichaAnamnese::with('anexos')
            ->where('user_id', $userId)
            ->where('excluido', 0)
            ->latest()
            ->get()
            ->map(function (FichaAnamnese $f) {
                if ($f->ehNota()) {
                    $prev   = $f->observacao();
                    $titulo = $f->titulo();
                } else {
                    $prev   = trim((string) ($f->dados['queixas'] ?? ''));
                    if ($prev === '') {
                        $prev = trim((string) ($f->dados['objetivo'] ?? ''));
                    }
                    $titulo = '';
                }
                return [
                    'id'           => $f->id,
                    'tipo'         => $f->tipo,
                    'titulo'       => $titulo,
                    'criada_em'    => $f->created_at?->format('d/m/Y H:i'),
                    'atualizada_em'=> $f->updated_at?->format('d/m/Y H:i'),
                    'preview'      => mb_substr($prev, 0, 80) . (mb_strlen($prev) > 80 ? '…' : ''),
                    'imc'          => $f->ehNota() ? null : $f->imc(),
                    'qtd_anexos'   => $f->anexos->count(),
                    'criador'      => $f->criador?->name,
                ];
            })
            ->values();

        return response()->json($fichas);
    }

    // Cria uma ficha vazia para o paciente escolhido e redireciona para a página
    // de preenchimento. `tipo` define se é anamnese (formulário) ou nota livre.
    public function store(Request $request)
    {
        $this->autorizar();

        $dados = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'tipo'    => ['nullable', 'in:' . FichaAnamnese::TIPO_ANAMNESE . ',' . FichaAnamnese::TIPO_NOTA],
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

        $tipo = $dados['tipo'] ?? FichaAnamnese::TIPO_ANAMNESE;

        $ficha = FichaAnamnese::create([
            'user_id'    => $paciente->id,
            'criado_por' => auth()->id(),
            'tipo'       => $tipo,
            'dados'      => [],
        ]);

        return redirect()->route(
            $tipo === FichaAnamnese::TIPO_NOTA ? 'anamneses.nota.edit' : 'anamneses.edit',
            $ficha->id
        );
    }

    // Página dedicada de preenchimento/edição da ficha de anamnese.
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

    // Página dedicada da anotação livre (título, observação e anexos).
    public function editNota($id)
    {
        $this->autorizar();

        $ficha = FichaAnamnese::with(['user', 'criador', 'anexos'])
            ->where('excluido', 0)
            ->where('tipo', FichaAnamnese::TIPO_NOTA)
            ->findOrFail($id);

        return view('anamneses.nota', [
            'ficha'   => $ficha,
            'paciente'=> $ficha->user,
            'anexos'  => $ficha->anexos,
        ]);
    }

    // Salva o título e a observação da nota (em JSON `dados`).
    public function updateNota(Request $request, $id)
    {
        $this->autorizar();

        $ficha = FichaAnamnese::where('excluido', 0)
            ->where('tipo', FichaAnamnese::TIPO_NOTA)
            ->findOrFail($id);

        $v = $request->validate([
            'titulo'     => ['nullable', 'string', 'max:200'],
            'observacao' => ['nullable', 'string', 'max:20000'],
        ]);

        $ficha->dados = [
            'titulo'     => trim((string) ($v['titulo'] ?? '')),
            'observacao' => trim((string) ($v['observacao'] ?? '')),
        ];
        $ficha->save();

        return redirect()->route('anamneses.nota.edit', $ficha->id)->with('msg', 'Nota salva!');
    }

    // Recebe um ou mais arquivos e anexa à nota. Ficam no disco `public`
    // (fichas-notas/{ficha_id}/...). Até 5 arquivos por envio, 10 MB cada.
    public function storeAnexo(Request $request, $id)
    {
        $this->autorizar();

        $ficha = FichaAnamnese::where('excluido', 0)
            ->where('tipo', FichaAnamnese::TIPO_NOTA)
            ->findOrFail($id);

        $v = $request->validate([
            'arquivo'   => ['required', 'array', 'max:5'],
            'arquivo.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,txt'],
        ], [
            'arquivo.required' => 'Selecione ao menos um arquivo.',
            'arquivo.max'      => 'Envie no máximo 5 arquivos por vez.',
            'arquivo.*.file'   => 'Arquivo inválido.',
            'arquivo.*.max'    => 'Cada arquivo deve ter no máximo 10 MB.',
            'arquivo.*.mimes'  => 'Tipo de arquivo não permitido.',
        ]);

        $dir = 'fichas-notas/' . $ficha->id;
        foreach ($v['arquivo'] as $file) {
            // hashName() gera nome único (evita colisão/sobrescrita); mantém a
            // extensão original. O nome original fica em nome_original.
            $caminho = Storage::disk('public')->putFile($dir, $file);
            if (!$caminho) {
                return redirect()->route('anamneses.nota.edit', $ficha->id)
                    ->with('msgErro', 'Falha ao salvar o arquivo "' . $file->getClientOriginalName() . '".');
            }
            FichaAnexo::create([
                'ficha_id'      => $ficha->id,
                'caminho'       => $caminho,
                'nome_original' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'tamanho'       => $file->getSize(),
            ]);
        }

        return redirect()->route('anamneses.nota.edit', $ficha->id)
            ->with('msg', count($v['arquivo']) . ' arquivo(s) anexado(s).');
    }

    // Remove um anexo (registro + arquivo físico).
    public function destroyAnexo($id, $anexoId)
    {
        $this->autorizar();

        $anexo = FichaAnexo::where('ficha_id', $id)->findOrFail($anexoId);
        Storage::disk('public')->delete($anexo->caminho);
        $anexo->delete();

        return redirect()->route('anamneses.nota.edit', $id)->with('msg', 'Anexo removido.');
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

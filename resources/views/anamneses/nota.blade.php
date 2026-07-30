@extends('layouts.app')

@section('title', 'Anotação')

@php
    // Nota livre: título e observação ficam em JSON (dados). Respeita old() em
    // reabertura com erro de validação.
    $dados       = $ficha->dados ?? [];
    $titulo      = old('titulo', $dados['titulo'] ?? '');
    $observacao  = old('observacao', $dados['observacao'] ?? '');

    // Classifica o anexo pra escolher o leitor: pdf (PDF.js), imagem (preview) ou
    // outro (só download). Os anexos são servidos pela rota autenticada
    // anamneses.anexo.show — não mais por URL pública do disco public.
    $extensao  = fn($nome) => strtolower(pathinfo((string) $nome, PATHINFO_EXTENSION));
    $tipoAnexo = function ($anexo) use ($extensao) {
        $mime = strtolower((string) $anexo->mime);
        if (str_contains($mime, 'pdf') || $extensao($anexo->nome_original) === 'pdf') return 'pdf';
        if (str_starts_with($mime, 'image/') || in_array($extensao($anexo->nome_original), ['jpg','jpeg','png','gif','webp'], true)) return 'imagem';
        return 'outro';
    };

    // Formata bytes em KB/MB legível.
    $humano = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes <= 0) return '';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    };

    $criada = $ficha->created_at?->format('d/m/Y H:i');
    $alterada = $ficha->updated_at && $ficha->updated_at != $ficha->created_at
        ? $ficha->updated_at->format('d/m/Y H:i') : null;
@endphp

@section('main')
<div class="container mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-2">
        <div>
            <p class="fs-4 mb-0">Anotação livre</p>
            <p class="text-muted mb-0">
                <strong>Paciente:</strong> {{ $paciente->name }}
                @if($ficha->criador) &middot; criada por {{ $ficha->criador->name }}@endif
                &middot; {{ $criada }}
                @if($alterada) &middot; <span class="text-muted">atualizada em {{ $alterada }}</span>@endif
            </p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">&larr; Voltar</a>
    </div>

    <form method="POST" id="form-nota" action="{{ route('anamneses.nota.update', $ficha->id) }}" novalidate>
        @csrf

        <div class="card shadow my-3">
            <div class="card-header sec-titulo" style="background:var(--marrom); color:#fff; font-weight:600;">Nota</div>
            <div class="card-body p-3">
                <div class="mb-3">
                    <label for="titulo" class="form-label fw-semibold">Título da nota</label>
                    <input type="text" name="titulo" id="titulo" class="form-control {{ $errors->has('titulo') ? 'is-invalid' : '' }}"
                           maxlength="200" value="{{ $titulo }}">
                    @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label for="observacao" class="form-label fw-semibold">Observações</label>
                    <textarea name="observacao" id="observacao" rows="10"
                        class="form-control {{ $errors->has('observacao') ? 'is-invalid' : '' }}">{{ $observacao }}</textarea>
                    <div class="form-text">Texto livre para anotações da consulta.</div>
                    @error('observacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Ações --}}
        <div class="d-flex justify-content-end gap-2 my-3">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-outline-light" style="background-color: var(--marrom)">Salvar nota</button>
        </div>
    </form>

    {{-- ============ ARQUIVOS ANEXOS ============ --}}
    <div class="card shadow my-3">
        <div class="card-header sec-titulo" style="background:var(--marrom); color:#fff; font-weight:600;">
            Arquivos anexos
        </div>
        <div class="card-body p-3">

            {{-- Anexos existentes --}}
            @if($anexos->isNotEmpty())
                <ul class="list-group mb-3">
                    @foreach($anexos as $anexo)
                        @php
                            $tipo    = $tipoAnexo($anexo);
                            $urlShow = route('anamneses.anexo.show', [$ficha->id, $anexo->id]);
                        @endphp
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <span class="text-break">
                                📄 {{ $anexo->nome_original }}
                                @if($anexo->tamanho)<span class="badge bg-light text-dark border ms-1">{{ $humano($anexo->tamanho) }}</span>@endif
                            </span>
                            <span class="d-flex align-items-center gap-2">
                                @if(in_array($tipo, ['pdf', 'imagem'], true))
                                    <button type="button" class="btn btn-sm btn-outline-dark"
                                            data-abrir-anexo data-tipo="{{ $tipo }}" data-url="{{ $urlShow }}"
                                            data-nome="{{ $anexo->nome_original }}">Visualizar</button>
                                @endif
                                <a class="btn btn-sm btn-outline-secondary" href="{{ $urlShow }}?download=1" target="_blank" rel="noopener">Baixar</a>
                                <form method="POST" action="{{ route('anamneses.anexo.destroy', [$ficha->id, $anexo->id]) }}"
                                      onsubmit="return confirm('Remover este anexo? O arquivo será excluído.')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remover</button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted mb-3">Nenhum arquivo anexado.</p>
            @endif

            {{-- Upload de novos anexos --}}
            <form method="POST" action="{{ route('anamneses.anexo.store', $ficha->id) }}" enctype="multipart/form-data">
                @csrf
                <label for="arquivo" class="form-label fw-semibold mb-1">Anexar arquivos</label>
                <input type="file" name="arquivo[]" id="arquivo" class="form-control" multiple
                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt">
                <div class="form-text">PDF, imagens (JPG/PNG/GIF/WEBP), Word, Excel ou TXT. Até 5 arquivos por envio, 10 MB cada.</div>
                @error('arquivo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('arquivo.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div class="mt-2">
                    <button type="submit" class="btn btn-outline-dark btn-sm">Enviar anexos</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Excluir a nota (form separado) --}}
    <div class="mt-1">
        <form method="POST" action="{{ route('anamneses.destroy', $ficha->id) }}"
              onsubmit="return confirm('Excluir esta anotação? Não poderá ser desfeito.')">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm">Excluir nota</button>
        </form>
    </div>
</div>

{{-- ============ MODAL LEITOR DE ANEXOS (PDF.js / imagem) ============ --}}
<div class="modal fade" id="modalAnexo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--marrom); color:#fff;">
                <h5 class="modal-title text-break pe-3" id="tituloAnexo">Anexo</h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a id="baixarAnexo" class="btn btn-sm btn-outline-light" href="#" target="_blank" rel="noopener">Baixar</a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
            </div>
            <div class="modal-body p-0" style="position:relative; overflow:hidden; background:#525659;">
                <div id="containerAnexo" style="position:absolute; inset:0;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scriptEnd')
<script>
    (function () {
        var modalEl    = document.getElementById('modalAnexo');
        if (!modalEl) return;
        var container  = document.getElementById('containerAnexo');
        var titulo     = document.getElementById('tituloAnexo');
        var baixar     = document.getElementById('baixarAnexo');
        var modal      = bootstrap.Modal.getOrCreateInstance(modalEl);
        var VIEWER     = '/vendor/pdfjs/web/viewer.html?file=';

        document.querySelectorAll('[data-abrir-anexo]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url  = btn.dataset.url;
                var tipo = btn.dataset.tipo;
                var nome = btn.dataset.nome || 'Anexo';

                titulo.textContent = nome;
                baixar.href = url + (url.indexOf('?') !== -1 ? '&' : '?') + 'download=1';

                if (tipo === 'pdf') {
                    container.innerHTML =
                        '<iframe src="' + VIEWER + encodeURIComponent(url) + '" ' +
                        'style="width:100%;height:100%;border:0;" allow="fullscreen"></iframe>';
                } else {
                    container.innerHTML =
                        '<div style="height:100%;display:flex;align-items:center;justify-content:center;overflow:auto;padding:1rem;">' +
                        '<img src="' + url + '" alt="" style="max-width:100%;max-height:100%;">' +
                        '</div>';
                }
                modal.show();
            });
        });

        // Libera o iframe ao fechar (para renderização/PDF.js de processar).
        modalEl.addEventListener('hidden.bs.modal', function () {
            container.innerHTML = '';
        });
    })();
</script>
@endsection

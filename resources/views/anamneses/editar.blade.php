@extends('layouts.app')

@section('title', 'Ficha de Anamnese')

@php
    // Campos estruturados em JSON (dados). Acesso seguro — dados pode ser null.
    $dados = $ficha->dados ?? [];

    // Helpers de valor (respeitam old() na reabertura com erro).
    $val   = fn($k) => old($k, $dados[$k] ?? '');
    $selA  = function($k) use ($dados) {
        $s = old($k, $dados[$k] ?? []);
        return is_array($s) ? $s : [];
    };
    $chk   = function($k, $v) use ($selA) {
        return in_array($v, $selA($k), true) ? 'checked' : '';
    };
    $radio = function($k, $v) use ($dados) {
        return (old($k, $dados[$k] ?? '') === $v) ? 'checked' : '';
    };

    // Opções (espelham o Google Forms de anamnese).
    $sexo        = ['F' => 'Feminino', 'M' => 'Masculino'];
    $dificuldade = ['FALTA DE CONSTANCIA', 'FALTA DE PLANEJAMENTO ALIMENTAR', 'POUCO TEMPO PARA ELABORAR REFEIÇÕES', 'COMPULSÃO ALIMENTAR', 'ANSIEDADE', 'FALTA DE COMPROMETIMENTO COMIGO MESMA/O', 'IMPACIENCIA'];
    $simnao      = ['Não' => 'Não', 'Sim' => 'Sim'];
    $gestante    = ['SIM' => 'Sim', 'NÃO' => 'Não'];
    $tpm         = ['SIM' => 'Sim', 'NÃO' => 'Não', 'ÀS VEZES' => 'Às vezes', 'ESTOU NA MENOPAUSA' => 'Estou na menopausa'];
    $ciclo       = ['REGULADO' => 'Regulado', 'DESREGULADO' => 'Desregulado', 'FAÇO USO DE PILULA' => 'Faço uso de pílula', 'FAÇO USO DE DIU/CHIP/IMPLANOM' => 'Faço uso de DIU/CHIP/Implanon', 'ESTOU NA MENOPAUSA' => 'Estou na menopausa'];
    $atividade   = ['1 A 2 VEZES NA SEMANA' => '1 a 2 vezes na semana', '3 A 4 VEZES NA SEMANA' => '3 a 4 vezes na semana', 'TODOS OS DIAS DA SEMANA' => 'Todos os dias da semana', 'NÃO PRATICO NENHUMA ATIVIDADE FISICA' => 'Não pratico nenhuma atividade física'];
    $postura     = ['EM PÉ' => 'Em pé', 'SENTADO (A)' => 'Sentado(a)'];
    $sono        = ['TENHO INSONIAS' => 'Tenho insônias', 'DURMO BEM' => 'Durmo bem', 'DURMO, MAS ACORDO CANSADO(A)' => 'Durmo, mas acordo cansado(a)'];
    $humor       = ['IRRITADA/O' => 'Irritada(o)', 'IMPACIENTE' => 'Impaciente', 'TRANQUILA/O' => 'Tranquila(o)', 'PACIENTE' => 'Paciente', 'ANSIOSA/O' => 'Ansiosa(o)', 'TRANQUILA/O, MAS ME IRRITO FACILMENTE' => 'Tranquila(o), mas me irrito facilmente'];
    $alergias    = ['MEDICAMENTOS' => 'Medicamentos', 'ALIMENTARES' => 'Alimentares', 'NÃO TENHO' => 'Não tenho'];
    $fumante     = ['SIM' => 'Sim', 'NÃO' => 'Não'];
    $alcool      = ['NÃO BEBO' => 'Não bebo', 'BEBO OCASIONALMENTE' => 'Bebo ocasionalmente', 'BEBO COM FREQUENCIA' => 'Bebo com frequência'];
    $sintomas    = ['GRANDE DESEJO POR DOCES', 'GRANDE DESEJO POR SALGADOS', 'DORES MUSCULARES', 'ENXAQUECA', 'RETENÇÃO DE LIQUIDOS', 'FOME CONSTANTE', 'GASTRITE', 'CANDIDIASE', 'RINITIE', 'SINUSITE', 'OTITE', 'PEDRA NA VESICULA', 'PROBLEMAS DE PELE (PSORIASE/ROSÁCEA)', 'SINTO PELE, UNHAS OU CABELO FRACOS', 'OSTEOPOROSE'];
    $comum       = ['FLATULENCIAS FREQUENTEMENTE (GASES)' => 'Flatulências frequentemente (gases)', 'DISTENÇÃO (INCHAÇO) ABDOMINAL' => 'Distensão (inchaço) abdominal em algum momento do dia'];
    $agua_sede   = ['NÃO TENHO SEDE' => 'Não tenho sede', 'TENHO SEDE EXCESSIVA' => 'Tenho sede excessiva', 'BEBO AGUA, MAS POUCO' => 'Bebo água, mas pouco', 'JULGO NORMAL' => 'Julgo normal'];
    $urina       = ['AMARELO CLARO' => '#fff3b0', 'AMARELO' => '#ffd43b', 'ÂMBAR' => '#f59f00', 'MARROM' => '#8b5a2b', 'VERMELHO' => '#e03131'];
    $fezesTipos  = [1, 2, 3, 4, 5, 6, 7];

    $imc = $ficha->imc();
@endphp

@section('style')
<style>
    .urina-swatch { display:inline-block; width:22px; height:22px; border-radius:50%; border:1px solid #ccc; vertical-align:middle; margin:0 6px 0 8px; box-shadow:inset 0 0 3px rgba(0,0,0,.15); }
    .fezes-opt { width:46px; height:46px; }
    .sec-titulo { background:var(--marrom); color:#fff; font-weight:600; }
</style>
@endsection

@section('main')
<div class="container mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-2">
        <div>
            <p class="fs-4 mb-0">Ficha de Anamnese</p>
            <p class="text-muted mb-0">
                <strong>Paciente:</strong> {{ $paciente->name }}
                @if($ficha->criador) &middot; criada por {{ $ficha->criador->name }}@endif
                &middot; {{ $ficha->created_at?->format('d/m/Y H:i') }}
            </p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">&larr; Voltar</a>
    </div>

    <form method="POST" id="form-anamnese" action="{{ route('anamneses.update', $ficha->id) }}" novalidate>
        @csrf

        {{-- ============ DADOS PESSOAIS ============ --}}
        <div class="card shadow my-3">
            <div class="card-header sec-titulo">Dados pessoais</div>
            <div class="card-body p-3">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Sexo <span class="text-danger">*</span></label>
                    <div>
                        @foreach($sexo as $v => $lbl)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sexo" id="sexo_{{ $v }}" value="{{ $v }}" {{ $radio('sexo', $v) }} onchange="aplicarSexo()">
                                <label class="form-check-label" for="sexo_{{ $v }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">Perguntas de menstruação/gestação aparecem apenas para sexo feminino.</div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6"><x-app.input label="Nome completo" name="nome" :value="$val('nome')" /></div>
                    <div class="col-md-3"><x-app.input label="Idade" name="idade" :value="$val('idade')" /></div>
                    <div class="col-md-3"><x-app.input label="Ocupação profissional" name="ocupacao" :value="$val('ocupacao')" /></div>
                    <div class="col-md-3">
                        <x-app.input label="Peso (kg)" type="number" name="peso" id="peso" :value="$val('peso')" />
                    </div>
                    <div class="col-md-3">
                        <x-app.input label="Altura (cm)" type="number" name="altura" id="altura" :value="$val('altura')" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">IMC</label>
                        <input type="text" id="imc-display" class="form-control" readonly style="background:#f8f9fa"
                               value="{{ $imc !== null ? number_format($imc, 1, ',', '.') : '' }}">
                        <div class="form-text">Calculado automaticamente.</div>
                    </div>
                </div>

                <div class="mb-3 mt-2">
                    <label for="objetivo" class="form-label fw-semibold">Qual o seu objetivo?</label>
                    <textarea name="objetivo" id="objetivo" class="form-control {{ $errors->has('objetivo') ? 'is-invalid' : '' }}" rows="2">{{ $val('objetivo') }}</textarea>
                </div>

                <div class="mb-3">
                    <label for="queixas" class="form-label fw-semibold">Quais as suas principais queixas?</label>
                    <textarea name="queixas" id="queixas" class="form-control {{ $errors->has('queixas') ? 'is-invalid' : '' }}" rows="3">{{ $val('queixas') }}</textarea>
                </div>
            </div>
        </div>

        {{-- ============ HISTÓRICO DE SAÚDE ============ --}}
        <div class="card shadow my-3">
            <div class="card-header sec-titulo">Histórico de saúde</div>
            <div class="card-body p-3">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Antecedentes pessoais</label>
                    <span class="form-text d-block mb-1">Anemia, problemas hormonais, ovário policístico, doença cardiovascular, diabetes, hipertensão, câncer, acne, doenças autoimunes e cirurgias realizadas.</span>
                    <textarea name="ant_pessoais" class="form-control" rows="2">{{ $val('ant_pessoais') }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Antecedentes familiares</label>
                    <span class="form-text d-block mb-1">Anemia, problemas hormonais, ovário policístico, doença cardiovascular, diabetes, hipertensão, câncer, acne, doenças autoimunes e cirurgias realizadas.</span>
                    <textarea name="ant_familiares" class="form-control" rows="2">{{ $val('ant_familiares') }}</textarea>
                </div>

                <div class="mb-3">
                    <span class="form-label fw-semibold d-block">Qual a maior dificuldade que você encontra em começar um novo estilo de vida?</span>
                    <div class="row">
                        @foreach($dificuldade as $opt)
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dificuldade[]" value="{{ $opt }}" id="dif_{{ $loop->index }}" {{ $chk('dificuldade', $opt) }}>
                                    <label class="form-check-label" for="dif_{{ $loop->index }}">{{ $opt }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <span class="form-label fw-semibold d-block">Faz uso de algum medicamento?</span>
                        @foreach($simnao as $v => $lbl)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="medicamentos" value="{{ $v }}" id="med_{{ $v }}" {{ $radio('medicamentos', $v) }}>
                                <label class="form-check-label" for="med_{{ $v }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                        <x-app.input label="Quais?" name="medicamentos_quais" :value="$val('medicamentos_quais')" />
                    </div>
                    <div class="col-md-6">
                        <span class="form-label fw-semibold d-block">Fez uso de antibióticos ou corticoides recentemente?</span>
                        @foreach($simnao as $v => $lbl)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="antibioticos" value="{{ $v }}" id="ant_{{ $v }}" {{ $radio('antibioticos', $v) }}>
                                <label class="form-check-label" for="ant_{{ $v }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                        <x-app.input label="Quais?" name="antibioticos_quais" :value="$val('antibioticos_quais')" />
                    </div>
                </div>

                {{-- Saúde da mulher: só aparece para sexo feminino --}}
                <div data-feminino class="border rounded p-3 mt-2" style="background:#fff8f3">
                    <p class="fw-semibold mb-3" style="color:var(--marrom)">Saúde da mulher</p>

                    <div class="mb-3">
                        <span class="form-label fw-semibold d-block">Gestante?</span>
                        @foreach($gestante as $v => $lbl)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gestante" value="{{ $v }}" id="gest_{{ $v }}" {{ $radio('gestante', $v) }}>
                                <label class="form-check-label" for="gest_{{ $v }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <span class="form-label fw-semibold d-block">Tem TPM intensa?</span>
                        @foreach($tpm as $v => $lbl)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tpm" value="{{ $v }}" id="tpm_{{ $v }}" {{ $radio('tpm', $v) }}>
                                <label class="form-check-label" for="tpm_{{ $v }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="mb-0">
                        <span class="form-label fw-semibold d-block">Ciclo menstrual</span>
                        @foreach($ciclo as $v => $lbl)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ciclo" value="{{ $v }}" id="cicl_{{ $v }}" {{ $radio('ciclo', $v) }}>
                                <label class="form-check-label" for="cicl_{{ $v }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ ESTILO DE VIDA ============ --}}
        <div class="card shadow my-3">
            <div class="card-header sec-titulo">Estilo de vida</div>
            <div class="card-body p-3">

                <div class="mb-3">
                    <span class="form-label fw-semibold d-block">Atividade física</span>
                    @foreach($atividade as $v => $lbl)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="atividade" value="{{ $v }}" id="ativ_{{ $loop->index }}" {{ $radio('atividade', $v) }}>
                            <label class="form-check-label" for="ativ_{{ $loop->index }}">{{ $lbl }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><x-app.input label="Se pratica, qual modalidade?" name="modalidade" :value="$val('modalidade')" /></div>
                    <div class="col-md-6">
                        <span class="form-label fw-semibold d-block">Passa a maior parte do dia:</span>
                        @foreach($postura as $v => $lbl)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="postura" value="{{ $v }}" id="post_{{ $v }}" {{ $radio('postura', $v) }}>
                                <label class="form-check-label" for="post_{{ $v }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <span class="form-label fw-semibold d-block">Como é sua noite de sono?</span>
                        @foreach($sono as $v => $lbl)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sono" value="{{ $v }}" id="sono_{{ $loop->index }}" {{ $radio('sono', $v) }}>
                                <label class="form-check-label" for="sono_{{ $loop->index }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-4"><x-app.input label="Dorme cerca de quantas horas por noite?" name="horas_sono" :value="$val('horas_sono')" /></div>
                    <div class="col-md-4"><x-app.input label="Como sente sua energia durante o dia?" name="energia" :value="$val('energia')" /></div>
                </div>

                <div class="mb-3 mt-2">
                    <span class="form-label fw-semibold d-block">Como é seu humor?</span>
                    <div class="row">
                        @foreach($humor as $v => $lbl)
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="humor[]" value="{{ $v }}" id="hum_{{ $loop->index }}" {{ $chk('humor', $v) }}>
                                    <label class="form-check-label" for="hum_{{ $loop->index }}">{{ $lbl }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <x-app.input label="Memória e concentração, como estão?" name="memoria" :value="$val('memoria')" />
            </div>
        </div>

        {{-- ============ ALERGIAS E HÁBITOS ============ --}}
        <div class="card shadow my-3">
            <div class="card-header sec-titulo">Alergias e hábitos</div>
            <div class="card-body p-3">

                <div class="row g-3">
                    <div class="col-md-4">
                        <span class="form-label fw-semibold d-block">Você tem alergias?</span>
                        @foreach($alergias as $v => $lbl)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="alergias[]" value="{{ $v }}" id="aler_{{ $loop->index }}" {{ $chk('alergias', $v) }}>
                                <label class="form-check-label" for="aler_{{ $loop->index }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-4">
                        <span class="form-label fw-semibold d-block">Fumante?</span>
                        @foreach($fumante as $v => $lbl)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="fumante" value="{{ $v }}" id="fum_{{ $v }}" {{ $radio('fumante', $v) }}>
                                <label class="form-check-label" for="fum_{{ $v }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-4">
                        <span class="form-label fw-semibold d-block">Bebida alcoólica</span>
                        @foreach($alcool as $v => $lbl)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="alcool" value="{{ $v }}" id="alc_{{ $loop->index }}" {{ $radio('alcool', $v) }}>
                                <label class="form-check-label" for="alc_{{ $loop->index }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mb-3 mt-2">
                    <label for="alergia_desc" class="form-label fw-semibold">Se tem alguma alergia, descreva qual:</label>
                    <textarea name="alergia_desc" id="alergia_desc" class="form-control" rows="2">{{ $val('alergia_desc') }}</textarea>
                </div>

                <div class="mb-3">
                    <span class="form-label fw-semibold d-block">Selecione as alternativas que você se identifica:</span>
                    <div class="row">
                        @foreach($sintomas as $opt)
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sintomas[]" value="{{ $opt }}" id="sint_{{ $loop->index }}" {{ $chk('sintomas', $opt) }}>
                                    <label class="form-check-label" for="sint_{{ $loop->index }}">{{ $opt }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <span class="form-label fw-semibold d-block">Selecione o que é comum para você:</span>
                    @foreach($comum as $v => $lbl)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="comum[]" value="{{ $v }}" id="comum_{{ $loop->index }}" {{ $chk('comum', $v) }}>
                            <label class="form-check-label" for="comum_{{ $loop->index }}">{{ $lbl }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ============ FUNÇÕES INTESTINAIS E HIDRATAÇÃO ============ --}}
        <div class="card shadow my-3">
            <div class="card-header sec-titulo">Funções intestinais e hidratação</div>
            <div class="card-body p-3">

                <div class="mb-3">
                    <span class="form-label fw-semibold d-block">Selecione o tipo das suas fezes (escala de Bristol):</span>
                    <img src="{{ asset('img/anamnese/fezes-bristol.jpg') }}" alt="Escala de Bristol" class="img-fluid rounded mb-2" style="max-width:260px">
                    <div class="d-flex flex-wrap gap-3 mt-1">
                        @foreach($fezesTipos as $t)
                            <label class="d-flex flex-column align-items-center" style="cursor:pointer">
                                <input type="radio" name="fezes" value="{{ $t }}" {{ $radio('fezes', (string)$t) }}>
                                <span class="fezes-opt badge bg-secondary mt-1 d-flex align-items-center justify-content-center">{{ $t }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mb-3">
                    <span class="form-label fw-semibold d-block">Qual a cor da sua urina?</span>
                    <div class="d-flex flex-wrap">
                        @foreach($urina as $opt => $cor)
                            <label class="d-flex align-items-center me-3 mb-2" style="cursor:pointer">
                                <input type="radio" name="urina" value="{{ $opt }}" {{ $radio('urina', $opt) }}>
                                <span class="urina-swatch" style="background:{{ $cor }}"></span>{{ $opt }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="form-label fw-semibold d-block">Água / sede:</span>
                        @foreach($agua_sede as $v => $lbl)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="agua_sede[]" value="{{ $v }}" id="ags_{{ $loop->index }}" {{ $chk('agua_sede', $v) }}>
                                <label class="form-check-label" for="ags_{{ $loop->index }}">{{ $lbl }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6"><x-app.input label="Quanto costuma ingerir de água por dia?" name="agua_qtd" :value="$val('agua_qtd')" /></div>
                </div>
            </div>
        </div>

        {{-- ============ SOBRE SUA ALIMENTAÇÃO ============ --}}
        <div class="card shadow my-3">
            <div class="card-header sec-titulo">Sobre sua alimentação</div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Que alimento você não gosta?</label>
                        <textarea name="al_nao_gosta" class="form-control" rows="2">{{ $val('al_nao_gosta') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Que alimento comum você gosta, mas não tem costume de comer?</label>
                        <textarea name="al_gosta_nao_come" class="form-control" rows="2">{{ $val('al_gosta_nao_come') }}</textarea>
                    </div>
                </div>
                <div class="mt-2"><x-app.input label="Que alimento é essencial pra você?" name="al_essencial" :value="$val('al_essencial')" /></div>
                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Rotina alimentar no café da manhã:</label>
                        <textarea name="rotina_cafe" class="form-control" rows="3">{{ $val('rotina_cafe') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Rotina alimentar no almoço:</label>
                        <textarea name="rotina_almoco" class="form-control" rows="3">{{ $val('rotina_almoco') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Rotina alimentar no jantar:</label>
                        <textarea name="rotina_jantar" class="form-control" rows="3">{{ $val('rotina_jantar') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ações --}}
        <div class="d-flex justify-content-end gap-2 my-4">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-outline-light" style="background-color: var(--marrom)">Salvar ficha</button>
        </div>
    </form>

    {{-- Excluir ficha (form separado) --}}
    <div class="mt-1">
        <form method="POST" action="{{ route('anamneses.destroy', $ficha->id) }}" onsubmit="return confirm('Excluir esta ficha de anamnese? Não poderá ser desfeito.')">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm">Excluir ficha</button>
        </form>
    </div>
</div>
@endsection

@section('scriptEnd')
<script>
    // IMC ao vivo a partir de peso (kg) e altura (cm).
    (function () {
        const peso = document.getElementById('peso');
        const altura = document.getElementById('altura');
        const saida = document.getElementById('imc-display');
        if (!peso || !altura || !saida) return;
        const calc = () => {
            const p = parseFloat(peso.value), a = parseFloat(altura.value);
            if (!p || !a || a <= 0) { saida.value = ''; return; }
            saida.value = (p / Math.pow(a / 100, 2)).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        };
        peso.addEventListener('input', calc);
        altura.addEventListener('input', calc);
        calc();
    })();

    // Mostra/oculta o bloco "Saúde da mulher" conforme o sexo.
    function aplicarSexo() {
        const sel = document.querySelector('input[name="sexo"]:checked');
        const feminino = sel && sel.value === 'F';
        document.querySelectorAll('[data-feminino]').forEach(el => {
            el.classList.toggle('d-none', !feminino);
            // Desabilita os campos ocultos para não serem enviados.
            el.querySelectorAll('input, select, textarea').forEach(i => { i.disabled = !feminino; });
        });
    }
    document.querySelectorAll('input[name="sexo"]').forEach(r => r.addEventListener('change', aplicarSexo));
    aplicarSexo();
</script>
@endsection

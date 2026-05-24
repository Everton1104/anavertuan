@extends("layouts.app")
@section("title", "Dashboard")
@section('style')
    <style>
        .card-header {
            background-color: var(--branco);
        }
        .consulta-card {
            border-radius: 12px;
            border-left: 6px solid #28a745;
            background: #f5f4f4;
            padding: 16px;
            margin-bottom: 16px;
        }
        .consulta-pendente {
            border-left-color: #ffc107;
        }
        .consulta-data {
            font-weight: bold;
            font-size: 1.1rem;
            color: #28a745;
        }
        .consulta-hora {
            font-size: 0.95rem;
            color: #555;
        }
        .consulta-info {
            font-size: 1rem;
        }
    </style>
@endsection
@section("main")
<div class="container mb-5">
    <div class="my-3">
        <p class="fs-4">Olá, {{ ucfirst(auth()->user()->name) }}</p>
    </div>

    {{-- Seção de controle para administradores --}}
    @if(auth()->user()->adm == 1 || auth()->user()->func == 1)

        {{-- Modal Adicionar Usuários --}}
        <x-app.modal id="modal-add-usuario" title="Adicionar novo usuário" :btn="[['lbl' => 'Adicionar', 'color' => 'primary', 'onclick' => '$( \'#form-add-usuario\').submit()']]">
            <form method="POST" id="form-add-usuario" action="{{ route('add-usuario') }}" novalidate>
                @csrf
                @method('post')
                <x-app.input label="Nome" type="text" name="nome" id="nome_usuario" required="true" />
                <x-app.input label="WhatsApp" type="tel" name="whatsapp" id="whatsapp_usuario" required="true" />
                <x-app.input label="Senha" type="password" name="senha" id="senha_usuario" required="true" />
                <x-app.input label="Confirmar Senha" type="password" name="senha_confirmation" id="senha_confirmation_usuario" required="true" />
                <x-app.radio name="tipo"
                    :options="[
                        'adm' => 'Administrador',
                        'func' => 'Funcionário',
                        'cli' => 'Cliente'
                    ]"
                />
            </form>
        </x-app.modal>

        {{-- Modal Editar Usuários --}}
        <x-app.modal id="modal-edt-usuario" title="Editar usuário" :btn="[['lbl' => 'Atualizar', 'color' => 'success', 'onclick' => '$(\'#form-edt-usuario\').submit()']]">
            <form method="POST" id="form-edt-usuario" action="{{ route('editar-usuario') }}" novalidate>
                @csrf
                @method('post')
                <x-app.input type="hidden" name="id" id="edt-id" required="true" />
                <x-app.input label="Nome" type="text" name="nome_edt" id="edt-name" required="true" />
                <x-app.input label="WhatsApp" type="tel" name="whatsapp_edt" id="edt-whatsapp" />
                <x-app.input label="Senha" type="password" name="senha_edt" id="senha_usuario_edt" required="true" />
                <x-app.input label="Confirmar Senha" type="password" name="senha_confirmation_edt" id="senha_confirmation_usuario_edt" required="true" />
                <x-app.radio name="tipo_edt"
                    :options="[
                        'adm' => 'Administrador',
                        'func' => 'Funcionário',
                        'cli' => 'Cliente'
                    ]"
                />
            </form>
        </x-app.modal>

        {{-- Modal Excluir Usuários --}}
        <x-app.modal id="modal-exc-usuario" title="Excluir Usuário" :btn="[['lbl' => 'Excluir', 'color' => 'danger', 'onclick' => '$(\'#form-excluir-usuario\').submit()']]">
            <form id="form-excluir-usuario" action="{{route('delete-usuario')}}" method="post">
                @csrf
                @method('post')
                <p class="fs-3">Tem certeza que deseja excluir o usuário <span id="excluir-usuario-nome"></span>?</p>
                <input class="d-none" type="text" name="id" id="excluir-usuario-id" value="">
            </form>
        </x-app.modal>

        {{-- Contas de usuário --}}
        <div class="card shadow my-3">
            <div class="card-header"
                data-bs-toggle="collapse"
                data-bs-target="#collapseUsuarios"
                style="cursor: pointer">
                Controle de Contas de Usuário
            </div>
            <div id="collapseUsuarios" class="collapse">
                <div class="card-body p-3 row">
                    <div>
                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-usuario" style="background-color: var(--marrom)">Novo Usuário</button>
                    </div>
                    <div class="input-group my-3">
                        <span class="input-group-text bg-primary" id="basic-addon1">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/></svg>
                        </span>
                        <input type="search" class="form-control" id="search" placeholder="Pesquisar por nome" aria-label="Pesquisar" aria-describedby="basic-addon1" oninput="searchUsuario()">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">&nbsp;</th>
                                    <th scope="col">Nome</th>
                                    <th scope="col">WhatsApp</th>
                                    <th scope="col">Administrador</th>
                                    <th scope="col">Colaborador</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-usuarios">
                                @foreach ($users as $user)
                                    <tr>
                                        @if ($user->adm != 1 || Auth()->user()->id == 1)
                                            @if ($user->func != 1 || Auth()->user()->adm == 1)
                                                <td class="d-flex">
                                                    <svg style="cursor: pointer" onclick="excluirUsuario({{$user->id}},'{{$user->name}}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                                    <svg style="cursor: pointer" onclick="editarUsuario({{$user->id}})" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                                                </td>
                                            @else
                                                <td>COLAB</td>
                                            @endif
                                        @else
                                            <td>ADM</td>
                                        @endif
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->whatsapp ?? '—' }}</td>
                                        <td>{{ $user->adm > 0 ? 'Sim' : 'Não' }}</td>
                                        <td>{{ $user->func > 0 ? 'Sim' : 'Não' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-end">
                            {{ $users->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal Adicionar Serviços --}}
        <x-app.modal id="modal-add-servico" title="Adicionar Novo Serviço" :btn="[['lbl' => 'Adicionar', 'color' => 'primary', 'onclick' => '$(\'#form-add-servico\').submit()']]">
            <form method="POST" id="form-add-servico" action="{{ route('servico.store') }}" novalidate>
                @csrf
                @method('post')
                <x-app.input label="Nome do Serviço" type="text" name="descricao" id="servico_desc" required="true" />
                <p class="fs-5 my-2">Duração do Serviço</p>
                <x-app.select label="Horas" name="duracao_h" required="true" :options="['00'=>'00', '01'=>'01', '02'=>'02']" />
                <x-app.select label="Minutos" name="duracao_m" required="true" :options="['00'=>'00', '30'=>'30']" />
            </form>
        </x-app.modal>

        {{-- Modal Editar Serviços --}}
        <x-app.modal id="modal-edt-servico" title="Editar Serviço" :btn="[['lbl' => 'Atualizar', 'color' => 'success', 'onclick' => '$(\'#form-edt-servico\').submit()']]">
            <form method="POST" id="form-edt-servico" action="{{ route('editar-servico') }}" novalidate>
                @csrf
                @method('post')
                <input type="hidden" name="id_edt_servico" id="id_edt_servico" value="{{ old('id_edt_servico') ?? '' }}">
                <x-app.input label="Descrição" type="text" name="descricao_edt_servico" required="true" />
                <p class="fs-5 my-2">Duração do Serviço</p>
                <x-app.select label="Horas" name="duracao_h_edt_servico" required="true" :options="['00'=>'00', '01'=>'01', '02'=>'02']" />
                <x-app.select label="Minutos" name="duracao_m_edt_servico" required="true" :options="['00'=>'00', '30'=>'30']" />
                <x-app.radio name="status_servico"
                    :options="[
                        '0' => 'INATIVO',
                        '1' => 'ATIVO'
                    ]"
                />
            </form>
        </x-app.modal>

        {{-- Modal Excluir Serviços --}}
        <x-app.modal id="modal-exc-servico" title="Excluir Serviço" :btn="[['lbl' => 'Excluir', 'color' => 'danger', 'onclick' => '$(\'#form-excluir-servico\').submit()']]">
            <form id="form-excluir-servico" action="{{route('delete-servico')}}" method="post">
                @csrf
                @method('post')
                <p class="fs-3">Tem certeza que deseja excluir o serviço <span id="excluir-servico-nome"></span>?</p>
                <input class="d-none" type="text" name="excluir-servico-id" id="excluir-servico-id" value="">
            </form>
        </x-app.modal>

        {{-- Serviços --}}
        <div class="card shadow my-3">
            <div class="card-header"
                data-bs-toggle="collapse"
                data-bs-target="#collapseServicos"
                style="cursor: pointer">
                Serviços
            </div>
            <div id="collapseServicos" class="collapse">
                <div class="card-body p-3 row">
                    <div>
                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-servico" style="background-color: var(--marrom)">Novo Serviço</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">&nbsp;</th>
                                    <th scope="col">Serviço</th>
                                    <th scope="col">Duração</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($servicos as $servico)
                                    <tr>
                                        <td class="d-flex">
                                            <svg style="cursor: pointer" onclick="excluirServico({{$servico->id}},'{{$servico->descricao}}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                            <svg style="cursor: pointer" onclick="editarServico('{{$servico->id}}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                                        </td>
                                        <td>{{ $servico->descricao }}</td>
                                        <td>{{ $servico->duracao }}</td>
                                        <td class="text-{{ $servico->status == 0 ? 'danger' : 'success' }}">{{ $servico->status == 0 ? 'INATIVO' : 'ATIVO' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Gerenciar slots de um dia --}}
        <div class="modal fade" id="modal-gerenciar-slots" tabindex="-1">
            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="mgm-modal-titulo">Gerenciar disponibilidade</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="mgm-data-atual">
                        {{-- Ações rápidas --}}
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button class="btn btn-sm btn-outline-success" onclick="mgmPreset('comercial')">Horário comercial (08:00–18:00)</button>
                            <button class="btn btn-sm btn-outline-primary" onclick="mgmPreset('tudo')">Selecionar tudo</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="mgmPreset('limpar')">Limpar tudo</button>
                        </div>
                        {{-- Lista de slots --}}
                        <div id="mgm-slots-container">
                            <p class="text-muted">Carregando...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" onclick="mgmSalvarSlots()" id="mgm-btn-salvar">Salvar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Gerenciar Disponibilidade da Agenda --}}
        <div class="card shadow my-3">
            <div class="card-header">Gerenciar Disponibilidade da Agenda</div>
            <div class="card-body p-3">
                <p class="text-muted small mb-3">Clique em um dia para definir quais horários estão disponíveis.</p>

                {{-- Calendário de gestão (sempre visível) --}}
                <div id="mgm-calendario-container" style="max-width:420px; margin:auto; font-family:Arial,sans-serif">
                    <div class="cal-header">
                        <span id="mgm-cal-prev" role="button" style="cursor:pointer">◀</span>
                        <h3 id="mgm-cal-mes-ano" class="mb-0"></h3>
                        <span id="mgm-cal-next" role="button" style="cursor:pointer">▶</span>
                    </div>
                    <div class="cal-header" style="justify-content:center; margin-bottom:10px">
                        <span id="mgm-cal-today" class="btn btn-sm btn-primary">Hoje</span>
                    </div>
                    <div class="cal-semana">
                        <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div>
                        <div>Qui</div><div>Sex</div><div>Sab</div>
                    </div>
                    <div id="mgm-cal-dias" class="cal-grid"></div>
                </div>

                {{-- Legenda --}}
                <div class="d-flex gap-3 mt-3 justify-content-center flex-wrap">
                    <span><span style="display:inline-block;width:14px;height:14px;background:#e9f7ef;border-radius:3px;vertical-align:middle"></span> Com horários</span>
                    <span><span style="display:inline-block;width:14px;height:14px;background:#f8d7da;border-radius:3px;vertical-align:middle"></span> Sem horários</span>
                    <span><span style="display:inline-block;width:14px;height:14px;background:#f1f1f1;opacity:.4;border-radius:3px;vertical-align:middle"></span> Passado</span>
                </div>
            </div>
        </div>

        {{-- Avisos --}}
        <div class="card shadow my-3">
            <div class="card-header d-flex justify-content-between align-items-center"
                 data-bs-toggle="collapse" data-bs-target="#collapseAvisos" style="cursor:pointer">
                <span>Avisos</span>
                @if($avisos->isNotEmpty())
                    <span class="badge bg-warning text-dark">{{ $avisos->count() }}</span>
                @endif
            </div>
            <div id="collapseAvisos" class="collapse {{ $avisos->isNotEmpty() ? 'show' : '' }}">
                <div class="card-body p-3" id="avisos-lista">
                    @forelse($avisos as $aviso)
                        <div class="d-flex justify-content-between align-items-start p-2 border-bottom" id="aviso-{{ $aviso->id }}">
                            <div>
                                @if($aviso->tipo === 'cancelamento')
                                    <strong>{{ ucfirst($aviso->user->name) }}</strong> cancelou a consulta de
                                    <em>{{ $aviso->servico->descricao }}</em> do dia
                                    {{ $aviso->data_antiga->format('d/m/Y') }} às {{ $aviso->data_antiga->format('H:i') }}.
                                @else
                                    <strong>{{ ucfirst($aviso->user->name) }}</strong> reagendou
                                    <em>{{ $aviso->servico->descricao }}</em>:
                                    {{ $aviso->data_antiga->format('d/m/Y H:i') }}
                                    <strong>→</strong>
                                    {{ $aviso->data_nova->format('d/m/Y H:i') }}
                                @endif
                                <br><small class="text-muted">{{ $aviso->created_at->diffForHumans() }}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary ms-3 flex-shrink-0"
                                    onclick="dispensarAviso({{ $aviso->id }})">
                                Dispensar
                            </button>
                        </div>
                    @empty
                        <p class="text-muted mb-0" id="avisos-vazio">Nenhum aviso pendente.</p>
                    @endforelse
                </div>
            </div>
        </div>

    @endif

    {{-- Agendamentos --}}
    <div class="container py-4">
        <h3 class="mb-4">Consultas Agendadas</h3>

        @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
            {{-- Modal Adicionar/Editar Agendamentos --}}
            <x-app.modal id="modal-add-agenda" title="Adicionar Nova Consulta" :btn="[['lbl' => 'Adicionar', 'color' => 'primary', 'onclick' => '$(\'#form-add-agenda\').submit()']]">
                <form method="POST" id="form-add-agenda" action="{{ route('agenda.store') }}" novalidate>
                    @csrf
                    @method('post')
                    <x-app.select label="Cliente" name="user_id" required="true" :options="$clientes->pluck('name', 'id')" />
                    <x-app.select label="Selecione o serviço" name="servico_id" required="true" :options="$servicos->where('status', 1)->mapWithKeys(fn($s) => [$s->id => $s->descricao . ' — ' . substr($s->duracao, 0, 5)])" />
                    <input type="hidden" name="data_inicio" id="data_inicio" />
                    <input type="hidden" name="data_fim" id="data_fim" />
                    <input type="hidden" id="dia_selecionado" name="dia_selecionado">
                    <input type="hidden" id="hora_selecionada" name="hora_selecionada" value="{{ old('hora_selecionada') }}">
                    <input type="hidden" id="agendamento_id" name="agendamento_id" value="{{ old('agendamento_id') }}">
                    <x-app.calendar :servicos="$servicos" :is-adm="true" />
                </form>
            </x-app.modal>

            <div class="my-3">
                <button class="btn btn-outline-light" onclick="abrirNovoAgendamento()" style="background-color: var(--marrom)">Novo Agendamento</button>
            </div>

            <div class="input-group my-3">
                <span class="input-group-text bg-primary" id="basic-addon1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"/></svg>
                </span>
                <input type="search" class="form-control" id="search-consulta" placeholder="Filtrar por paciente" aria-label="Filtrar por paciente" aria-describedby="basic-addon1" oninput="searchConsulta()">
            </div>
        @endif

        @if(!auth()->user()->adm && !auth()->user()->func)
            <x-app.modal id="modal-reagendar" title="Reagendar Consulta"
                :btn="[['lbl' => 'Confirmar', 'color' => 'primary', 'onclick' => '$(\'#form-reagendar\').submit()']]">
                <form method="POST" id="form-reagendar" action="{{ route('agenda.store') }}" novalidate>
                    @csrf
                    <input type="hidden" name="user_id" value="{{ auth()->id() }}" />
                    <input type="hidden" name="agendamento_id" id="agendamento_id" value="{{ old('agendamento_id') }}" />
                    <input type="hidden" name="data_inicio" id="data_inicio" />
                    <input type="hidden" name="data_fim" id="data_fim" />
                    <input type="hidden" id="dia_selecionado" name="dia_selecionado" />
                    <input type="hidden" id="hora_selecionada" name="hora_selecionada" value="{{ old('hora_selecionada') }}" />
                    <input type="hidden" name="servico_id" id="servico_id" value="{{ old('servico_id') }}" />
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Serviço</label>
                        <input type="text" id="servico_nome_display" class="form-control" readonly
                               style="background:#f8f9fa; cursor:default"
                               value="{{ old('servico_id') ? ($servicos->firstWhere('id', old('servico_id'))?->descricao ?? '') : '' }}" />
                    </div>
                    <x-app.calendar :servicos="$servicos" :is-adm="false" />
                </form>
            </x-app.modal>
        @endif

        {{-- Modal: ação sobre consulta (reagendar / excluir / dispensar) --}}
        <div class="modal fade" id="modal-acao-consulta" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-acao-titulo">O que deseja fazer?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="modal-acao-info" class="mb-0"></p>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dispensar</button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" id="btn-acao-reagendar">Reagendar</button>
                            <button type="button" class="btn btn-danger" id="btn-acao-excluir">Excluir</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: confirmar exclusão/cancelamento --}}
        <div class="modal fade" id="modal-confirmar-exclusao" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-confirmar-titulo">Confirmar exclusão</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p id="modal-confirmar-info" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                        <button type="button" class="btn btn-danger" id="btn-confirmar-excluir">Confirmar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="accordion shadow" id="accordionMeses">
            @foreach ($consultas as $mes => $lista)
                @php
                    $id = Str::slug($mes);
                    $isOpen = $mes === $mesAtual ? 'show' : '';
                    $isCollapsed = $mes === $mesAtual ? '' : 'collapsed';
                @endphp
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-{{ $id }}">
                        <button class="accordion-button {{ $isCollapsed }}" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#collapse-{{ $id }}">
                            {{ ucfirst($mes) }}
                        </button>
                    </h2>
                    <div id="collapse-{{ $id }}" class="accordion-collapse collapse {{ $isOpen }}"
                        data-bs-parent="#accordionMeses">
                        <div class="accordion-body">
                            @forelse ($lista as $consulta)
                                <div class="consulta-card {{ !$consulta->confirmado ? 'consulta-pendente' : '' }} d-flex justify-content-between align-items-center" data-consulta-id="{{ $consulta->id }}">
                                    <div class="d-flex flex-grow-1" @if(auth()->user()->adm == 1 || auth()->user()->func == 1) onclick="editarConsulta({{ $consulta->id }})" style="cursor: pointer" @endif>
                                        <div class="me-3 text-center">
                                            <div class="consulta-data">
                                                Dia {{ \Carbon\Carbon::parse($consulta->data_inicio)->format('d') }}
                                            </div>
                                            <div class="consulta-hora">
                                                {{ \Carbon\Carbon::parse($consulta->data_inicio)->format('H:i') }} <br>às<br>
                                                {{ \Carbon\Carbon::parse($consulta->data_fim)->format('H:i') }}
                                            </div>
                                        </div>
                                        <div>
                                            <strong>Paciente:</strong> {{ $consulta->user->name }}<br>
                                            <strong>Serviço:</strong> {{ $consulta->servico->descricao ?? '' }}<br>
                                            @if(!$consulta->confirmado)
                                                @if(auth()->user()->adm || auth()->user()->func)
                                                    <span class="badge bg-warning text-dark mt-1">Pendente confirmação</span>
                                                @else
                                                    <span class="badge bg-info text-dark mt-1">Aguardando confirmação</span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
                                        @if(!$consulta->confirmado)
                                            <button class="btn btn-sm btn-success btn-confirmar ms-2"
                                                    onclick="event.stopPropagation(); confirmarConsulta({{ $consulta->id }})">
                                                Confirmar
                                            </button>
                                        @endif
                                        <button class="btn btn-sm btn-danger ms-2"
                                                onclick="event.stopPropagation(); excluirConsultaById({{ $consulta->id }})">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                        </button>
                                    @elseif($consulta->data_inicio->isFuture())
                                        <button class="btn btn-sm btn-outline-primary ms-2"
                                                onclick="reagendarConsulta({{ $consulta->id }}, {{ $consulta->servico_id }}, @json($consulta->servico->descricao ?? ''))">
                                            Reagendar
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger ms-2"
                                                onclick="cancelarConsulta({{ $consulta->id }}, {{ $consulta->servico_id }}, @json($consulta->servico->descricao ?? ''))">
                                            Cancelar
                                        </button>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted">Nenhuma consulta neste mês.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('scriptEnd')
    @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
    <script>
        // ── Mapa de consultas para acesso por id ─────────────────────────────
        const consultaMap = {};
        @foreach($consultas->flatten() as $c)
            consultaMap[{{ $c->id }}] = {
                id: {{ $c->id }},
                user_id: {{ $c->user_id }},
                servico_id: {{ $c->servico_id }},
                confirmado: {{ $c->confirmado ? 'true' : 'false' }},
                user: { name: @json($c->user->name) },
                servico: { descricao: @json($c->servico->descricao ?? '') },
                data_inicio: @json($c->data_inicio->format('Y-m-d H:i:s')),
                data_fim: @json($c->data_fim->format('Y-m-d H:i:s')),
                mes: @json(\Carbon\Carbon::parse($c->data_inicio)->locale('pt_BR')->translatedFormat('F Y')),
                inicio: @json($c->data_inicio->format('H:i')),
                fim: @json($c->data_fim->format('H:i')),
                dia: @json($c->data_inicio->format('d')),
            };
        @endforeach

        // ── Agendamentos ─────────────────────────────────────────────────────
        function abrirNovoAgendamento() {
            document.getElementById('agendamento_id').value = '';
            document.getElementById('user_id').value = '';
            document.getElementById('servico_id').value = '';
            document.getElementById('dia_selecionado').value = '';
            document.getElementById('hora_selecionada').value = '';
            document.getElementById('data_inicio').value = '';
            document.getElementById('data_fim').value = '';
            document.getElementById('horarios').innerHTML = '';
            document.getElementById('horarios-erro').textContent = '';
            document.querySelectorAll('.cal-dia').forEach(d => d.classList.remove('cal-selecionado'));
            dataAtual = new Date();
            carregarMes();
            new bootstrap.Modal(document.getElementById('modal-add-agenda')).show();
        }

        function editarConsulta(id) {
            axios.get(`/agenda/${id}/edit`)
                .then(res => {
                    const c = res.data;

                    document.getElementById('user_id').value       = c.user_id;
                    document.getElementById('servico_id').value    = c.servico_id;
                    document.getElementById('agendamento_id').value = c.id;
                    document.getElementById('dia_selecionado').value = c.data_inicio.split(' ')[0];
                    document.getElementById('hora_selecionada').value = c.data_inicio.split(' ')[1].substring(0, 5);
                    document.getElementById('data_inicio').value   = c.data_inicio;
                    document.getElementById('data_fim').value      = c.data_fim;

                    // Navegar o calendário para o mês da consulta
                    dataAtual = new Date(c.data_inicio.replace(' ', 'T'));

                    new bootstrap.Modal(document.getElementById('modal-add-agenda')).show();

                    carregarMes().then(() => {
                        const diaNumero = parseInt(c.data_inicio.split(' ')[0].split('-')[2]);
                        document.querySelectorAll('.cal-dia').forEach(d => {
                            if (parseInt(d.textContent) === diaNumero && !d.classList.contains('disabled')) {
                                d.classList.add('cal-selecionado');
                            }
                        });
                        getHoras(c.data_inicio.split(' ')[0]);
                    });
                });
        }

        function excluirConsultaById(id) {
            const c = consultaMap[id];
            if (!c) return;

            document.getElementById('modal-acao-titulo').textContent = 'O que deseja fazer?';
            document.getElementById('modal-acao-info').textContent =
                `Consulta de ${c.user.name} — ${c.servico.descricao} — Dia ${c.dia} às ${c.inicio}`;
            document.getElementById('btn-acao-reagendar').textContent = 'Reagendar';
            document.getElementById('btn-acao-excluir').textContent   = 'Excluir sem reagendar';

            const modalAcao = new bootstrap.Modal(document.getElementById('modal-acao-consulta'));

            document.getElementById('btn-acao-reagendar').onclick = () => {
                modalAcao.hide();
                editarConsulta(id);
            };

            document.getElementById('btn-acao-excluir').onclick = () => {
                document.getElementById('modal-confirmar-titulo').textContent = 'Confirmar exclusão';
                document.getElementById('modal-confirmar-info').textContent =
                    `Tem certeza que deseja excluir a consulta de ${c.user.name} (${c.servico.descricao}) das ${c.inicio} às ${c.fim} do dia ${c.dia}?`;
                document.getElementById('btn-confirmar-excluir').textContent = 'Excluir';

                const modalConfirmar = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao'));

                document.getElementById('btn-confirmar-excluir').onclick = () => {
                    axios.delete(`/agenda/${id}`)
                        .then(() => {
                            modalConfirmar.hide();
                            const card = document.querySelector(`.consulta-card[data-consulta-id="${id}"]`);
                            if (card) card.remove();
                            delete consultaMap[id];
                        })
                        .catch(() => alert('Erro ao excluir consulta'));
                };

                document.getElementById('modal-acao-consulta').addEventListener('hidden.bs.modal', () => {
                    modalConfirmar.show();
                }, { once: true });
                modalAcao.hide();
            };

            modalAcao.show();
        }

        function dispensarAviso(id) {
            axios.post(`/aviso/${id}/dispensar`)
                .then(() => {
                    document.getElementById(`aviso-${id}`)?.remove();
                    const restantes = document.querySelectorAll('#avisos-lista [id^="aviso-"]').length;
                    if (restantes === 0) {
                        const lista = document.getElementById('avisos-lista');
                        if (lista) lista.innerHTML = '<p class="text-muted mb-0">Nenhum aviso pendente.</p>';
                        document.querySelector('[data-bs-target="#collapseAvisos"] .badge')?.remove();
                    } else {
                        const badge = document.querySelector('[data-bs-target="#collapseAvisos"] .badge');
                        if (badge) badge.textContent = restantes;
                    }
                })
                .catch(() => alert('Erro ao dispensar aviso'));
        }

        function confirmarConsulta(id) {
            axios.post(`/agenda/${id}/confirmar`)
                .then(() => {
                    const card = document.querySelector(`.consulta-card[data-consulta-id="${id}"]`);
                    if (card) {
                        card.classList.remove('consulta-pendente');
                        card.querySelector('.badge.bg-warning')?.remove();
                        card.querySelector('.btn-confirmar')?.remove();
                    }
                    if (consultaMap[id]) {
                        consultaMap[id].confirmado = true;
                    }
                })
                .catch(() => alert('Erro ao confirmar consulta'));
        }

        // ── Usuários ─────────────────────────────────────────────────────────
        function excluirUsuario(id, nome) {
            $('#excluir-usuario-id').val(id);
            $('#excluir-usuario-nome').text(nome);
            setTimeout(() => $('#modal-exc-usuario').modal('show'), 250);
        }

        let users = @json($users->items());

        function editarUsuario(id) {
            const user = users.find(u => u.id == id);
            if (!user) return;

            $('#edt-id').val(id);
            $('#edt-name').val(user.name);
            $('#edt-whatsapp').val(user.whatsapp ?? '');

            // Resetar todos os radios antes de marcar o correto
            $('[name="tipo_edt"]').prop('checked', false);
            if (user.adm == 1) {
                $('#tipo_edt_adm').prop('checked', true);
            } else if (user.func == 1) {
                $('#tipo_edt_func').prop('checked', true);
            } else {
                $('#tipo_edt_cli').prop('checked', true);
            }

            $('#modal-edt-usuario').modal('show');
        }

        let searchUsuarioTimeout = null;
        function searchUsuario() {
            clearTimeout(searchUsuarioTimeout);
            searchUsuarioTimeout = setTimeout(() => {
                const termo = document.getElementById('search').value;
                axios.get('/usuarios-search', { params: { q: termo } })
                    .then(res => renderTabelaUsuarios(res.data));
            }, 300);
        }

        function renderTabelaUsuarios(data) {
            users = data;
            const tbody = document.getElementById('tbody-usuarios');
            tbody.innerHTML = data.map(user => `
                <tr>
                    <td class="d-flex">
                        <svg style="cursor:pointer" onclick="excluirUsuario(${user.id},'${user.name.replace(/'/g,"\\'")}') " xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                        <svg style="cursor:pointer" onclick="editarUsuario(${user.id})" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                    </td>
                    <td>${user.name}</td>
                    <td>${user.whatsapp ?? '—'}</td>
                    <td>${user.adm > 0 ? 'Sim' : 'Não'}</td>
                    <td>${user.func > 0 ? 'Sim' : 'Não'}</td>
                </tr>
            `).join('');
        }

        // ── Serviços ──────────────────────────────────────────────────────────
        function excluirServico(id, nome) {
            $('#excluir-servico-id').val(id);
            $('#excluir-servico-nome').text(nome);
            setTimeout(() => $('#modal-exc-servico').modal('show'), 250);
        }

        let servicos = @json($servicos);
        function editarServico(id) {
            const servico = servicos.find(s => s.id == id);
            if (!servico) return;

            $('#id_edt_servico').val(id);
            $('#descricao_edt_servico').val(servico.descricao);
            $('#duracao_h_edt_servico').val(servico.duracao.split(':')[0].padStart(2, '0'));
            $('#duracao_m_edt_servico').val(servico.duracao.split(':')[1].padStart(2, '0'));

            $('[name="status_servico"]').prop('checked', false);
            $(`#status_servico_${servico.status}`).prop('checked', true);

            $('#modal-edt-servico').modal('show');
        }

        // ── Calendário de gestão de disponibilidade ──────────────────────────
        let mgmDataAtual       = new Date();
        let mgmDiasDisponiveis = [];

        async function mgmCarregarMes() {
            const ano = mgmDataAtual.getFullYear();
            const mes = mgmDataAtual.getMonth() + 1;
            try {
                const res = await axios.get(`/api/dias-disponiveis/${ano}/${mes}`);
                mgmDiasDisponiveis = res.data; // array de números de dia
            } catch(e) {
                mgmDiasDisponiveis = [];
            }
            mgmGerarCalendario();
        }

        function mgmGerarCalendario() {
            const ano = mgmDataAtual.getFullYear();
            const mes = mgmDataAtual.getMonth();

            const nomeMes = mgmDataAtual.toLocaleString('pt-BR', { month: 'long' });
            document.getElementById('mgm-cal-mes-ano').textContent =
                `${nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1)} ${ano}`;

            const primeiroDia  = new Date(ano, mes, 1);
            const ultimoDia    = new Date(ano, mes + 1, 0);
            const inicioSemana = primeiroDia.getDay();
            const totalDias    = ultimoDia.getDate();

            const grid = document.getElementById('mgm-cal-dias');
            grid.innerHTML = '';

            // Dias do mês anterior
            const ultimoDiaMesAnt = new Date(ano, mes, 0).getDate();
            for (let i = inicioSemana - 1; i >= 0; i--) {
                const dia = ultimoDiaMesAnt - i;
                const div = document.createElement('div');
                div.classList.add('cal-dia', 'disabled');
                div.textContent = dia;
                const ds = new Date(ano, mes - 1, dia).getDay();
                if (ds === 0 || ds === 6) div.classList.add('cal-fds');
                grid.appendChild(div);
            }

            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            for (let d = 1; d <= totalDias; d++) {
                const div      = document.createElement('div');
                const dataDiv  = new Date(ano, mes, d);
                const diaSem   = dataDiv.getDay();
                div.classList.add('cal-dia');
                div.textContent = d;

                if (diaSem === 0 || diaSem === 6) div.classList.add('cal-fds');

                const passado = dataDiv < hoje;

                if (passado) {
                    div.style.opacity = '0.55';
                } else if (mgmDiasDisponiveis.includes(d)) {
                    div.classList.add('cal-disponivel');
                } else {
                    div.classList.add('cal-bloqueado');
                }

                div.style.cursor = 'pointer';
                div.onclick = () => {
                    const mesF = String(mes + 1).padStart(2, '0');
                    const diaF = String(d).padStart(2, '0');
                    mgmAbrirModal(`${ano}-${mesF}-${diaF}`, passado);
                };

                grid.appendChild(div);
            }

            // Completar grade
            const totalCelulas = inicioSemana + totalDias;
            const resto = totalCelulas % 7;
            if (resto !== 0) {
                for (let d = 1; d <= 7 - resto; d++) {
                    const div = document.createElement('div');
                    div.classList.add('cal-dia', 'disabled');
                    div.textContent = d;
                    const ds = new Date(ano, mes + 1, d).getDay();
                    if (ds === 0 || ds === 6) div.classList.add('cal-fds');
                    grid.appendChild(div);
                }
            }
        }

        document.getElementById('mgm-cal-prev').addEventListener('click', () => {
            mgmDataAtual.setMonth(mgmDataAtual.getMonth() - 1);
            mgmCarregarMes();
        });
        document.getElementById('mgm-cal-next').addEventListener('click', () => {
            mgmDataAtual.setMonth(mgmDataAtual.getMonth() + 1);
            mgmCarregarMes();
        });
        document.getElementById('mgm-cal-today').addEventListener('click', () => {
            mgmDataAtual = new Date();
            mgmCarregarMes();
        });

        // ── Modal de slots ────────────────────────────────────────────────────
        let mgmModoLeitura = false;

        function mgmAbrirModal(data, readonly = false) {
            mgmModoLeitura = readonly;

            const dtObj = new Date(`${data}T00:00:00`);
            const titulo = dtObj.toLocaleDateString('pt-BR', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });

            document.getElementById('mgm-modal-titulo').textContent =
                (titulo.charAt(0).toUpperCase() + titulo.slice(1))
                + (readonly ? ' — somente leitura' : '');

            document.getElementById('mgm-data-atual').value = data;
            document.getElementById('mgm-slots-container').innerHTML =
                '<p class="text-muted">Carregando...</p>';

            const btnSalvar  = document.getElementById('mgm-btn-salvar');
            const btnPresets = document.querySelectorAll('[onclick^="mgmPreset"]');
            btnSalvar.style.display  = readonly ? 'none' : '';
            btnPresets.forEach(b => b.style.display = readonly ? 'none' : '');

            new bootstrap.Modal(document.getElementById('modal-gerenciar-slots')).show();

            axios.get(`/api/disponibilidade/${data}`)
                .then(res => mgmRenderSlots(res.data))
                .catch(() => {
                    document.getElementById('mgm-slots-container').innerHTML =
                        '<p class="text-danger">Erro ao carregar os horários.</p>';
                });
        }

        function mgmRenderSlots(slots) {
            const container = document.getElementById('mgm-slots-container');
            container.innerHTML = '';

            slots.forEach(slot => {
                const id      = `mgm-slot-${slot.hora.replace(':', '-')}`;
                const agend   = slot.agendamento;
                const bloqueadoPorAgend = !!agend;

                const row = document.createElement('div');
                row.className = 'slot-row d-flex align-items-center py-2 border-bottom';
                row.style.minHeight = '44px';

                const check = document.createElement('input');
                check.type      = 'checkbox';
                check.className = 'form-check-input me-3 mgm-slot-check flex-shrink-0';
                check.id        = id;
                check.value     = slot.hora;
                check.checked   = slot.disponivel;
                if (mgmModoLeitura) check.disabled = true;

                const label = document.createElement('label');
                label.htmlFor   = id;
                label.className = 'me-3 flex-shrink-0';
                label.style.cssText = 'min-width:52px; font-family:monospace; font-size:1rem';
                label.textContent = slot.hora;

                row.appendChild(check);
                row.appendChild(label);

                if (agend) {
                    const badge = document.createElement('span');
                    badge.className   = 'badge bg-success text-wrap text-start';
                    badge.style.fontSize = '0.8rem';
                    badge.textContent = `${agend.paciente} — ${agend.servico} (${agend.inicio}–${agend.fim})`;
                    row.appendChild(badge);
                }

                container.appendChild(row);
            });
        }

        function mgmPreset(tipo) {
            const checks = document.querySelectorAll('.mgm-slot-check:not(:disabled)');
            checks.forEach(cb => {
                if (tipo === 'tudo')      cb.checked = true;
                else if (tipo === 'limpar') cb.checked = false;
                else if (tipo === 'comercial') {
                    // 08:00 até 17:30 (última slot que começa antes das 18:00)
                    cb.checked = cb.value >= '08:00' && cb.value <= '17:30';
                }
            });
        }

        async function mgmSalvarSlots() {
            const data  = document.getElementById('mgm-data-atual').value;
            const slots = [...document.querySelectorAll('.mgm-slot-check:checked')]
                .map(cb => cb.value);

            document.getElementById('mgm-btn-salvar').disabled = true;

            try {
                await axios.post(`/api/disponibilidade/${data}`, { slots });
                location.reload();
            } catch(e) {
                alert('Erro ao salvar disponibilidade.');
                document.getElementById('mgm-btn-salvar').disabled = false;
            }
        }

        // Inicializar calendário de gestão ao carregar a página
        document.addEventListener('DOMContentLoaded', () => {
            mgmCarregarMes();

            @if($errors->any() && old('data_inicio'))
                new bootstrap.Modal(document.getElementById('modal-add-agenda')).show();
            @endif
        });

        // ── Busca de consultas ────────────────────────────────────────────────
        let consultaTimeout = null;
        function searchConsulta() {
            clearTimeout(consultaTimeout);
            consultaTimeout = setTimeout(() => {
                const termo = document.getElementById('search-consulta').value;
                axios.get('/agenda-search', { params: { q: termo } })
                    .then(res => renderAccordionConsultas(res.data));
            }, 300);
        }

        function renderAccordionConsultas(data) {
            const accordion = document.getElementById('accordionMeses');
            accordion.innerHTML = '';

            Object.keys(data).forEach(mes => {
                const id = mes.toLowerCase().replace(/\s+/g, '-');
                let html = `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapse-${id}">
                                ${mes.charAt(0).toUpperCase() + mes.slice(1)}
                            </button>
                        </h2>
                        <div id="collapse-${id}" class="accordion-collapse collapse" data-bs-parent="#accordionMeses">
                            <div class="accordion-body">
                `;

                data[mes].forEach(c => {
                    const inicio = c.data_inicio.substring(11, 16);
                    const fim    = c.data_fim.substring(11, 16);
                    const dia    = c.data_inicio.substring(8, 10);

                    consultaMap[c.id] = { ...c, mes, inicio, fim, dia };

                    const nomePaciente  = document.createElement('span');
                    nomePaciente.textContent = c.user.name;
                    const nomeServico   = document.createElement('span');
                    nomeServico.textContent  = c.servico?.descricao ?? '';

                    html += `
                        <div class="consulta-card ${c.confirmado ? '' : 'consulta-pendente'} d-flex justify-content-between align-items-center" data-consulta-id="${c.id}">
                            <div class="d-flex flex-grow-1" onclick="editarConsulta(${c.id})" style="cursor:pointer">
                                <div class="me-3 text-center">
                                    <div class="consulta-data">Dia ${dia}</div>
                                    <div class="consulta-hora">${inicio} <br>às<br> ${fim}</div>
                                </div>
                                <div>
                                    <strong>Paciente:</strong> ${nomePaciente.textContent}<br>
                                    <strong>Serviço:</strong> ${nomeServico.textContent}
                                    ${!c.confirmado ? '<br><span class="badge bg-warning text-dark mt-1">Pendente confirmação</span>' : ''}
                                </div>
                            </div>
                            ${!c.confirmado ? `<button class="btn btn-sm btn-success btn-confirmar ms-2" onclick="event.stopPropagation(); confirmarConsulta(${c.id})">Confirmar</button>` : ''}
                            <button class="btn btn-sm btn-danger ms-2"
                                onclick="event.stopPropagation(); excluirConsultaById(${c.id})">
                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                            </button>
                        </div>
                    `;
                });

                html += `</div></div></div>`;
                accordion.innerHTML += html;
            });
        }
    </script>
    @else
    <script>
        function abrirModalAgendamento(agendamentoId, servicoId, servicoNome) {
            document.getElementById('agendamento_id').value       = agendamentoId ?? '';
            document.getElementById('servico_id').value           = servicoId ?? '';
            document.getElementById('servico_nome_display').value = servicoNome ?? '';
            document.querySelectorAll('.cal-dia').forEach(d => d.classList.remove('cal-selecionado'));
            document.getElementById('dia_selecionado').value  = '';
            document.getElementById('hora_selecionada').value = '';
            document.getElementById('data_inicio').value      = '';
            document.getElementById('data_fim').value         = '';
            document.getElementById('horarios').innerHTML     = '';
            document.getElementById('horarios-erro').textContent = '';
            dataAtual = new Date();
            carregarMes();
            new bootstrap.Modal(document.getElementById('modal-reagendar')).show();
        }

        function reagendarConsulta(id, servicoId, servicoNome) {
            abrirModalAgendamento(id, servicoId, servicoNome);
        }

        function cancelarConsulta(id, servicoId, servicoNome) {
            document.getElementById('modal-acao-titulo').textContent = 'O que deseja fazer?';
            document.getElementById('modal-acao-info').textContent   = 'Você pode reagendar para outro horário ou cancelar definitivamente.';
            document.getElementById('btn-acao-reagendar').textContent = 'Reagendar';
            document.getElementById('btn-acao-excluir').textContent   = 'Cancelar sem reagendar';

            const modalAcao = new bootstrap.Modal(document.getElementById('modal-acao-consulta'));

            document.getElementById('btn-acao-reagendar').onclick = () => {
                modalAcao.hide();
                reagendarConsulta(id, servicoId, servicoNome);
            };

            document.getElementById('btn-acao-excluir').onclick = () => {
                document.getElementById('modal-confirmar-titulo').textContent = 'Cancelar consulta';
                document.getElementById('modal-confirmar-info').textContent   = 'Tem certeza que deseja cancelar esta consulta? O funcionário será notificado.';
                document.getElementById('btn-confirmar-excluir').textContent  = 'Confirmar cancelamento';

                const modalConfirmar = new bootstrap.Modal(document.getElementById('modal-confirmar-exclusao'));

                document.getElementById('btn-confirmar-excluir').onclick = () => {
                    axios.delete(`/agenda/${id}`)
                        .then(() => {
                            modalConfirmar.hide();
                            const card = document.querySelector(`.consulta-card[data-consulta-id="${id}"]`);
                            if (card) card.remove();
                        })
                        .catch(err => {
                            alert(err.response?.data?.error ?? 'Erro ao cancelar consulta');
                        });
                };

                document.getElementById('modal-acao-consulta').addEventListener('hidden.bs.modal', () => {
                    modalConfirmar.show();
                }, { once: true });
                modalAcao.hide();
            };

            modalAcao.show();
        }
    </script>
    @endif
@endsection

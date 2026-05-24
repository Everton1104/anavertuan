@extends("layouts.app")
@section("title", "Dashboard")
@section('style')
    <style>
        .card-header {
            background-color: var(--branco);
        }
        .consulta-card {
            border-radius: 12px;
            border-left: 6px solid #28a745; /* cor lateral */
            background: #f5f4f4;
            padding: 16px;
            margin-bottom: 16px;
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
        {{-- Modal Adicionar Usuarios --}}
        <x-app.modal id="modal-add-usuario" title="Adicionar novo usuário" :btn="[['lbl' => 'Adicionar', 'color' => 'primary', 'onclick' => '$(\'#form-add-usuario\').submit()']]">
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

        {{-- Modal Editar Usuarios --}}
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

        {{-- Modal Excluir Usuarios --}}
        <x-app.modal id="modal-exc-usuario" title="Excluir Usuário" :btn="[['lbl' => 'Excluir', 'color' => 'danger', 'onclick' => '$(\'#form-excluir-usuario\').submit()']]">
            <form id="form-excluir-usuario" action="{{route('delete-usuario')}}" method="post">
                @csrf
                @method('post')
                <p class="fs-3"> Tem certeza que deseja excluir o usuário <span id="excluir-usuario-nome"></span>?</p>
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
                            <tbody>
                                @foreach ($users as $user)
                                    <tr>
                                        @if ($user->adm !=1 || Auth()->user()->id == 1)
                                            @if ($user->func !=1 || Auth()->user()->adm == 1)
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
                <input type="hidden" name="id_edt_servico" id="id_edt_servico" value="{{ old('id_edt_servico') ?? ''}}">
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
                <p class="fs-3"> Tem certeza que deseja excluir o serviço <span id="excluir-servico-nome"></span>?</p>
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
    @endif

    {{-- Agendametos --}}
    <div class="container py-4">

        <h3 class="mb-4">Consultas Agendadas</h3>
        @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
            {{-- Modal Adicionar Agendamentos --}}
            <x-app.modal id="modal-add-agenda" title="Adicionar Nova Consulta" :btn="[['lbl' => 'Adicionar', 'color' => 'primary', 'onclick' => '$(\'#form-add-agenda\').submit()']]">
                <form method="POST" id="form-add-agenda" action="{{ route('agenda.store') }}" novalidate>
                    @csrf
                    @method('post')
                    <x-app.select label="Cliente" name="user_id" required="true" :options="$clientes->pluck('name', 'id')" />
                    <x-app.select label="Selecione o serviço" name="servico_id" required="true" :options="$servicos->where('status', 1)->mapWithKeys(fn($s) => [$s->id => $s->descricao . ' - duração ' . $s->duracao])" />
                    <input type="hidden" name="data_inicio" id="data_inicio" />
                    <input type="hidden" name="data_fim" id="data_fim" />
                    <input type="hidden" id="dia_selecionado" name="dia_selecionado">
                    <input type="hidden" id="hora_selecionada" name="hora_selecionada" value="{{ old('hora_selecionada') }}">
                    <input type="hidden" id="agendamento_id" name="agendamento_id" value="{{old('agendamento_id')}}">
                    <x-app.calendar :servicos="$servicos" />
                </form>
            </x-app.modal>
            <div class="my-3">
                <button class="btn btn-outline-light" onclick="$('#agendamento_id').val('')" data-bs-toggle="modal" data-bs-target="#modal-add-agenda" style="background-color: var(--marrom)">Novo Agendamento</button>
            </div>
            <div class="input-group my-3">
                <span class="input-group-text bg-primary" id="basic-addon1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"/></svg>
                </span>
                <input type="search" class="form-control" id="search-consulta" placeholder="Filtrar por paciente" aria-label="Filtrar por paciente" aria-describedby="basic-addon1" oninput="searchConsulta()">
            </div>
        @endif

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
                                <div class="consulta-card d-flex justify-content-between align-items-center">
                                {{-- Área clicável para editar --}}
                                <div class="d-flex flex-grow-1" @if(auth()->user()->adm == 1 || auth()->user()->func == 1) onclick="editarConsulta({{ $consulta->id }})" style="cursor: pointer" @endif>
                                    <div class="me-3 text-center">
                                        <div class="consulta-data">
                                            Dia {{ \Carbon\Carbon::parse($consulta->data_inicio)->format('d') }}
                                        </div>
                                        <div class="consulta-hora">
                                            {{ \Carbon\Carbon::parse($consulta->data_inicio)->format('H:i') }} <br>ás<br>
                                            {{ \Carbon\Carbon::parse($consulta->data_fim)->format('H:i') }}
                                        </div>
                                    </div>

                                    <div>
                                        <strong>Paciente:</strong> {{ $consulta->user->name }}<br>
                                        <strong>Serviço:</strong> {{ $consulta->servico->descricao ?? '' }}<br>
                                    </div>
                                </div>
                                @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
                                    {{-- Botão de excluir --}}
                                    <button class="btn btn-sm btn-danger ms-3"
                                            onclick="event.stopPropagation(); excluirConsulta({{ $consulta->id }}, '{{ $consulta->servico->descricao ?? '' }}', '{{ $consulta->user->name }}', '{{ \Carbon\Carbon::parse($consulta->data_inicio)->format('H:i') }}', '{{ \Carbon\Carbon::parse($consulta->data_fim)->format('H:i') }}', '{{ \Carbon\Carbon::parse($consulta->data_inicio)->format('d') }}', '{{$mesAtual}}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
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
            function editarConsulta(id) {
                axios.get(`/agenda/${id}/edit`)
                    .then(res => {
                        const c = res.data;

                        // preencher selects
                        document.getElementById('user_id').value = c.user_id;
                        document.getElementById('servico_id').value = c.servico_id;

                        // preencher hidden
                        document.getElementById('agendamento_id').value = c.id;
                        document.getElementById('dia_selecionado').value = c.data_inicio.split(' ')[0];
                        document.getElementById('hora_selecionada').value = c.data_inicio.split(' ')[1];
                        document.getElementById('data_inicio').value = c.data_inicio;
                        document.getElementById('data_fim').value = c.data_fim;

                        // abrir modal
                        var modal = new bootstrap.Modal(document.getElementById('modal-add-agenda'));
                        modal.show();

                        // gerar calendário
                        gerarCalendario();

                        // marcar o dia visualmente
                        setTimeout(() => {
                            const dia = c.data_inicio.split(' ')[0];
                            const diaNumero = parseInt(dia.split('-')[2]);

                            document.querySelectorAll(".cal-dia").forEach(d => {
                                if (parseInt(d.textContent) === diaNumero) {
                                    d.classList.add("cal-selecionado");
                                }
                            });
                        }, 50);

                        // carregar horários
                        getHoras(c.data_inicio.split(' ')[0]);
                    });
            }

            function excluirConsulta(id, desc, nome, init, fim, dia, mes) {
                if (!confirm('Tem certeza que deseja excluir a consulta de '+nome+' com o serviço '+desc+' das '+init+' ás '+fim+' do dia '+dia+' de '+mes+'?')) {
                    return;
                }

                axios.delete(`/agenda/${id}`)
                    .then(res => {
                        // remover o card da tela
                        const card = document.querySelector(`button[onclick*="${id}"]`).closest('.consulta-card');
                        card.remove();

                        // opcional: mensagem de sucesso
                        alert('Consulta excluída com sucesso');
                    })
                    .catch(err => {
                        alert('Erro ao excluir consulta');
                    });
            }

            function excluirUsuario(id, nome) {
                $('#excluir-usuario-id').val(id);
                $('#excluir-usuario-nome').text(nome);
                setTimeout(() => {
                    $('#modal-exc-usuario').modal('show');
                }, 250);
            }

            let users = JSON.parse('{!! json_encode($users->items()) !!}', true);
            function editarUsuario(id) {
                users.forEach(user => {
                    if(user.id == id) {
                        $('#edt-id').val(id);
                        $('#edt-name').val(user.name);
                        $('#edt-whatsapp').val(user.whatsapp ?? '');
                        if(user.adm == 1){
                            $('#tipo_edt_adm').prop('checked', true);
                        }
                        if(user.func == 1){
                            $('#tipo_edt_func').prop('checked', true);
                        }
                        $('#modal-edt-usuario').modal('show');
                    }
                });
            }

            let searchTimeout = null;

            function searchUsuario() {
                clearTimeout(searchTimeout);

                searchTimeout = setTimeout(() => {
                    const termo = document.getElementById('search').value;

                    axios.get('/usuarios-search', {
                        params: { q: termo }
                    })
                    .then(res => {
                        renderTabelaUsuarios(res.data);
                    });
                }, 300); // espera 300ms antes de buscar
            }

            function renderTabelaUsuarios(users) {
                const tbody = document.querySelector("table tbody");
                tbody.innerHTML = "";

                users.forEach(user => {
                    tbody.innerHTML += `
                        <tr>
                            <td class="d-flex">
                                <svg style="cursor: pointer" onclick="excluirUsuario(${user.id}, '${user.name}')" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>

                                <svg style="cursor: pointer" onclick="editarUsuario(${user.id})" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                            </td>

                            <td>${user.name}</td>
                            <td>${user.whatsapp ?? '—'}</td>
                            <td>${user.adm > 0 ? 'Sim' : 'Não'}</td>
                            <td>${user.func > 0 ? 'Sim' : 'Não'}</td>
                        </tr>
                    `;
                });
            }

            // Mapa seguro: evita interpolação de dados do usuário em onclick
            const consultaMap = {};

            let consultaTimeout = null;

            function searchConsulta() {
                clearTimeout(consultaTimeout);

                consultaTimeout = setTimeout(() => {
                    const termo = document.getElementById('search-consulta').value;

                    axios.get('/agenda-search', {
                        params: { q: termo }
                    })
                    .then(res => {
                        renderAccordionConsultas(res.data);
                    });
                }, 300);
            }

            function renderAccordionConsultas(data) {
                const accordion = document.getElementById('accordionMeses');
                accordion.innerHTML = "";

                Object.keys(data).forEach(mes => {
                    const id = mes.toLowerCase().replace(/\s+/g, '-');

                    let html = `
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-${id}">
                                <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapse-${id}">
                                    ${mes.charAt(0).toUpperCase() + mes.slice(1)}
                                </button>
                            </h2>
                            <div id="collapse-${id}" class="accordion-collapse collapse"
                                data-bs-parent="#accordionMeses">
                                <div class="accordion-body">
                    `;

                    data[mes].forEach(c => {
                        const inicio = c.data_inicio.substring(11, 16);
                        const fim    = c.data_fim.substring(11, 16);
                        const dia    = c.data_inicio.substring(8, 10);

                        // Armazenar dados no mapa — nenhum dado de usuário vai para o onclick
                        consultaMap[c.id] = { ...c, mes, inicio, fim, dia };

                        html += `
                            <div class="consulta-card d-flex justify-content-between align-items-center">
                                <div class="d-flex flex-grow-1" onclick="editarConsulta(${c.id})" style="cursor: pointer">
                                    <div class="me-3 text-center">
                                        <div class="consulta-data">Dia ${dia}</div>
                                        <div class="consulta-hora">${inicio} <br>ás<br> ${fim}</div>
                                    </div>
                                    <div>
                                        <strong>Paciente:</strong> ${document.createTextNode(c.user.name).textContent}<br>
                                        <strong>Serviço:</strong> ${document.createTextNode(c.servico?.descricao ?? '').textContent}
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-danger ms-3"
                                    onclick="event.stopPropagation(); excluirConsultaById(${c.id})">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff">
                                        <path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/>
                                    </svg>
                                </button>
                            </div>
                        `;
                    });

                    html += `</div></div></div>`;
                    accordion.innerHTML += html;
                });
            }

            function excluirConsultaById(id) {
                const c = consultaMap[id];
                if (!c) return;
                excluirConsulta(id, c.servico?.descricao ?? '', c.user.name, c.inicio, c.fim, c.dia, c.mes);
            }


            function excluirServico(id, nome) {
                $('#excluir-servico-id').val(id);
                $('#excluir-servico-nome').text(nome);
                setTimeout(() => {
                    $('#modal-exc-servico').modal('show');
                }, 250);
            }

            let servicos = @json($servicos);
            function editarServico(id) {
                servicos.forEach(servico => {
                    if(servico.id == id) {
                        $('#id_edt_servico').val(id);
                        $('#descricao_edt_servico').val(servico.descricao);
                        duracao_h = servico.duracao.split(':')[0];
                        duracao_m = servico.duracao.split(':')[1];
                        $('#duracao_h_edt_servico').val(duracao_h.padStart(2, '0'));
                        $('#duracao_m_edt_servico').val(duracao_m.padStart(2, '0'));
                        if(servico.status == 0){
                            $('#status_servico_0').prop('checked', true);
                        }
                        if(servico.status == 1){
                            $('#status_servico_1').prop('checked', true);
                        }
                        $('#modal-edt-servico').modal('show');
                    }
                });
            }
        </script>
    @endif
@endsection

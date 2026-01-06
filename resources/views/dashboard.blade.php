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
<div class="container">
    <div class="my-3">
        <p class="fs-4">Olá, {{ ucfirst(auth()->user()->name) }}</p>
    </div>

    {{-- Seção de controle para administradores --}}
    @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
        @include('dashboard.modal-add-usuario')
        @include('dashboard.modal-edt-usuario')
        @include('dashboard.modal-add-consulta', compact('clientes', 'servicos'))
        @include('dashboard.modal-add-servico')
        <div class="card shadow my-3">
            <div class="card-header">Controle de Contas de Usuário</div>
            <div class="card-body p-3 row">
                <div>
                    <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-usuario" style="background-color: var(--marrom)">Novo Usuário</button>
                </div>
                <div class="input-group my-3">
                    <span class="input-group-text bg-primary" id="basic-addon1">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/></svg>
                    </span>
                    <input type="search" class="form-control" id="search" placeholder="Pesquisar por nome" aria-label="Pesquisar por nome" aria-describedby="basic-addon1" oninput="searchUsuario()">
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th scope="col">&nbsp;</th>
                                <th scope="col">Nome</th>
                                <th scope="col">Email</th>
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
                                    <td>{{ $user->email }}</td>
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
        <form id="form-excluir-usuario" action="{{route('delete-usuario')}}" method="post" class="d-none">
            @csrf
            @method('post')
            <input type="text" name="id" id="excluir-usuario-id" value="">
        </form>

        <div class="card shadow my-3">
            <div class="card-header">Serviços</div>
            <div class="card-body p-3 row">
                <div>
                    <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-servico" style="background-color: var(--marrom)">Novo Serviço</button>
                </div>
            </div>
        </div>
    @endif


    <div class="container py-4">

        <h3 class="mb-4">Consultas Agendadas</h3>
        @if(auth()->user()->adm == 1 || auth()->user()->func == 1)
            <div class="my-3">
                <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-consulta" style="background-color: var(--marrom)">Novo Agendamento</button>
            </div>
            <div class="input-group my-3">
                <span class="input-group-text bg-primary" id="basic-addon1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff"><path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"/></svg>
                </span>
                <input type="search" class="form-control" id="search-consulta" placeholder="Filtrar por paciente" aria-label="Filtrar por paciente" aria-describedby="basic-addon1" oninput="searchConsulta()">
            </div>
        @endif

        <div class="accordion shadow" id="accordionMeses">

            @php
                use App\Models\AgendamentoModel;
                if(auth()->user()->adm == 1 || auth()->user()->func == 1){
                    $consultas = AgendamentoModel::with(['user', 'servico'])
                        ->orderBy('data_inicio')
                        ->get()
                        ->groupBy(function ($item) {
                            return \Carbon\Carbon::parse($item->data_inicio)
                                ->locale('pt_BR')
                                ->translatedFormat('F Y'); // Ex: "Janeiro 2026"
                        });
                }else{
                    $consultas = AgendamentoModel::with(['user', 'servico'])
                        ->where('user_id','=',Auth()->user()->id)
                        ->orderBy('data_inicio')
                        ->get()
                        ->groupBy(function ($item) {
                            return \Carbon\Carbon::parse($item->data_inicio)
                                ->locale('pt_BR')
                                ->translatedFormat('F Y'); // Ex: "Janeiro 2026"
                        });
                }
                $mesAtual = now()->locale('pt_BR')->translatedFormat('F Y');
            @endphp

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
                                <div class="consulta-card d-flex">
                                    <div class="me-3 text-center">
                                        <div class="consulta-data">
                                            Dia {{ \Carbon\Carbon::parse($consulta->data_inicio)->format('d') }}
                                        </div>
                                        <div class="consulta-hora">
                                            {{ \Carbon\Carbon::parse($consulta->data_inicio)->format('H:i') }} <br>ás<br> {{ \Carbon\Carbon::parse($consulta->data_fim)->format('H:i') }}
                                        </div>
                                    </div>

                                    <div>
                                        <strong>Paciente:</strong> {{ $consulta->user->name }}<br>
                                        <strong>Serviço:</strong> {{ $consulta->servico->descricao }}<br>
                                    </div>
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

    @include('dashboard.calculadora-tmb')
    @include('dashboard.calculadora-agua')


</div>
@endsection

@section('scriptEnd')
    <script>
        $(document).ready(function () {
            setTimeout(function () {
                // Se houver erros, abre o modal add-usuario
                @if (isset($errors->toArray()['nome']) || isset($errors->toArray()['email']) || isset($errors->toArray()['senha']))
                    $('#modal-add-usuario').modal('show');
                @endif
                @if (false)
                    $('#modal-add-consulta').modal('show');
                @endif
            }, 250);
        });

        function excluirUsuario(id, nome) {
            if(confirm("Deseja realmente excluir o usuário "+nome+"?"))
            $('#excluir-usuario-id').val(id);
            $('#form-excluir-usuario').submit();
        }

        let users = JSON.parse('{!! json_encode($users->items()) !!}', true);
        function editarUsuario(id) {
            users.forEach(user => {
                if(user.id == id) {
                    $('#edt-id').val(id);
                    $('#edt-name').val(user.name);
                    $('#edt-email').val(user.email);
                    if(user.adm == 1){
                        $('#edt-adm').prop('checked', true);
                    }
                    if(user.func == 1){
                        $('#edt-func').prop('checked', true);
                    }
                    $('#modal-edt-usuario').modal('show');
                }
            });
        }
    </script>
@endsection

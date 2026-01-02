@extends("layouts.app")
@section("title", "Dashboard")
@section('style')
    <style>
        .fc-button {
            background-color: var(--marrom) !important;
            border-color: var(--cinza) !important;
        }
        .card-header {
            background-color: var(--branco);
        }
        #calendar {
            max-width: 100%;
            margin: 0 auto;
            max-width: 100%;
            overflow-x: auto;
        }
        .fc-daygrid-day {
            cursor: pointer;
        }
        .fc-timegrid-slot {
            cursor: pointer;
        }
        .fc-event-main-frame {
            cursor: pointer;
        }
        .fc-daygrid-day:hover {
            background-color: #f0f8ff;
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
        <div class="card shadow my-3">
            <div class="card-header">Controle de Contas de Usuário</div>
            <div class="card-body p-3 row">
                <div>
                    <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modal-add-usuario" style="background-color: var(--marrom)">Novo Usuário</button>
                </div>
                @include('dashboard.modal-add-usuario')
                @include('dashboard.modal-edt-usuario')
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
    @endif

    {{-- Calendário --}}



</div>
@endsection

@section('scriptEnd')
    <script>
        $(document).ready(function () {
            setTimeout(function () {
                @if ($errors->any())
                    $('#modal-add-usuario').modal('show');
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

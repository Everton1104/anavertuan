<div class="modal fade modal-lg" data-bs-backdrop="static" id="modal-edt-usuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header alert alert-primary">
                <h5 class="modal-title fs-3">Editar usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="form-edt-usuario" action="{{ route('editar-usuario') }}">
                    @csrf
                    @method('post')

                    <input type="text" class="d-none" id="edt-id" name="id">

                    <!-- Name -->
                    <div>
                        <x-input-label for="edt-name" :value="__('Nome')" />
                        <x-text-input id="edt-name" class="block mt-1 w-full" type="text" name="nome" :value="old('nome')" required autofocus autocomplete="edt-name" />
                        <x-input-error :messages="$errors->get('nome')" class="mt-2" />
                    </div>

                    <!-- Email Address -->
                    <div class="mt-4">
                        <x-input-label for="edt-email" :value="__('Email')" />
                        <x-text-input id="edt-email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="edt-username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div class="mt-4">
                        <x-input-label for="edt-password" :value="__('Mudar Senha')" />

                        <x-text-input id="edt-password" class="block mt-1 w-full"
                                        type="password"
                                        name="senha"
                                        required autocomplete="new-password" />

                        <x-input-error :messages="$errors->get('senha')" class="mt-2" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="mt-4">
                        <x-input-label for="edt-password_confirmation" :value="__('Confirmar Senha')" />

                        <x-text-input id="edt-password_confirmation" class="block mt-1 w-full"
                                        type="password"
                                        name="senha_confirmation" required autocomplete="new-password" />

                        <x-input-error :messages="$errors->get('senha_confirmation')" class="mt-2" />
                    </div>

                    @if(auth()->user()->adm == 1)
                        <div class="form-check my-3">
                            <label class="form-check-label">
                                <input type="radio" class="form-check-input" name="tipo" value="adm" id="edt-adm">
                                Administrador
                            </label>
                        </div>
                        <div class="form-check my-3">
                            <label class="form-check-label">
                                <input type="radio" class="form-check-input" name="tipo" value="func" id="edt-func">
                                Funcionário
                            </label>
                        </div>
                        <div class="form-check my-3">
                            <label class="form-check-label">
                                <input type="radio" class="form-check-input" name="tipo">
                                Cliente
                            </label>
                        </div>
                    @endif
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="$('#form-edt-usuario').submit()">Atualizar</button>
            </div>
        </div>
    </div>
</div>

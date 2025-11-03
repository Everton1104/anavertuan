<div class="modal fade modal-lg" data-bs-backdrop="static" id="modal-add-usuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header alert alert-primary">
                <h5 class="modal-title fs-3">Adicionar novo usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="form-add-usuario" action="{{ route('add-usuario') }}">
                    @csrf
                    @method('post')

                    <!-- Name -->
                    <div>
                        <x-input-label for="name" :value="__('Nome')" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="nome" :value="old('nome')" required autofocus autocomplete="name" />
                        <x-input-error :messages="$errors->get('nome')" class="mt-2" />
                    </div>

                    <!-- Email Address -->
                    <div class="mt-4">
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div class="mt-4">
                        <x-input-label for="password" :value="__('Senha')" />

                        <x-text-input id="password" class="block mt-1 w-full"
                                        type="password"
                                        name="senha"
                                        required autocomplete="new-password" />

                        <x-input-error :messages="$errors->get('senha')" class="mt-2" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="mt-4">
                        <x-input-label for="password_confirmation" :value="__('Confirmar Senha')" />

                        <x-text-input id="password_confirmation" class="block mt-1 w-full"
                                        type="password"
                                        name="senha_confirmation" required autocomplete="new-password" />

                        <x-input-error :messages="$errors->get('senha_confirmation')" class="mt-2" />
                    </div>

                    @if(auth()->user()->adm == 1)
                        <div class="form-check my-3">
                            <label class="form-check-label">
                                <input type="radio" class="form-check-input" name="tipo" value="adm" id="adm">
                                Administrador
                            </label>
                        </div>
                        <div class="form-check my-3">
                            <label class="form-check-label">
                                <input type="radio" class="form-check-input" name="tipo" value="func" id="func">
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
                <button type="button" class="btn btn-primary" onclick="$('#form-add-usuario').submit()">Adicionar</button>
            </div>
        </div>
    </div>
</div>

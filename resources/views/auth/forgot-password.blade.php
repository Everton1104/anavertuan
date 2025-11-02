@extends('layouts.app')

@section('main')
    @if(session('status'))
        <div class="text-success text-center my-5">
                EMAIL DE REDEFINIÇÃO DE SENHA ENVIADO COM SUCESSO!
        </div>
        <script>
            setTimeout(() => {
                window.location.href = '/';
            }, 5000);
        </script>
    @else
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div class="mb-4 text-sm text-gray-600">
                {{ __('Por favor, insira seu email para que possamos enviar um link de redefinição de senha.') }}
            </div>
            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <!-- Email Address -->
                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-primary-button>
                        {{ __('ENVIAR LINK DE RECUPERAÇÃO') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    @endif
@endsection


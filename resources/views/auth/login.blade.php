<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" id="login-form">
        @csrf

        <!-- WhatsApp -->
        <div>
            <x-input-label for="whatsapp" :value="'WhatsApp'" />
            <x-text-input id="whatsapp" class="block mt-1 w-full" type="tel" name="whatsapp" :value="old('whatsapp')" required autofocus autocomplete="username" placeholder="Ex: 11987654321" />
            <x-input-error :messages="$errors->get('whatsapp')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="'Senha'" />
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Manter conectado</span>
            </label>
        </div>

        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response-login">

        @error('g-recaptcha-response')
            <span class="text-red-600 text-sm mt-2 block">{{ $message }}</span>
        @enderror

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    Esqueci minha senha
                </a>
            @endif

            <button type="button"
                class="ms-3 g-recaptcha inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                data-sitekey="{{ env('RECAPTCHA_SITE_KEY') }}"
                data-callback="onLoginRecaptcha"
                data-action="login">
                ENTRAR
            </button>
        </div>
    </form>

    <script src="https://www.google.com/recaptcha/enterprise.js?render={{ env('RECAPTCHA_SITE_KEY') }}" async defer></script>
    <script>
        function onLoginRecaptcha(token) {
            document.getElementById('g-recaptcha-response-login').value = token;
            document.getElementById('login-form').submit();
        }
    </script>
</x-guest-layout>

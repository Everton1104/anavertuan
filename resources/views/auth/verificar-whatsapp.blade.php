@extends("layouts.app")
@section("title", "Verificar WhatsApp")
@section("main")
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Verificar WhatsApp</div>
                <div class="card-body">
                    @if(session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <p class="text-muted mb-4">
                        Enviamos um código de 6 dígitos para <strong>{{ auth()->user()->whatsapp }}</strong>.<br>
                        Digite-o abaixo para ativar sua conta.
                    </p>

                    <form method="POST" action="{{ route('verificar.whatsapp.confirmar') }}">
                        @csrf
                        <x-app.input
                            label="Código de verificação"
                            type="text"
                            name="codigo"
                            id="codigo"
                            placeholder="000000"
                            maxlength="6"
                            required="true"
                        />
                        @error('codigo')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror

                        <div class="d-grid mt-3 mb-2">
                            <button type="submit" class="btn btn-primary">Confirmar</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('verificar.whatsapp.reenviar') }}">
                        @csrf
                        <div class="d-grid">
                            <button id="btn-reenviar" type="submit" class="btn btn-outline-secondary btn-sm">
                                Reenviar código
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scriptEnd')
<script>
    (function () {
        const btn = document.getElementById('btn-reenviar');
        let restam = {{ (int) ($aguardar ?? 0) }};
        if (restam <= 0) return;
        btn.disabled = true;
        function atualizar() {
            if (restam <= 0) { btn.disabled = false; btn.textContent = 'Reenviar código'; return; }
            btn.textContent = 'Reenviar código (' + restam + 's)';
            restam--;
            setTimeout(atualizar, 1000);
        }
        atualizar();
    })();
</script>
@endsection
@endsection

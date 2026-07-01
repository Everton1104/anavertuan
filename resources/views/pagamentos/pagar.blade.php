@extends('layouts.app')

@section('title', 'Pagar ordem')

@section('main')
<div class="container mb-5">
    <div class="my-3">
        <p class="fs-4">Pagamento</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            {{-- Resumo da ordem --}}
            <div class="card shadow mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-2">{{ $ordem->descricao }}</h5>
                    <p class="fs-5 mb-0">Valor: <strong>R$ {{ number_format($ordem->valor, 2, ',', '.') }}</strong></p>
                </div>
            </div>

            {{-- Container do Card Payment Brick (Mercado Pago) --}}
            <div id="cardPaymentBrick_container"></div>

            {{-- Resultado do pagamento --}}
            <div id="resultado-pagamento" class="d-none">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <div id="resultado-icon" class="display-6 mb-2"></div>
                        <h5 id="resultado-titulo" class="mb-2"></h5>
                        <p id="resultado-msg" class="text-muted mb-3"></p>
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">Voltar ao painel</a>
                    </div>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="{{ route('dashboard') }}" class="btn btn-link">&larr; Voltar</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scriptEnd')
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
(function () {
    const PUBLIC_KEY   = @json($publicKey);
    const COBRAR_URL   = @json(route('pagamentos.cobrar', $ordem));
    const CSRF         = @json(csrf_token());
    const VALOR        = @json((float) $ordem->valor);
    const MAX_PARCELAS = @json((int) $ordem->max_parcelas);
    const container    = document.getElementById('cardPaymentBrick_container');

    function mostrarResultado(status, msg) {
        container.classList.add('d-none');
        const box = document.getElementById('resultado-pagamento');
        const titulo = document.getElementById('resultado-titulo');
        const texto = document.getElementById('resultado-msg');
        const icon = document.getElementById('resultado-icon');
        box.classList.remove('d-none');

        if (status === 'approved') {
            icon.textContent = '✅';
            titulo.textContent = 'Pagamento aprovado!';
            titulo.className = 'text-success mb-2';
        } else if (status === 'pending' || status === 'in_process') {
            icon.textContent = '⏳';
            titulo.textContent = 'Pagamento em análise';
            titulo.className = 'text-warning mb-2';
        } else {
            icon.textContent = '❌';
            titulo.textContent = 'Pagamento não aprovado';
            titulo.className = 'text-danger mb-2';
        }
        texto.textContent = msg || '';
    }

    // Mostra o erro DENTRO do container (visível na tela) + console — facilita
    // diagnosticar falhas de renderização do Brick remotamente.
    function falhar(msg, detalhe) {
        console.error('[MP Brick]', msg, detalhe || '');
        const d = detalhe ? (' — ' + (typeof detalhe === 'string'
            ? detalhe
            : (detalhe.message || JSON.stringify(detalhe)))) : '';
        container.innerHTML =
            '<div class="alert alert-danger mb-0"><strong>Não foi possível carregar o formulário de pagamento.</strong>' +
            '<div class="small mt-1">' + msg + d + '</div>' +
            '<button class="btn btn-sm btn-outline-secondary mt-2" onclick="location.reload()">Tentar novamente</button></div>';
    }

    function avisarErro(msg) {
        const antigo = document.getElementById('brick-warn');
        if (antigo) antigo.remove();
        container.insertAdjacentHTML('beforebegin',
            '<div class="alert alert-warning alert-dismissible fade show" id="brick-warn" role="alert">' + msg +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button></div>');
    }

    if (!PUBLIC_KEY) { falhar('Configuração de pagamento ausente (PUBLIC_KEY).'); return; }
    if (typeof MercadoPago === 'undefined') { falhar('A SDK do Mercado Pago não carregou (verifique a conexão ou bloqueadores).'); return; }

    let mp;
    try {
        mp = new MercadoPago(PUBLIC_KEY, { locale: 'pt-BR' });
    } catch (e) { falhar('Erro ao iniciar o Mercado Pago.', e); return; }

    let pronto = false;
    const bricksBuilder = mp.bricks();
    bricksBuilder.create('cardPayment', 'cardPaymentBrick_container', {
            initialization: { amount: VALOR },
            customization: {
                paymentMethods: { maxInstallments: MAX_PARCELAS, minInstallments: 1 },
                visual: { style: { theme: 'default' } },
            },
            callbacks: {
                onReady: function () { pronto = true; console.log('[MP Brick] pronto'); },
                onSubmit: function (cardFormData) {
                    // Campos chegam em snake_case neste brick; aceitamos ambos por segurança.
                    const paymentMethodId = cardFormData.payment_method_id || cardFormData.paymentMethodId;
                    const issuerId = cardFormData.issuer_id || cardFormData.issuerId;
                    const payer = cardFormData.payer || {};
                    const identification = payer.identification || {};

                    return fetch(COBRAR_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            token: cardFormData.token,
                            payment_method_id: paymentMethodId,
                            issuer_id: issuerId,
                            installments: cardFormData.installments,
                            payer_email: payer.email || undefined,
                            payer_doc_type: identification.type || undefined,
                            payer_doc_number: identification.number || undefined,
                        }),
                    })
                    .then(function (resp) { return resp.json(); })
                    .then(function (data) {
                        if (data && data.erro) { avisarErro(data.msg); return; }
                        mostrarResultado(data && data.status ? data.status : 'rejected', data && data.message);
                    })
                    .catch(function () {
                        avisarErro('Falha de comunicação com o servidor. Tente novamente.');
                    });
                },
                onError: function (e) { falhar('Erro no Brick durante o carregamento.', e); },
            },
        })
    .catch(function (e) {
        falhar('Erro ao criar o Brick.', e);
    });

    // Diagnóstico: se após 10s o Brick não ficou pronto nem preencheu o container,
    // exibe um erro visível (captura falhas silenciosas de renderização).
    setTimeout(function () {
        if (pronto) return;
        if (!container.innerHTML.trim()) {
            falhar('O formulário não carregou em 10s.');
        }
    }, 10000);
})();
</script>
@endsection

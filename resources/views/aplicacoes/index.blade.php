@extends("layouts.app")
@section("title", "Aplicações Mounjaro")
@section('style')
<style>
    .resumo-card { border-left: 5px solid var(--marrom); }
    .dose-input { max-width: 120px; }
    .inp-ok   { border-color: #198754 !important; }
    .inp-erro { border-color: #dc3545 !important; }
    .balanco-box { background: #f8f9fa; border-radius: 8px; }
</style>
@endsection
@section("main")
<div class="container mb-5">
    <div class="my-3 d-flex justify-content-between align-items-center">
        <p class="fs-4 mb-0">Aplicações Mounjaro</p>
    </div>

    @if(session('msg'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('msg') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- ── RESUMO GERAL ────────────────────────────────────────────────────── --}}
    <div class="card shadow my-3 resumo-card">
        <div class="card-header fw-bold">Resumo Geral</div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="fw-semibold text-muted small">Total recebido</div>
                    <div class="fs-5 text-success" id="resumo-recebido">R$ {{ number_format($totalRecebido, 2, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-semibold text-muted small">Custo medicamento</div>
                    <div class="fs-5 text-danger" id="resumo-custo">R$ {{ number_format($custoTotal, 2, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-semibold text-muted small">Lucro líquido</div>
                    <div class="fs-5 {{ $lucroTotal >= 0 ? 'text-success' : 'text-danger' }}" id="resumo-lucro">R$ {{ number_format($lucroTotal, 2, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-semibold text-muted small">Custo médio / UI</div>
                    <div class="fs-5">R$ {{ number_format($custoPorUi, 4, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-semibold text-muted small">Pago fornecedores</div>
                    <div class="fs-5">R$ {{ number_format($totalFornecedor, 2, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-semibold text-muted small">UI comprado</div>
                    <div class="fs-5">{{ number_format($uiComprado, 0, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-semibold text-muted small">UI vendido</div>
                    <div class="fs-5" id="resumo-ui-vendido">{{ number_format($uiVendido, 0, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-semibold text-muted small">UI em estoque</div>
                    <div class="fs-5 {{ $uiRestante < 0 ? 'text-danger' : '' }}" id="resumo-ui-restante">{{ number_format($uiRestante, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── MODAL FORNECEDOR ────────────────────────────────────────────────── --}}
    <div class="modal fade" id="modal-fornecedor" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo-fornecedor">Novo Fornecedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('aplicacoes.fornecedor.store') }}" id="form-fornecedor">
                    @csrf
                    <input type="hidden" name="fornecedor_id" id="fornecedor_id_hidden">
                    <div class="modal-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fornecedor</label>
                            <input type="text" name="fornecedor" id="f_fornecedor" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data da Compra</label>
                            <input type="date" name="data_compra" id="f_data_compra" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Produto</label>
                            <input type="text" name="produto" id="f_produto" class="form-control" required value="Mounjaro">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ampolas</label>
                            <input type="number" name="ampolas_compradas" id="f_ampolas" class="form-control" min="1" required oninput="calcFornecedor()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">UI por Ampola</label>
                            <input type="number" name="ui_por_ampola" id="f_ui" class="form-control" min="1" required oninput="calcFornecedor()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor Total (R$)</label>
                            <input type="number" name="valor_total" id="f_valor" class="form-control" min="0" step="0.01" required oninput="calcFornecedor()">
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0 small" id="preview-fornecedor" style="display:none">
                                Total UI: <strong id="prev-total-ui">—</strong> &nbsp;|&nbsp;
                                Custo/UI: <strong id="prev-custo-ui">—</strong> &nbsp;|&nbsp;
                                Valor/Ampola: <strong id="prev-valor-amp">—</strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── MODAL CONFIRMAR EXCLUSÃO ────────────────────────────────────────── --}}
    <div class="modal fade" id="modal-confirmar-excl" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><p id="confirmar-excl-msg"></p></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btn-confirmar-excl">Excluir</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CARD FORNECEDORES ───────────────────────────────────────────────── --}}
    <div class="card shadow my-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Fornecedores (compras)</span>
            <button class="btn btn-sm btn-outline-light" onclick="abrirNovoFornecedor()" style="background:var(--marrom)">+ Novo</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Fornecedor</th>
                            <th>Data</th>
                            <th>Produto</th>
                            <th class="text-end">Ampolas</th>
                            <th class="text-end">UI/Ampola</th>
                            <th class="text-end">Total UI</th>
                            <th class="text-end">Valor Total</th>
                            <th class="text-end">Custo/UI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fornecedores as $f)
                        <tr id="forn-row-{{ $f->id }}">
                            <td class="d-flex gap-1">
                                <svg style="cursor:pointer" onclick="editarFornecedor({{ $f->id }})" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#0d6efd"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                                <svg style="cursor:pointer" onclick="excluirFornecedor({{ $f->id }})" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                            </td>
                            <td>{{ $f->fornecedor }}</td>
                            <td>{{ $f->data_compra->format('d/m/Y') }}</td>
                            <td>{{ $f->produto }}</td>
                            <td class="text-end">{{ $f->ampolas_compradas }}</td>
                            <td class="text-end">{{ $f->ui_por_ampola }}</td>
                            <td class="text-end">{{ number_format($f->total_ui, 0, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($f->valor_total, 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($f->custo_por_ui, 4, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-muted text-center py-3">Nenhum fornecedor cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── BALANÇO MENSAL ──────────────────────────────────────────────────── --}}
    <div class="card shadow my-3">
        <div class="card-header">Balanço Mensal</div>
        <div class="card-body p-3">
            <p class="text-muted small">
                Consultas de serviços marcados como <strong>Mounjaro</strong>, agrupadas por mês. Informe o valor pago
                e o UI aplicado em cada uma; o balanço (comprado × vendido × lucro) é calculado por mês.
            </p>

            @forelse($balancoMeses as $mes)
            @php $aberto = $mes->ym === $mesAtual; @endphp
            <div class="accordion mb-2" id="acc-{{ $mes->ym }}">
                <div class="accordion-item mes-card" data-ym="{{ $mes->ym }}">
                    <h2 class="accordion-header">
                        <button class="accordion-button {{ $aberto ? '' : 'collapsed' }} fw-semibold" type="button"
                                data-bs-toggle="collapse" data-bs-target="#mes-{{ $mes->ym }}">
                            {{ $mes->label }}
                        </button>
                    </h2>
                    <div id="mes-{{ $mes->ym }}" class="accordion-collapse collapse {{ $aberto ? 'show' : '' }}">
                        <div class="accordion-body">
                            {{-- Balanço do mês --}}
                            <div class="balanco-box p-3 mb-3">
                                <div class="row g-3 text-center">
                                    <div class="col-4">
                                        <div class="fw-semibold text-muted small">Comprado no mês</div>
                                        <div class="fs-6 text-danger">R$ {{ number_format($mes->comprado_valor, 2, ',', '.') }}</div>
                                        <div class="small text-muted">{{ number_format($mes->comprado_ui, 0, ',', '.') }} UI</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-semibold text-muted small">Vendido no mês</div>
                                        <div class="fs-6 text-success m-vendido">R$ {{ number_format($mes->vendido_valor, 2, ',', '.') }}</div>
                                        <div class="small text-muted"><span class="m-ui-vendido">{{ number_format($mes->ui_vendido, 0, ',', '.') }}</span> UI</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-semibold text-muted small">Lucro do mês</div>
                                        <div class="fs-6 fw-bold m-lucro {{ $mes->lucro >= 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($mes->lucro, 2, ',', '.') }}</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Consultas do mês --}}
                            @if($mes->consultas->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Serviço</th>
                                            <th>Data</th>
                                            <th class="text-end">Unidades</th>
                                            <th class="text-end" style="width:150px">Valor pago (R$)</th>
                                            <th class="text-end" style="width:150px">UI aplicado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($mes->consultas as $c)
                                        <tr>
                                            <td>{{ $c->cliente }}</td>
                                            <td>{{ $c->servico }}</td>
                                            <td>{{ $c->data->format('d/m/Y') }}</td>
                                            <td class="text-end">{{ $c->unidades }}</td>
                                            <td class="text-end">
                                                <input type="text" inputmode="decimal"
                                                       class="form-control form-control-sm text-end dose-input ms-auto inp-valor"
                                                       data-ag="{{ $c->agendamento_id }}"
                                                       value="{{ $c->valor_pago !== null ? number_format($c->valor_pago, 2, ',', '.') : '' }}"
                                                       onchange="salvarCampo(this, 'valor_pago')">
                                            </td>
                                            <td class="text-end">
                                                <input type="number" min="1"
                                                       class="form-control form-control-sm text-end dose-input ms-auto inp-ui"
                                                       data-ag="{{ $c->agendamento_id }}"
                                                       value="{{ $c->ui }}"
                                                       onchange="salvarCampo(this, 'ui')">
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="text-muted small mb-0">Nenhuma consulta de Mounjaro neste mês.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <p class="text-muted mb-0">Nenhuma movimentação. Marque um serviço como Mounjaro e agende uma consulta, ou cadastre um fornecedor.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@php
$fornecedoresJs = $fornecedores->map(fn($f) => [
    'id'          => $f->id,
    'fornecedor'  => $f->fornecedor,
    'data_compra' => $f->data_compra->format('d/m/Y'),
    'produto'     => $f->produto,
    'ampolas'     => $f->ampolas_compradas,
    'ui_por_amp'  => $f->ui_por_ampola,
    'valor_total' => $f->valor_total,
]);
@endphp
@section('scriptEnd')
<script>
    const fornecedoresData = @json($fornecedoresJs);
    const CUSTO_POR_UI     = {{ $custoPorUi }};
    const UI_COMPRADO      = {{ $uiComprado }};

    const brl = n => 'R$ ' + (n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const intBr = n => (n || 0).toLocaleString('pt-BR');

    // Converte "1.150,00" (formato BR) em número; aceita também "150" e "150.5".
    function parseMoeda(v) {
        if (v == null) return 0;
        v = String(v).trim().replace(/\s|R\$/g, '');
        if (v === '') return 0;
        v = v.replace(/\./g, '').replace(',', '.');
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }
    const fmtMoeda = n => (n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // ── Fornecedores ──────────────────────────────────────────────────────────
    function abrirNovoFornecedor() {
        document.getElementById('fornecedor_id_hidden').value = '';
        document.getElementById('titulo-fornecedor').textContent = 'Novo Fornecedor';
        document.getElementById('form-fornecedor').reset();
        document.getElementById('preview-fornecedor').style.display = 'none';
        new bootstrap.Modal(document.getElementById('modal-fornecedor')).show();
    }

    function editarFornecedor(id) {
        const f = fornecedoresData.find(x => x.id === id);
        if (!f) return;
        document.getElementById('fornecedor_id_hidden').value = id;
        document.getElementById('titulo-fornecedor').textContent = 'Editar Fornecedor';
        document.getElementById('f_fornecedor').value   = f.fornecedor;
        document.getElementById('f_data_compra').value  = f.data_compra.split('/').reverse().join('-');
        document.getElementById('f_produto').value      = f.produto;
        document.getElementById('f_ampolas').value      = f.ampolas;
        document.getElementById('f_ui').value           = f.ui_por_amp;
        document.getElementById('f_valor').value        = f.valor_total;
        calcFornecedor();
        new bootstrap.Modal(document.getElementById('modal-fornecedor')).show();
    }

    function excluirFornecedor(id) {
        document.getElementById('confirmar-excl-msg').textContent = 'Tem certeza que deseja excluir este fornecedor?';
        document.getElementById('btn-confirmar-excl').onclick = () => {
            axios.delete(`/aplicacoes/fornecedores/${id}`)
                .then(() => location.reload())
                .catch(err => alert(err.response?.data?.msg || 'Erro ao excluir.'));
        };
        new bootstrap.Modal(document.getElementById('modal-confirmar-excl')).show();
    }

    function calcFornecedor() {
        const ampolas = parseFloat(document.getElementById('f_ampolas').value) || 0;
        const ui      = parseFloat(document.getElementById('f_ui').value) || 0;
        const valor   = parseFloat(document.getElementById('f_valor').value) || 0;
        const totalUi = ampolas * ui;
        const prev    = document.getElementById('preview-fornecedor');
        if (ampolas && ui && valor) {
            document.getElementById('prev-total-ui').textContent  = totalUi.toLocaleString('pt-BR');
            document.getElementById('prev-custo-ui').textContent  = 'R$ ' + (valor / totalUi).toFixed(4).replace('.', ',');
            document.getElementById('prev-valor-amp').textContent = 'R$ ' + (valor / ampolas).toFixed(2).replace('.', ',');
            prev.style.display = '';
        } else {
            prev.style.display = 'none';
        }
    }

    // ── Consultas (valor pago + UI por consulta) ────────────────────────────────
    function salvarCampo(input, field) {
        const ag = input.dataset.ag;
        input.classList.remove('inp-ok', 'inp-erro');

        let val;
        if (field === 'valor_pago') {
            // Normaliza a entrada (aceita "150" ou "150,00") e reexibe formatado.
            val = input.value.trim() === '' ? null : parseMoeda(input.value);
            input.value = val === null ? '' : fmtMoeda(val);
        } else {
            val = input.value === '' ? null : input.value;
        }

        axios.post(`/aplicacoes/doses/${ag}`, { [field]: val })
            .then(() => {
                input.classList.add('inp-ok');
                setTimeout(() => input.classList.remove('inp-ok'), 1200);
                recalcMes(input.closest('.mes-card'));
                recalcResumo();
            })
            .catch(err => {
                input.classList.add('inp-erro');
                alert(err.response?.data?.message || 'Erro ao salvar.');
            });
    }

    function somaInputs(scope, selector, parser) {
        const fn = parser || (v => parseFloat(v) || 0);
        return [...scope.querySelectorAll(selector)]
            .reduce((acc, el) => acc + fn(el.value), 0);
    }

    function recalcMes(card) {
        if (!card) return;
        const vendido = somaInputs(card, '.inp-valor', parseMoeda);
        const ui      = somaInputs(card, '.inp-ui');
        const lucro   = vendido - ui * CUSTO_POR_UI;

        card.querySelector('.m-vendido').textContent    = brl(vendido);
        card.querySelector('.m-ui-vendido').textContent = intBr(ui);
        const lucroEl = card.querySelector('.m-lucro');
        lucroEl.textContent = brl(lucro);
        lucroEl.classList.toggle('text-success', lucro >= 0);
        lucroEl.classList.toggle('text-danger', lucro < 0);
    }

    function recalcResumo() {
        const doc       = document;
        const recebido  = somaInputs(doc, '.inp-valor', parseMoeda);
        const uiVendido = somaInputs(doc, '.inp-ui');
        const custo     = uiVendido * CUSTO_POR_UI;
        const lucro     = recebido - custo;
        const restante  = UI_COMPRADO - uiVendido;

        doc.getElementById('resumo-recebido').textContent    = brl(recebido);
        doc.getElementById('resumo-custo').textContent       = brl(custo);
        doc.getElementById('resumo-ui-vendido').textContent  = intBr(uiVendido);

        const restEl = doc.getElementById('resumo-ui-restante');
        restEl.textContent = intBr(restante);
        restEl.classList.toggle('text-danger', restante < 0);

        const lucroEl = doc.getElementById('resumo-lucro');
        lucroEl.textContent = brl(lucro);
        lucroEl.classList.toggle('text-success', lucro >= 0);
        lucroEl.classList.toggle('text-danger', lucro < 0);
    }
</script>
@endsection

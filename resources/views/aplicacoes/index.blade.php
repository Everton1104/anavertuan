@extends("layouts.app")
@section("title", "Aplicações Mounjaro")
@section('style')
<style>
    .resumo-card { border-left: 5px solid var(--marrom); }
    .badge-dose { font-size: 0.75rem; }
    .dose-row { background: #f8f9fa; border-radius: 6px; padding: 8px 12px; margin-bottom: 4px; }
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

    {{-- ── RESUMO ──────────────────────────────────────────────────────────── --}}
    <div class="card shadow my-3 resumo-card">
        <div class="card-header fw-bold">Resumo Financeiro</div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-2">
                    <div class="fw-semibold text-muted small">Total recebido</div>
                    <div class="fs-5 text-success">R$ {{ number_format($totalRecebido, 2, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fw-semibold text-muted small">Custo medicamento</div>
                    <div class="fs-5 text-danger">R$ {{ number_format($custoTotal, 2, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fw-semibold text-muted small">Lucro líquido</div>
                    <div class="fs-5 {{ $lucroTotal >= 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($lucroTotal, 2, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fw-semibold text-muted small">Pago fornecedores</div>
                    <div class="fs-5">R$ {{ number_format($totalFornecedor, 2, ',', '.') }}</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fw-semibold text-muted small">Ampolas compradas</div>
                    <div class="fs-5">{{ $totalAmpolas }}</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fw-semibold text-muted small">Total UI estoque</div>
                    <div class="fs-5">{{ number_format($totalUI, 0, ',', '.') }}</div>
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

    {{-- ── CARD FORNECEDORES ───────────────────────────────────────────────── --}}
    <div class="card shadow my-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Fornecedores</span>
            <button class="btn btn-sm btn-outline-light" onclick="abrirNovoFornecedor()" style="background:var(--marrom)">+ Novo</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabela-fornecedores">
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
                            <th class="text-end">UI Restante</th>
                            <th class="text-end">Lucro Acum.</th>
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
                            <td class="text-end {{ $f->ui_sobrando < 0 ? 'text-danger' : '' }}">{{ number_format($f->ui_sobrando, 0, ',', '.') }}</td>
                            <td class="text-end {{ $f->lucro_acumulado >= 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($f->lucro_acumulado, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-muted text-center py-3">Nenhum fornecedor cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── MODAL NOVA APLICAÇÃO ────────────────────────────────────────────── --}}
    <div class="modal fade" id="modal-aplicacao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="titulo-aplicacao">Nova Aplicação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('aplicacoes.store') }}" id="form-aplicacao">
                    @csrf
                    <input type="hidden" name="aplicacao_id" id="aplicacao_id_hidden">
                    <div class="modal-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Cliente</label>
                            <select name="user_id" id="apl_user_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach($clientes as $c)
                                    <option value="{{ $c->id }}">{{ ucfirst($c->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Total Pago pelo Cliente (R$)</label>
                            <input type="number" name="total_pago" id="apl_total_pago" class="form-control" min="0" step="0.01" required>
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

    {{-- ── MODAL NOVA DOSE ─────────────────────────────────────────────────── --}}
    <div class="modal fade" id="modal-dose" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Dose — <span id="dose-cliente-nome"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Vincular a agendamento <span class="text-muted small">(opcional — preenche data e dose automaticamente)</span></label>
                        <select id="dose_agendamento_id" class="form-select" onchange="selecionarAgendamento()">
                            <option value="">Sem vínculo</option>
                        </select>
                        <div id="dose-agendamento-loading" class="text-muted small mt-1 d-none">Carregando agendamentos...</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Fornecedor (lote)</label>
                        <select id="dose_fornecedor_id" class="form-select" required onchange="calcDosePreview()">
                            <option value="">Selecione...</option>
                            @foreach($fornecedores as $f)
                                <option value="{{ $f->id }}" data-custo="{{ $f->custo_por_ui }}">
                                    {{ $f->fornecedor }} — {{ $f->data_compra->format('d/m/Y') }} ({{ $f->produto }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">UI aplicado</label>
                        <input type="number" id="dose_ui" class="form-control" min="1" required oninput="calcDosePreview()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data da aplicação</label>
                        <input type="date" id="dose_data" class="form-control">
                    </div>
                    <div class="col-12">
                        <div class="text-muted small" id="dose-custo-preview"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarDose()">Adicionar</button>
                </div>
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

    {{-- ── CARD APLICAÇÕES ─────────────────────────────────────────────────── --}}
    <div class="card shadow my-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Aplicações por Cliente</span>
            <button class="btn btn-sm btn-outline-light" onclick="abrirNovaAplicacao()" style="background:var(--marrom)">+ Nova</button>
        </div>
        <div class="card-body p-3">
            @forelse($aplicacoes as $apl)
            @php
                $custo  = $apl->custo_medicamento;
                $lucro  = $apl->lucro;
            @endphp
            <div class="card mb-3 border" id="apl-card-{{ $apl->id }}">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <strong>{{ ucfirst($apl->user->name) }}</strong>
                        <span class="badge bg-secondary badge-dose">{{ $apl->doses->count() }} dose(s)</span>
                        <span class="text-muted small">Pago: <strong class="text-success">R$ {{ number_format($apl->total_pago, 2, ',', '.') }}</strong></span>
                        <span class="text-muted small">Custo: <strong class="text-danger">R$ {{ number_format($custo, 2, ',', '.') }}</strong></span>
                        <span class="text-muted small">Lucro: <strong class="{{ $lucro >= 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($lucro, 2, ',', '.') }}</strong></span>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        <button class="btn btn-sm btn-outline-primary" onclick="abrirNovaDose({{ $apl->id }}, '{{ addslashes(ucfirst($apl->user->name)) }}', {{ $apl->user_id }})">+ Dose</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editarAplicacao({{ $apl->id }}, {{ $apl->user_id }}, {{ $apl->total_pago }})">Editar</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="excluirAplicacao({{ $apl->id }}, '{{ addslashes(ucfirst($apl->user->name)) }}')">Excluir</button>
                    </div>
                </div>
                @if($apl->doses->isNotEmpty())
                <div class="card-body py-2 px-3">
                    @foreach($apl->doses as $dose)
                    <div class="dose-row d-flex justify-content-between align-items-center" id="dose-row-{{ $dose->id }}">
                        <div class="d-flex gap-3 align-items-center flex-wrap">
                            <span class="badge bg-primary badge-dose">Dose {{ $dose->numero_dose }}</span>
                            <span class="small"><strong>{{ $dose->ui }}</strong> UI</span>
                            <span class="small text-muted">{{ $dose->fornecedor->fornecedor }} — {{ $dose->fornecedor->data_compra->format('d/m/Y') }}</span>
                            <span class="small text-danger">R$ {{ number_format($dose->custo_dose, 2, ',', '.') }}</span>
                        </div>
                        <svg style="cursor:pointer" onclick="excluirDose({{ $dose->id }}, {{ $apl->id }})" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#dc3545"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @empty
            <p class="text-muted">Nenhuma aplicação registrada.</p>
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
    'custo_por_ui' => $f->custo_por_ui,
]);
@endphp
@section('scriptEnd')
<script>
    const fornecedoresData = @json($fornecedoresJs);

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
                .then(() => { document.getElementById(`forn-row-${id}`)?.remove(); bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-excl')).hide(); })
                .catch(() => alert('Erro ao excluir.'));
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

    // ── Aplicações ────────────────────────────────────────────────────────────
    function abrirNovaAplicacao() {
        document.getElementById('aplicacao_id_hidden').value = '';
        document.getElementById('titulo-aplicacao').textContent = 'Nova Aplicação';
        document.getElementById('form-aplicacao').reset();
        new bootstrap.Modal(document.getElementById('modal-aplicacao')).show();
    }

    function editarAplicacao(id, userId, totalPago) {
        document.getElementById('aplicacao_id_hidden').value = id;
        document.getElementById('titulo-aplicacao').textContent = 'Editar Aplicação';
        document.getElementById('apl_user_id').value       = userId;
        document.getElementById('apl_total_pago').value    = totalPago;
        new bootstrap.Modal(document.getElementById('modal-aplicacao')).show();
    }

    function excluirAplicacao(id, nome) {
        document.getElementById('confirmar-excl-msg').textContent = `Excluir todas as doses e o registro de ${nome}?`;
        document.getElementById('btn-confirmar-excl').onclick = () => {
            axios.delete(`/aplicacoes/clientes/${id}`)
                .then(() => { document.getElementById(`apl-card-${id}`)?.remove(); bootstrap.Modal.getInstance(document.getElementById('modal-confirmar-excl')).hide(); })
                .catch(() => alert('Erro ao excluir.'));
        };
        new bootstrap.Modal(document.getElementById('modal-confirmar-excl')).show();
    }

    // ── Doses ─────────────────────────────────────────────────────────────────
    let doseAplicacaoId  = null;
    let doseClienteUserId = null;

    function abrirNovaDose(aplicacaoId, clienteNome, userId) {
        doseAplicacaoId   = aplicacaoId;
        doseClienteUserId = userId;
        document.getElementById('dose-cliente-nome').textContent  = clienteNome;
        document.getElementById('dose_agendamento_id').innerHTML  = '<option value="">Sem vínculo</option>';
        document.getElementById('dose_fornecedor_id').value       = '';
        document.getElementById('dose_ui').value                  = '';
        document.getElementById('dose_data').value                = '';
        document.getElementById('dose-custo-preview').textContent = '';

        // Carregar agendamentos do cliente
        const loading = document.getElementById('dose-agendamento-loading');
        loading.classList.remove('d-none');
        axios.get(`/api/aplicacoes/agendamentos/${userId}`)
            .then(res => {
                const sel = document.getElementById('dose_agendamento_id');
                res.data.forEach(ag => {
                    const opt = document.createElement('option');
                    opt.value            = ag.id;
                    opt.textContent      = ag.label;
                    opt.dataset.data     = ag.data;
                    sel.appendChild(opt);
                });
            })
            .finally(() => loading.classList.add('d-none'));

        new bootstrap.Modal(document.getElementById('modal-dose')).show();
    }

    function selecionarAgendamento() {
        const sel = document.getElementById('dose_agendamento_id');
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.dataset.data) {
            document.getElementById('dose_data').value = opt.dataset.data;
        }
    }

    function calcDosePreview() {
        const sel   = document.getElementById('dose_fornecedor_id');
        const ui    = parseFloat(document.getElementById('dose_ui').value) || 0;
        const custo = parseFloat(sel.options[sel.selectedIndex]?.dataset.custo) || 0;
        const prev  = document.getElementById('dose-custo-preview');
        prev.textContent = (ui && custo)
            ? 'Custo desta dose: R$ ' + (ui * custo).toFixed(2).replace('.', ',')
            : '';
    }

    function salvarDose() {
        const fornecedorId   = document.getElementById('dose_fornecedor_id').value;
        const ui             = document.getElementById('dose_ui').value;
        const agendamentoId  = document.getElementById('dose_agendamento_id').value || null;
        if (!fornecedorId || !ui) { alert('Preencha fornecedor e UI.'); return; }

        axios.post(`/aplicacoes/clientes/${doseAplicacaoId}/dose`, {
            fornecedor_id:   fornecedorId,
            ui,
            agendamento_id:  agendamentoId,
        }).then(() => location.reload())
          .catch(() => alert('Erro ao adicionar dose.'));
    }

    function excluirDose(doseId, aplicacaoId) {
        document.getElementById('confirmar-excl-msg').textContent = 'Excluir esta dose? As demais serão renumeradas.';
        document.getElementById('btn-confirmar-excl').onclick = () => {
            axios.delete(`/aplicacoes/doses/${doseId}`)
                .then(() => location.reload())
                .catch(() => alert('Erro ao excluir dose.'));
        };
        new bootstrap.Modal(document.getElementById('modal-confirmar-excl')).show();
    }
</script>
@endsection

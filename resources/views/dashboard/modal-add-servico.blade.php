<div class="modal fade modal-lg" data-bs-backdrop="static" id="modal-add-servico" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header alert alert-primary">
                <h5 class="modal-title fs-3">Adicionar Novo Serviço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="form-add-servico" action="{{ route('servico.store') }}" novalidate>
                    @csrf
                    @method('post')
                    <div class="mb-3">
                        <label for="servico_desc" class="form-label">Nome do Serviço</label>
                        <input type="text" class="form-control" id="servico_desc" name="descricao" required>
                    </div>
                    <div class="mb-3">
                        <p>Duração do Serviço</p>
                        <label for="duracao_h" class="form-label">Horas</label>
                        <select class="form-select" name="duracao_h" id="duracao_h">
                            <option value="0">00</option>
                            <option value="1">01</option>
                            <option value="2">02</option>
                        </select>
                        <label for="duracao_m" class="form-label">Minutos</label>
                        <select class="form-select" name="duracao_m" id="duracao_m">
                            <option value="00">00</option>
                            <option value="30">30</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="$('#form-add-servico').submit()">Atualizar</button>
            </div>
        </div>
    </div>
</div>


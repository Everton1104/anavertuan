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
                    <x-app.input label="Nome do Serviço" type="text" name="descricao" id="servico_desc" required="true" />
                    <p class="fs-5 my-2">Duração do Serviço</p>
                    <x-app.select label="Horas" name="duracao_h" required="true" :options="['00'=>'00', '01'=>'01', '02'=>'02']" />
                    <x-app.select label="Minutos" name="duracao_m" required="true" :options="['00'=>'00', '30'=>'30']" />
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="$('#form-add-servico').submit()">Adicionar</button>
            </div>
        </div>
    </div>
</div>


<style>
    .hora-item {
        padding: 8px 14px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        border: 1px solid transparent;
    }

    .hora-disponivel {
        background: #d1e7dd;
        color: #0f5132;
        border-color: #badbcc;
    }

    .hora-indisponivel {
        background: #f8d7da;
        color: #842029;
        border-color: #f5c2c7;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .hora-selecionada {
        background: #0d6efd !important;
        color: white !important;
        border-color: #0a58ca !important;
    }
</style>


<div class="modal fade modal-lg" data-bs-backdrop="static" id="modal-add-consulta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header alert alert-primary">
                <h5 class="modal-title fs-3">Adicionar Nova Consulta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form method="POST" id="form-add-consulta" action="{{ route('agenda.store') }}" novalidate>
                    @csrf
                    @method('post')

                    <!-- Cliente -->
                    <div class="mb-3">
                        <label for="cliente_id" class="form-label">Cliente</label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required>
                            <option value="" selected disabled>Selecione um cliente</option>
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->id }}">{{ $cliente->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Data da consulta -->
                    <div class="mb-3">
                        <label for="data_consulta" class="form-label">Data da Consulta</label>
                        <input type="date" class="form-control" id="data_consulta" name="data_consulta" required>
                    </div>

                    <!-- Serviço -->
                    {{-- <div class="mb-3">
                        <label for="servico_id" class="form-label">Serviço</label>
                        <select class="form-select" id="servico_id" name="servico_id" required>
                            <option value="" selected disabled>Selecione um serviço</option>
                            @foreach($servicos as $servico)
                                <option value="{{ $servico->id }}"  data-duracao="60">{{ $servico->name }}</option>
                            @endforeach
                        </select>
                    </div> --}}

                    <div class="mb-3">
                        <label for="servico_id" class="form-label">Serviço</label>
                        <select class="form-select" id="servico_id" name="servico_id" required>
                            <option value="" selected disabled>Selecione um serviço</option>
                            <option value="1" data-duracao="30">Corte de Cabelo (30 min)</option>
                            <option value="2" data-duracao="60">Massagem Relaxante (1 hora)</option>
                            <option value="3" data-duracao="90">Limpeza de Pele (1h30)</option>
                        </select>
                    </div>


                    <!-- Hora -->
                    <div class="mb-3">
                        <label class="form-label">Horário</label>

                        <!-- Campo real que será enviado no form -->
                        <input type="hidden" id="hora_inicio" name="hora_inicio">

                        <!-- Lista de horários -->
                        <div id="lista-horarios" class="d-flex flex-wrap gap-2"></div>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="$('#form-add-consulta').submit()">Atualizar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const horariosIndisponiveisFixos = ["12:00", "12:30"]; // se quiser bloquear horários fixos

    function gerarHorarios(duracaoMinutos = 30) {
        const container = document.getElementById("lista-horarios");
        container.innerHTML = "";

        const horaInicioExpediente = 9;
        const horaFimExpediente = 20;

        for (let h = horaInicioExpediente; h <= horaFimExpediente; h++) {
            for (let m of ["00", "30"]) {
                const hora = `${String(h).padStart(2, '0')}:${m}`;

                const [hh, mm] = hora.split(":").map(Number);
                const inicio = hh * 60 + mm;
                const fim = inicio + duracaoMinutos;

                const limite = horaFimExpediente * 60;

                // ❗ Se o horário + duração ultrapassa o expediente, NÃO cria o botão
                if (fim > limite) {
                    continue; // <-- simplesmente pula esse horário
                }

                const div = document.createElement("div");
                div.classList.add("hora-item");

                // Horários fixos bloqueados
                if (horariosIndisponiveisFixos.includes(hora)) {
                    div.classList.add("hora-indisponivel");
                } else {
                    div.classList.add("hora-disponivel");

                    div.addEventListener("click", function () {
                        document.querySelectorAll(".hora-item").forEach(i =>
                            i.classList.remove("hora-selecionada")
                        );

                        div.classList.add("hora-selecionada");
                        document.getElementById("hora_inicio").value = hora;
                    });
                }

                div.textContent = hora;
                container.appendChild(div);
            }
        }
    }


    // Quando o usuário troca o serviço
    document.getElementById("servico_id").addEventListener("change", function () {
        const duracao = parseInt(this.selectedOptions[0].dataset.duracao);
        gerarHorarios(duracao);
    });

    // Gera horários padrão (30 min)
    gerarHorarios();
</script>


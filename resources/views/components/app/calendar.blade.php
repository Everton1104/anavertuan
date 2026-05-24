@props(['servicos'])

<style>
    #calendario-container {
        width: 100%;
        max-width: 420px;
        margin: auto;
        font-family: Arial, sans-serif;
    }

    .cal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .cal-header button {
        padding: 6px 12px;
        cursor: pointer;
    }

    .cal-semana, .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        text-align: center;
        gap: 4px;
    }

    .cal-semana div {
        font-weight: bold;
        padding: 6px 0;
    }

    .cal-dia {
        padding: 10px 0;
        border-radius: 4px;
        background: #f1f1f1;
    }

    .cal-dia.disabled {
        opacity: 0.4;
    }

    .cal-fds {
        background: #dbeafe !important; /* azul claro */
        color: #1e3a8a !important;      /* azul escuro */
    }

    .cal-selecionado {
        background: #3e8e41 !important; /* verde escuro */
        color: white !important;
    }

    .hora-item {
        padding: 8px;
        margin-bottom: 6px;
        border-radius: 4px;
        font-weight: 500;
    }

    .hora-livre {
        background: #e9f7ef;
        color: #1e7e34;
    }

    .hora-ocupado {
        background: #f8d7da;
        color: #842029;
    }

</style>


<div id="calendario-container">
    <div class="cal-header">
        <span id="cal-prev" role="button">◀</span>
        <h3 id="cal-mes-ano"></h3>
        <span id="cal-next" role="button">▶</span>
    </div>
    <div class="cal-header" style="justify-content: center; margin-bottom: 10px;">
        <span id="cal-today" class="btn btn-sm btn-primary ">Hoje</span>
    </div>


    <div class="cal-semana">
        <div>Dom</div>
        <div>Seg</div>
        <div>Ter</div>
        <div>Qua</div>
        <div>Qui</div>
        <div>Sex</div>
        <div>Sab</div>
    </div>

    <div id="cal-dias" class="cal-grid"></div>
</div>

<div class="container">
    <p class="fs-5 mt-3">Horários disponíveis</p>
    <p class="text-danger fs-3" id="horarios-erro"></p>
    <div id="horarios"></div>
</div>

<script>

    let removeFds = false; // desativar final de semana

    document.getElementById('servico_id').addEventListener('change', function () {

        // limpar mensagens de erro
        document.getElementById('horarios-erro').textContent = "";

        // limpar horários
        document.getElementById('horarios').innerHTML = "";

        // limpar seleção visual do calendário
        document.querySelectorAll(".cal-dia").forEach(d => d.classList.remove("cal-selecionado"));

        // limpar inputs hidden
        document.getElementById('dia_selecionado').value = "";
        document.getElementById('hora_selecionada').value = "";
        document.getElementById('data_inicio').value = "";
        document.getElementById('data_fim').value = "";
    });

    let dataAtual = new Date();

    // Se existe old(), ajusta o mês/ano inicial
    @if(old('dia_selecionado'))
        dataAtual = new Date("{{ old('dia_selecionado') }}");
    @endif

    function gerarCalendario() {
        const ano = dataAtual.getFullYear();
        const mes = dataAtual.getMonth();

        const nomeMes = dataAtual.toLocaleString('pt-BR', { month: 'long' });
        document.getElementById("cal-mes-ano").textContent =
            `${nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1)} ${ano}`;

        const primeiroDia = new Date(ano, mes, 1);
        const ultimoDia = new Date(ano, mes + 1, 0);

        const inicioSemana = primeiroDia.getDay();
        const totalDias = ultimoDia.getDate();

        const grid = document.getElementById("cal-dias");
        grid.innerHTML = "";

        // Dias do mês anterior
        const diasAntes = inicioSemana;
        const ultimoDiaMesAnterior = new Date(ano, mes, 0).getDate();

        for (let i = diasAntes - 1; i >= 0; i--) {
            const dia = ultimoDiaMesAnterior - i;
            const div = document.createElement("div");
            div.classList.add("cal-dia", "disabled");
            div.textContent = dia;

            const diaSemana = new Date(ano, mes - 1, dia).getDay();
            if (diaSemana === 0 || diaSemana === 6) div.classList.add("cal-fds");

            grid.appendChild(div);
        }

        // Hoje (sem hora) para comparação
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        // Dias do mês atual
        for (let d = 1; d <= totalDias; d++) {
            const div = document.createElement("div");
            div.classList.add("cal-dia");
            div.textContent = d;

            const dataDiv = new Date(ano, mes, d);
            const diaSemana = dataDiv.getDay();

            // Bloquear datas passadas
            if (dataDiv < hoje) {
                div.classList.add("disabled");
                grid.appendChild(div);
                continue;
            }

            if (diaSemana === 0 || diaSemana === 6) {
                if (removeFds) {
                    div.classList.add("cal-fds", "disabled");
                    grid.appendChild(div);
                    continue;
                } else {
                    div.classList.add("cal-fds");
                }
            }

            div.style.cursor = "pointer";
            div.onclick = function() {
                document.getElementById('horarios-erro').textContent = "";

                document.querySelectorAll(".cal-dia").forEach(d => d.classList.remove("cal-selecionado"));
                div.classList.add("cal-selecionado");

                const mesFormatado = String(mes + 1).padStart(2, '0');
                const diaFormatado = String(d).padStart(2, '0');
                const dataCompleta = `${ano}-${mesFormatado}-${diaFormatado}`;

                document.getElementById('dia_selecionado').value = dataCompleta;

                getHoras(dataCompleta);
            };

            grid.appendChild(div);
        }

        // Dias do próximo mês
        const totalCelulas = diasAntes + totalDias;
        const resto = totalCelulas % 7;

        if (resto !== 0) {
            const diasProx = 7 - resto;

            for (let d = 1; d <= diasProx; d++) {
                const div = document.createElement("div");
                div.classList.add("cal-dia", "disabled");
                div.textContent = d;

                const diaSemana = new Date(ano, mes + 1, d).getDay();
                if (diaSemana === 0 || diaSemana === 6) div.classList.add("cal-fds");

                grid.appendChild(div);
            }
        }

        // Restaurar seleção do dia
        @if(old('dia_selecionado'))
            const oldDate = "{{ old('dia_selecionado') }}";
            const [anoOld, mesOld, diaOld] = oldDate.split('-');

            if (anoOld == dataAtual.getFullYear() && mesOld == String(dataAtual.getMonth() + 1).padStart(2, '0')) {
                document.querySelectorAll('.cal-dia').forEach(div => {
                    if (div.textContent == Number(diaOld)) {

                        // marcar visualmente
                        div.classList.add('cal-selecionado');

                        // preencher o hidden (🔥 ESSENCIAL)
                        document.getElementById('dia_selecionado').value = oldDate;
                    }
                });
            }
        @endif

    }

    // Navegação
    document.getElementById("cal-prev").addEventListener("click", () => {
        dataAtual.setMonth(dataAtual.getMonth() - 1);
        gerarCalendario();
    });

    document.getElementById("cal-next").addEventListener("click", () => {
        dataAtual.setMonth(dataAtual.getMonth() + 1);
        gerarCalendario();
    });

    document.getElementById("cal-today").addEventListener("click", () => {
        dataAtual = new Date();
        gerarCalendario();
    });

    function getHoras(data) {
        let servico_id = document.getElementById('servico_id').value;

        if (!servico_id) {
            document.getElementById('servico_id').classList.add('is-invalid');
            document.querySelectorAll(".cal-dia").forEach(d => d.classList.remove("cal-selecionado"));
            return;
        }

        document.getElementById('servico_id').classList.remove('is-invalid');
        document.getElementById('horarios').innerHTML = "<p>Carregando...</p>";

        axios.get('/api/horarios/' + data, { params: { 
            servico_id,
            ignore_id: document.getElementById('agendamento_id')?.value ?? null
        } })
            .then(response => mostrarHorarios(response.data))
            .catch(() => {
                document.getElementById('horarios').innerHTML = "<p>Falha ao carregar os horários</p>";
            });
    }

    function mostrarHorarios(lista) {
        const container = document.getElementById('horarios');
        container.innerHTML = "";

        // Recuperar valores antigos (caso tenha erro de validação)
        const horaSelecionadaOld = "{{ old('hora_selecionada') }}";
        const diaSelecionadoOld = "{{ old('dia_selecionado') }}";

        lista.forEach(item => {
            const div = document.createElement("div");
            div.classList.add("hora-item");

            if (item.ocupado) {
                div.classList.add("hora-ocupado");
                div.textContent = item.hora + " — " + (item.motivo ?? "Indisponível");
                div.style.opacity = "0.6";
                div.style.cursor = "not-allowed";
            } else {
                div.classList.add("hora-livre");
                div.textContent = item.hora + " — Disponível";
                div.style.cursor = "pointer";

                // Clique no horário
                div.onclick = function() {

                    if (item.ocupado) return; //impede clique em horários inválidos

                    // limpar erro
                    document.getElementById('horarios-erro').textContent = "";

                    // remover seleção anterior
                    document.querySelectorAll(".hora-item").forEach(h => h.classList.remove("cal-selecionado"));
                    div.classList.add("cal-selecionado");

                    // salvar hora selecionada
                    document.getElementById('hora_selecionada').value = item.hora;

                    // pegar o dia selecionado
                    const dataSelecionada = document.getElementById('dia_selecionado').value;

                    // montar data_inicio
                    const dataInicio = `${dataSelecionada} ${item.hora}`;
                    document.getElementById('data_inicio').value = dataInicio;

                    // calcular data_fim baseado no serviço
                    calcularDataFim(dataInicio);
                };
            }

            // 🔥 Restaurar seleção após erro
            if (horaSelecionadaOld && item.hora === horaSelecionadaOld) {
                div.classList.add("cal-selecionado");

                // reconstruir data_inicio
                const dataSelecionada = diaSelecionadoOld;
                const dataInicio = `${dataSelecionada} ${item.hora}`;

                // preencher inputs hidden
                document.getElementById('hora_selecionada').value = item.hora;
                document.getElementById('data_inicio').value = dataInicio;

                // calcular data_fim
                calcularDataFim(dataInicio);
            }

            container.appendChild(div);
        });
    }


    function calcularDataFim(dataInicio) {
        const servico_id = document.getElementById('servico_id').value;
        const servico = SERVICOS.find(s => s.id == servico_id);
        if (!servico) return;

        const [h, m] = servico.duracao.split(':').map(Number);
        const minutos = h * 60 + m;

        const inicio = new Date(dataInicio.replace(' ', 'T'));
        if (isNaN(inicio)) return;

        const fim = new Date(inicio.getTime() + minutos * 60000);

        const ano = fim.getFullYear();
        const mes = String(fim.getMonth() + 1).padStart(2, '0');
        const dia = String(fim.getDate()).padStart(2, '0');
        const hora = String(fim.getHours()).padStart(2, '0');
        const min = String(fim.getMinutes()).padStart(2, '0');

        document.getElementById('data_fim').value = `${ano}-${mes}-${dia} ${hora}:${min}`;
    }

    const SERVICOS = @json($servicos);

    document.addEventListener("DOMContentLoaded", function() {
        gerarCalendario();
    });
</script>

@error('data_inicio')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById('horarios-erro').textContent = "{{ $message }}";
        });
    </script>
@enderror

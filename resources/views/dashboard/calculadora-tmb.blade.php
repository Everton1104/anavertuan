<div class="container mt-4">
    <h3>Calculadora de Taxa Metabólica Basal (TMB)</h3>

    <div class="mb-3">
        <label>Sexo</label>
        <select id="tmb-sexo" class="form-select">
            <option value="homem">Homem</option>
            <option value="mulher">Mulher</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Peso (kg)</label>
        <input type="number" id="tmb-peso" class="form-control">
    </div>

    <div class="mb-3">
        <label>Altura (cm)</label>
        <input type="number" id="tmb-altura" class="form-control">
    </div>

    <div class="mb-3">
        <label>Idade</label>
        <input type="number" id="tmb-idade" class="form-control">
    </div>

    <button class="btn btn-primary" onclick="calcularTMB()">Calcular</button>

    <h4 class="mt-3">Resultado: <span id="tmb-resultado"></span> kcal/dia</h4>
</div>

<script>
    function calcularTMB() {
        const sexo = document.getElementById("tmb-sexo").value;
        const peso = parseFloat(document.getElementById("tmb-peso").value);
        const altura = parseFloat(document.getElementById("tmb-altura").value);
        const idade = parseFloat(document.getElementById("tmb-idade").value);

        let tmb = 0;

        if (sexo === "homem") {
            tmb = (10 * peso) + (6.25 * altura) - (5 * idade) + 5;
        } else {
            tmb = (10 * peso) + (6.25 * altura) - (5 * idade) - 161;
        }

        document.getElementById("tmb-resultado").innerText = tmb.toFixed(0);
    }
</script>
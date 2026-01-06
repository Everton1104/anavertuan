<div class="container mt-4">
    <h3>Calculadora de Taxa Metabólica Basal (TMB)</h3>

    <p class="form-text">
        Taxa Metabólica Basal (TMB) é a quantidade de calorias que seu corpo gasta em repouso para manter funções vitais como respiração, circulação e temperatura corporal.
    </p>

    <div class="mb-3">
        <label>Sexo</label>
        <select id="tmb-sexo" class="form-select">
            <option value="homem">Homem</option>
            <option value="mulher">Mulher</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Peso (kg)</label>
        <input type="number" id="tmb-peso" placeholder="Ex: 85" class="form-control">
    </div>

    <div class="mb-3">
        <label>Altura (cm)</label>
        <input type="number" id="tmb-altura" placeholder="Ex: 175" class="form-control">
    </div>

    <div class="mb-3">
        <label>Idade</label>
        <input type="number" id="tmb-idade" placeholder="Ex: 30" class="form-control">
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

        if (isNaN(tmb)) {
            document.getElementById("tmb-resultado").innerText = "Insira os dados corretamente";
            return;
        }

        document.getElementById("tmb-resultado").innerText = tmb.toFixed(0);
    }
</script>
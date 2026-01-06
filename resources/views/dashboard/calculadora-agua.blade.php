<div class="container mt-4">
    <h3>Calculadora de Consumo Diário de Água</h3>

    <div class="mb-3">
        <label>Peso (kg)</label>
        <input type="number" id="agua-peso" class="form-control" placeholder="Ex: 75">
    </div>

    <button class="btn btn-primary" onclick="calcularAgua()">Calcular</button>

    <h4 class="mt-3">Você deve beber: <span id="agua-resultado"></span></h4>
</div>

<script>
    function calcularAgua() {
        const peso = parseFloat(document.getElementById("agua-peso").value);

        if (!peso || peso <= 0) {
            document.getElementById("agua-resultado").innerText = "Informe um peso válido.";
            return;
        }

        // Fórmula: 35 ml por kg
        const mlPorDia = peso * 35;
        const litros = mlPorDia / 1000;

        document.getElementById("agua-resultado").innerText =
            `${mlPorDia.toFixed(0)} ml por dia (${litros.toFixed(2)} litros)`;
    }
</script>
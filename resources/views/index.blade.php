@extends("layouts.app")
@section("title", "Nutri Ana Vertuan")
@section("main")
  
    <!-- Hero Section -->
    <header class="bg-success text-white text-center py-5">
        <div class="container">
        <h1 class="display-4">Nutri Ana Vertuan</h1>
        <p class="lead">Cuidando da sua saúde e bem-estar através da nutrição</p>
        <a href="https://wa.me/5511975712377" target="_blank" class="btn btn-light btn-lg mt-3">Agende sua consulta</a>
        </div>
    </header>

    <!-- Sobre -->
    <section id="sobre" class="py-5">
        <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
            <img src="storage/perfil.png?time={{date("His")}}" class="img-fluid rounded shadow" alt="Nutricionista">
            </div>
            <div class="col-md-6">
                <h2>Sobre mim</h2>
                <p>Sou a <strong>Nutri Ana Vertuan</strong>, nutricionista dedicada a promover saúde e qualidade de vida por meio de uma abordagem integrativa. Além da alimentação equilibrada e personalizada, também atuo com <strong>auriculoterapia</strong>, técnica que potencializa o bem-estar físico e emocional. Meu objetivo é ajudar você a alcançar seus objetivos de forma saudável, sustentável e completa — cuidando do corpo, da mente e da energia vital.</p>
            </div>
        </div>
        </div>
    </section>

    <!-- Serviços -->
    <section id="servicos" class="py-5 bg-light">
        <div class="container text-center">
        <h2 class="mb-4">Serviços</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm p-3">
                    <h5 class="card-title">Consultas Individuais</h5>
                    <p class="card-text">Atendimento personalizado com plano alimentar adaptado à sua rotina, objetivos e preferências. Foco em saúde, bem-estar e resultados sustentáveis.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm p-3">
                    <h5 class="card-title">Auriculoterapia</h5>
                    <p class="card-text">Terapia complementar que estimula pontos específicos na orelha para promover equilíbrio físico e emocional, baseada nos princípios da medicina tradicional chinesa.</p>
                </div>
            </div>
        </div>
        </div>
    </section>

    <!-- Contato -->
    <section id="contato" class="py-5">
        <div class="container text-center">
        <h2 class="mb-4">Entre em Contato</h2>
        <p>Agende sua consulta ou tire suas dúvidas:</p>
        <a href="mailto:contato@nutrianavertuan.com" class="btn btn-success btn-lg">contato@nutrianavertuan.com.br</a>
        </div>
    </section>
@endsection
@section("footer")
<footer class="bg-dark text-white text-center py-3">
    <p class="mb-0">&copy; {{date("Y")}} Nutri Ana Vertuan - Todos os direitos reservados - By Evtu</p>
</footer>
@endsection
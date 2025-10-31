@extends("layouts.app")
@section("title", "Nutri Ana Vertuan")
@section("main")
    <style>
        .cover {
            background-image: url("{{ Storage::url('fundo.png') }}");
            background-repeat: no-repeat;
            background-size: cover;
            height: 100vh;
            width: 100vw;
            opacity: .3;
            position: fixed;
            z-index: -1;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        main {
            overflow-x: hidden;
        }

        .hero {
            background-color: var(--marrom);
        }

        .btn-agendar {
            background-color: var(--cinza);
        }
    
    </style>

    <div class="cover"></div>

    <!-- Hero Section -->
    <header class="hero text-white py-5">
        <div class="container">
            <div class="row align-items-center text-center">
                <div class="mb-4 col-12 d-flex justify-content-center">
                    <img src="{{ Storage::url('logo-lg.png') }}" alt="Nutri Ana Vertuan" class="img-fluid w-50">
                </div>
                <div class="mb-4 col-12">
                    <p class="lead">Cuidando da sua saúde e bem-estar através da nutrição</p>
                </div>    
                <div class="col-12">
                    <a href="https://wa.me/5511975712377" target="_blank" class="btn btn-agendar btn-outline-light mt-3">Agende sua consulta</a>
                </div>    
            </div>
        </div>
    </header>

    <!-- Sobre -->
    <section id="sobre" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                <img src="storage/perfil.png?time={{date("His")}}" class="img-fluid rounded shadow" alt="Nutricionista">
                </div>
                <div class="col-md-6 m-2 m-md-0">
                    <p class="fs-3">Sobre mim</p>
                    <p>Sou a <strong>Nutri Ana Vertuan</strong>, nutricionista dedicada a promover saúde e qualidade de vida por meio de uma abordagem integrativa. Além da alimentação equilibrada e personalizada, também atuo com <strong>auriculoterapia</strong>, técnica que potencializa o bem-estar físico e emocional. Meu objetivo é ajudar você a alcançar seus objetivos de forma saudável, sustentável e completa — cuidando do corpo, da mente e da energia vital.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Serviços -->
    <section id="servicos" class="py-5">
        <div class="container text-center">
            <p class="mb-4 fs-2">Serviços</p>
            <div class="row">
                <div class="col-md-6 mb-2 m-md-0">
                    <div class="card shadow-sm p-2" style="height: 180px">
                        <h5 class="card-title">Consultas Individuais</h5>
                        <p class="card-text">Atendimento personalizado com plano alimentar adaptado à sua rotina, objetivos e preferências. Foco em saúde, bem-estar e resultados sustentáveis.</p>
                    </div>
                </div>
                <div class="col-md-6 mb-2 m-md-0">
                    <div class="card shadow-sm p-3" style="height: 180px">
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
        <h2 class="mb-4 fs-2">Entre em Contato</h2>
        <p>Agende sua consulta ou tire suas dúvidas:</p>
        <a href="mailto:contato@nutrianavertuan.com" class="btn btn-success">contato@nutrianavertuan.com.br</a>
        </div>
    </section>
@endsection
@section("footer")
<footer class="bg-dark text-white text-center">
    <p class="mb-0" style="font-size: 10px">&copy; {{date("Y")}} - Todos os direitos reservados - By EVTU</p>
</footer>
@endsection
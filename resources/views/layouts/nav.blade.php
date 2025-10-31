<style>
    .nav {
        background-color: var(--branco);
    }
</style>

<nav class="nav shadow-sm d-none d-md-block">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-6 py-3">
                <img src="{{ Storage::url('logo-lg.png') }}?time={{date("His")}}" alt="Nutri Ana Vertuan" class="img-fluid" style="width: 15vh">
            </div>
            <div class="col-6 text-end form-text text-secondary">
                <a href="#sobre" class="me-3">Sobre</a>
                <a href="#servicos" class="me-3">Serviços</a>
                <a href="#contato">Contato</a>
            </div>
        </div>
    </div>
</nav>

<div class="side-menu">
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='#sobre'},500)">
        Sobre
    </div>
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='#servicos'},500)">
        Serviços
    </div>
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='#contato'},500)">
        Contato
    </div>

    {{-- LOGOUT --}}
    <div class="menu-item menu-sair" onclick="$('#logout-form').submit()">
        SAIR
        <form id="logout-form" action="/logout" method="POST">
            @csrf
            @method('POST')
        </form>
    </div>
</div>

{{-- Backdrop --}}
<div class="backdrop-menu position-fixed d-none vw-100 vh-100"></div>
<div class="d-md-none d-block">
    <svg class="menu-icon" id="menu-bars" height="2rem" viewBox="0 -960 960 960" width="2rem" fill="#a6a6a6"><path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/></svg>
    <svg class="menu-icon d-none" id="menu-times" height="2rem" viewBox="0 -960 960 960" width="2rem" fill="#a6a6a6"><path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>
</div>

<style>
    .menu-icon {
        cursor: pointer;
        z-index: 9999;
        position: fixed;
        top: 5px;
        right: 5px;
    }
    .menu-sair {
        position: absolute ;
        bottom: 15px;
    }
    .side-menu {
        position: fixed;
        z-index: 99;
        top: 0;
        right: -310px;
        width: 300px;
        height: 100%;
        background-color: var(--branco);
        padding: 10px;
        padding-top: 40px;
        border-right: 1px solid #000000;
    }
    .menu-item {
        cursor: pointer;
        z-index: 999;
        padding: 10px;
        width: 100%;
    }
    .menu-item:hover {
        background-color: var(--cinza);
    }
    .backdrop-menu {
        position: fixed;
        z-index: 9;
        height: 100vh;
        width: 100vw;
        top: 0;
        right: 0;
        backdrop-filter: blur(5px);
    }
</style>

<script>
    $('.menu-icon').on('click', function(){
        if($('.side-menu').css('right') < '0') {
            $('#menu-bars').addClass('d-none');
            $('#menu-times').removeClass('d-none');
            $('.backdrop-menu').removeClass('d-none');
            $('#menu-times').animate({
                right: "260px",
            })
            $('.side-menu').animate({
                right: "0px",
            })
        }else{
            $('#menu-bars').removeClass('d-none');
            $('#menu-times').addClass('d-none');
            $('.backdrop-menu').addClass('d-none');
            $('#menu-times').animate({
                right: "0px",
            })
            $('.side-menu').animate({
                right: "-310px",
            })
        }
    });
    $('.main').on('click', function(){
        $('#menu-bars').removeClass('d-none');
        $('#menu-times').addClass('d-none');
        $('.side-menu').animate({
            right: "-310px",
        })
    });
</script>
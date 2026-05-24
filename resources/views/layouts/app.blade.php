<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" /> 
        <meta name="theme-color" content="#f6f0e6">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="msapplication-navbutton-color" content="#f6f0e6">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="apple-touch-icon" href="/storage/favicon.ico?time={{time()}}">
        <link rel="icon" href="/storage/favicon.ico?time={{time()}}" type="image/x-icon">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery-mask-plugin@1.14.16/dist/jquery.mask.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <title>@yield("title") - Nutri Ana Vertuan</title>
        @yield("style")
        @vite(['resources/js/app.js'])
        @yield("scriptTop")
        <style>
            :root {
                --marrom: #a16e51;
                --bege: #f0b99a;
                --branco: #f6f0e6;
                --cinza: #a6a6a6;
            }
        </style>
    </head>
    <body>
        @include("layouts.nav")
        <main>
            @include('components.msg')
            @yield("main")
        </main>
        <footer>
            @yield("footer")
            <footer class="bg-dark text-white text-center" style="bottom:0px;position:fixed;width:100vw;">
                <p class="mb-0" style="font-size: 10px;">&copy; {{date("Y")}} - Todos os direitos reservados - By <a href="https://evtu.com.br" target="_blank">EVTU</a></p>
            </footer>
        </footer>
        @yield("scriptEnd")
        <script>
            $(function () {
                $('[name="whatsapp"], [name="whatsapp_edt"]').mask('+55 (00) 00000-0000');
            });
        </script>
        <script>
            // Example starter JavaScript for disabling form submissions if there are invalid fields
            (() => {
            'use strict'

            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            const forms = document.querySelectorAll('.needs-validation')

            // Loop over them and prevent submission
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')
                }, false)
            })
            })()
        </script>
    </body>
</html>

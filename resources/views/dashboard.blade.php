@extends("layouts.app")
@section("title", "Dashboard")
@section("main")
<div class="container">
    <h1 class="fs-3 my-3">Dashboard</h1>

    {{-- Seção de controle para administradores --}}
    @if(auth()->user()->adm == 1)
        <div class="card mb-4">
            <div class="card-header">Controle de Contas de Usuário</div>
            <div class="card-body">
                @foreach ($users as $user)
                    {{$user->name}}<br>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Calendário --}}
    <div class="card my-3 d-none d-md-block">
        <div class="card-header">Calendário</div>
        <div class="card-body">
            <div class="container-fluid">
                <div id="calendar-lg" class="w-100"></div>
            </div>
        </div>
    </div>
    <div id="calendar-sm" class="w-100 my-5 d-md-none"></div>
</div>
@endsection

@section('scriptEnd')
    <!-- FullCalendar JS + CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

    <script>
        // Tela LG
        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar-lg');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                allDayText: 'O dia todo',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia'
                },
                dateClick: function(info) {
                    console.log("DateClick");
                    console.log(info.dateStr);
                    console.log(info);
                },
                eventClick: function(info) {
                    console.log("EventClick");
                    console.log(info.event.title);
                    console.log(info.event.startStr);
                    console.log(info.event.endStr);
                    console.log(info);
                },
                events: [
                    {
                        title: 'Fulano',
                        start: '2025-11-05T10:00:00',
                        end: '2025-11-05T11:00:00'
                    },
                    {
                        title: 'Ciclano',
                        start: '2025-11-05T11:15:00',
                        end: '2025-11-05T12:15:00'
                    },
                    {
                        title: 'Beltrano',
                        start: '2025-11-12',
                    }
                ]
            });

            calendar.render();
        });
        // Tela SM
        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar-sm');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                allDayText: 'O dia todo',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia'
                },
                dateClick: function(info) {
                    console.log("DateClick");
                    console.log(info.dateStr);
                    console.log(info);
                },
                eventClick: function(info) {
                    console.log("EventClick");
                    console.log(info.event.title);
                    console.log(info.event.startStr);
                    console.log(info.event.endStr);
                    console.log(info);
                },
                events: [
                    {
                        title: 'Fulano',
                        start: '2025-11-05T10:00:00',
                        end: '2025-11-05T11:00:00'
                    },
                    {
                        title: 'Ciclano',
                        start: '2025-11-05T11:15:00',
                        end: '2025-11-05T12:15:00'
                    },
                    {
                        title: 'Beltrano',
                        start: '2025-11-12',
                    }
                ]
            });

            calendar.render();
        });
        $(document).ready(function () {
            // Aguarda o calendário renderizar
            setTimeout(function () {
                if(isSM()){
                    $('.fc-header-toolbar').addClass('row');
                    $('.fc-toolbar-chunk').addClass('my-2');
                }
                
            }, 250);
        });
        function isSM() {
            return window.matchMedia("(min-width: 768px)").matches ? false : true;
        }
    </script>
@endsection
@section('style')
    <style>
        .fc-button {
            background-color: var(--marrom) !important;
            border-color: var(--cinza) !important;
        }
        .card-header {
            background-color: var(--branco);
        }
        #calendar {
            max-width: 100%;
            margin: 0 auto;
            max-width: 100%;
            overflow-x: auto;
        }
        .fc-daygrid-day {
            cursor: pointer;
        }
        .fc-timegrid-slot {
            cursor: pointer;
        }
        .fc-event-main-frame {
            cursor: pointer;
        }
        .fc-daygrid-day:hover {
            background-color: #f0f8ff;
        }
    </style>
@endsection
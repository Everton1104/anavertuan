@extends('layouts.app')
@section("title", "LOGIN")
@section('main')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <a href="/">
                <img src="/storage/logo-lg.png" alt="logo" class="img-fluid" style="width: 50vw">
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">

            <x-auth-session-status class="mb-4" :status="session('status')" />

            {{ $slot }}
        </div>
    </div>
@endsection

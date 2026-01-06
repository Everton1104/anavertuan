<?php

use App\Http\Controllers\Agenda\AgendaControler;
use App\Http\Controllers\Agenda\ServicoController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::get('/dashboard', function () {
    $users = User::paginate(10);
    $clientes = User::where([['func','=','0'],['adm','=','0']])->get(); //clientes

    $servicos = User::where('func','=','0')->get();///////////////////////////////temporario

    return view('dashboard', compact('users', 'clientes', 'servicos'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::post('add-usuario', [RegisteredUserController::class, 'store'])->middleware(['auth', 'verified'])->name('add-usuario');
Route::post('delete-usuario', [RegisteredUserController::class, 'delete'])->middleware('auth')->name('delete-usuario');
Route::post('editar-usuario', [RegisteredUserController::class, 'editar'])->middleware('auth')->name('editar-usuario');
Route::resource('agenda', AgendaControler::class)->middleware('auth');
Route::resource('servico', ServicoController::class)->middleware('auth');

require __DIR__.'/auth.php';

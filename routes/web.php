<?php

use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Agenda\ServicoController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use App\Models\AgendamentoModel;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::get('/dashboard', function () {
    $users = User::where('excluido','=','0')->orderBy('adm', 'desc')->orderBy('func', 'desc')->paginate(10);
    $clientes = User::where([['excluido','=','0'],['func','=','0'],['adm','=','0']])->get(); //clientes
    $servicos = ServicosModel::where('excluido','=','0')->get();
    return view('dashboard', compact('users', 'clientes', 'servicos'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/api/horarios/{data}', [AgendaController::class, 'horarios']);

Route::post('add-usuario', [RegisteredUserController::class, 'store'])->middleware(['auth', 'verified'])->name('add-usuario');
Route::post('delete-usuario', [RegisteredUserController::class, 'delete'])->middleware('auth')->name('delete-usuario');
Route::post('editar-usuario', [RegisteredUserController::class, 'editar'])->middleware('auth')->name('editar-usuario');
Route::get('usuarios-search', [RegisteredUserController::class, 'search'])->name('usuarios.search');
Route::resource('agenda', AgendaController::class)->middleware('auth');
Route::get('agenda-search', [AgendaController::class, 'search'])->name('agenda.search');
Route::resource('servico', ServicoController::class)->middleware('auth');
Route::post('delete-servico', [ServicoController::class, 'delete'])->middleware('auth')->name('delete-servico');
Route::post('editar-servico', [ServicoController::class, 'editar'])->middleware('auth')->name('editar-servico');


require __DIR__.'/auth.php';

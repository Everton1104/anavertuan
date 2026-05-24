<?php

use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Agenda\ServicoController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WhatsappController;
use App\Models\AgendamentoModel;
use App\Models\Aviso;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::get('/dashboard', function () {
    $user     = auth()->user();
    $users    = User::where('excluido', '0')->orderBy('adm', 'desc')->orderBy('func', 'desc')->paginate(10);
    $clientes = User::where([['excluido', '0'], ['func', '0'], ['adm', '0']])->get();
    $servicos = ServicosModel::where('excluido', '0')->get();
    $mesAtual = now()->locale('pt_BR')->translatedFormat('F Y');

    $consultasQuery = AgendamentoModel::with(['user', 'servico'])->orderBy('data_inicio');

    if (!$user->adm && !$user->func) {
        $consultasQuery->where('user_id', $user->id);
    }

    $consultas = $consultasQuery->get()->groupBy(
        fn($item) => \Carbon\Carbon::parse($item->data_inicio)->locale('pt_BR')->translatedFormat('F Y')
    );

    $avisos = ($user->adm || $user->func)
        ? Aviso::with(['user', 'servico'])->whereNull('dispensado_at')->latest()->get()
        : collect();

    return view('dashboard', compact('users', 'clientes', 'servicos', 'consultas', 'mesAtual', 'avisos'));
})->middleware(['auth', 'verified', 'whatsapp.verified'])->name('dashboard');

Route::get('/api/horarios/{data}', [AgendaController::class, 'horarios']);
Route::get('/api/dias-disponiveis/{ano}/{mes}', [AgendaController::class, 'diasDisponiveis']);
Route::get('/api/disponibilidade/{data}', [AgendaController::class, 'getSlotsDia'])->middleware('auth');
Route::post('/api/disponibilidade/{data}', [AgendaController::class, 'salvarSlots'])->middleware('auth');

Route::post('add-usuario', [RegisteredUserController::class, 'store'])->middleware(['auth', 'verified'])->name('add-usuario');
Route::post('delete-usuario', [RegisteredUserController::class, 'delete'])->middleware('auth')->name('delete-usuario');
Route::post('editar-usuario', [RegisteredUserController::class, 'editar'])->middleware('auth')->name('editar-usuario');
Route::get('usuarios-search', [RegisteredUserController::class, 'search'])->middleware('auth')->name('usuarios.search');
Route::resource('agenda', AgendaController::class)->middleware('auth');
Route::post('agenda/{id}/confirmar', [AgendaController::class, 'confirmar'])->middleware('auth')->name('agenda.confirmar');
Route::post('aviso/{id}/dispensar', [AgendaController::class, 'dispensarAviso'])->middleware('auth')->name('aviso.dispensar');
Route::get('agenda-search', [AgendaController::class, 'search'])->name('agenda.search');
Route::resource('servico', ServicoController::class)->middleware('auth');
Route::post('delete-servico', [ServicoController::class, 'delete'])->middleware('auth')->name('delete-servico');
Route::post('editar-servico', [ServicoController::class, 'editar'])->middleware('auth')->name('editar-servico');


// ── WhatsApp webhook (público) ────────────────────────────────────────────────
Route::get('/whatsapp/webhook',  [WhatsappController::class, 'virifyToken']);
Route::post('/whatsapp/webhook', [WhatsappController::class, 'getMsgs']);

// ── WhatsApp verificação de número ────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/cadastrar-whatsapp', function () {
        if (auth()->user()->whatsapp) {
            return redirect()->route('verificar.whatsapp');
        }
        return view('auth.cadastrar-whatsapp');
    })->name('cadastrar.whatsapp');

    Route::post('/cadastrar-whatsapp', function (\Illuminate\Http\Request $req) {
        $req->validate(['whatsapp' => 'required|string|max:20']);
        $numero = preg_replace('/\D/', '', $req->whatsapp);
        if (strlen($numero) < 10) {
            return back()->withErrors(['whatsapp' => 'Número inválido.'])->withInput();
        }
        $user = auth()->user();
        $user->whatsapp              = '55' . $numero;
        $user->whatsapp_verified_at  = null;
        $user->save();
        WhatsappController::enviarCodigoVerificacao($user);
        return redirect()->route('verificar.whatsapp')->with('status', 'Código enviado!');
    })->name('cadastrar.whatsapp.salvar');

    Route::get('/verificar-whatsapp', function () {
        $user = auth()->user();
        if ($user->whatsappVerificado()) {
            return redirect()->route('dashboard');
        }
        $codigoEnviado = !is_null($user->whatsapp_code_expires_at);
        $aguardar = 0;
        if ($codigoEnviado) {
            // 60s de cooldown: expires_at é 600s a frente, aguardar = remaining - 540
            $aguardar = max(0, $user->whatsapp_code_expires_at->diffInSeconds(now()) - 300);
        }
        return view('auth.verificar-whatsapp', compact('aguardar', 'codigoEnviado'));
    })->name('verificar.whatsapp');

    Route::post('/verificar-whatsapp', function (\Illuminate\Http\Request $req) {
        $req->validate(['codigo' => 'required|string|size:6']);
        $user = auth()->user();
        if ($user->whatsapp_code !== $req->codigo || now()->gt($user->whatsapp_code_expires_at)) {
            return back()->withErrors(['codigo' => 'Código inválido ou expirado.']);
        }
        $user->whatsapp_verified_at     = now();
        $user->whatsapp_code            = null;
        $user->whatsapp_code_expires_at = null;
        $user->save();
        return redirect()->route('dashboard')->with('msg', 'WhatsApp verificado com sucesso!');
    })->name('verificar.whatsapp.confirmar');

    Route::post('/verificar-whatsapp/reenviar', function () {
        $user = auth()->user();
        if ($user->whatsappVerificado()) {
            return redirect()->route('dashboard');
        }
        $aguardar = $user->whatsapp_code_expires_at
            ? max(0, $user->whatsapp_code_expires_at->diffInSeconds(now()) - 300)
            : 0;
        if ($aguardar > 0) {
            return redirect()->route('verificar.whatsapp')
                ->with('error', "Aguarde {$aguardar}s antes de reenviar.");
        }
        WhatsappController::enviarCodigoVerificacao($user);
        return redirect()->route('verificar.whatsapp')->with('status', 'Código enviado!');
    })->name('verificar.whatsapp.reenviar');
});

require __DIR__.'/auth.php';

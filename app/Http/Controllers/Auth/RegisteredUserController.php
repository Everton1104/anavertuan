<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'senha' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->nome,
            'email' => $request->email,
            'password' => Hash::make($request->senha),
            'adm' => $request['tipo'] == 'adm' ? 1 : 0,
            'func' => $request['tipo'] == 'func' ? 1 : 0
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    public function delete(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|integer|gt:1',
        ]);

        if ($validation->fails()) {
            return redirect()->back()->with('msgErro', 'Falha ao excluir usuário!');
        }

        User::find($request['id'])->update(['excluido' => 1]);
        return redirect()->back()->with('msg', 'Usuário excluído com sucesso!');
    }

    public function editar(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|integer',
            'nome_edt' => 'required|string|max:255',
            'email_edt' => 'required|string|email|max:255',
        ]);

        if($request->has('senha_edt')){
            if($request['senha_edt'] != $request['senha_confirmation_edt']){
                return redirect()->back()->with('msgErro', 'Senhas não conferem!');
            }else{
                User::find($request['id'])->update([
                    'password' => Hash::make($request['senha_edt']),
                ]);
            }
        }

        if ($validation->fails()) {
            return redirect()->back()->with('msgErro', 'Falha ao atualizar usuário!');
        }

        User::find($request['id'])->update([
            'name' => $request['nome_edt'],
            'email' => $request['email_edt'],
            'adm' => $request['tipo_edt'] == 'adm' ? 1 : 0,
            'func' => $request['tipo_edt'] == 'func' ? 1 : 0
        ]);
        return redirect()->back()->with('msg', 'Usuário atualizado com sucesso!');
    }

}

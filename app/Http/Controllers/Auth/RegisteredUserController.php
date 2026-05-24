<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $request->validate([
            'nome'     => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20'],
            'senha'    => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'nome.required'     => 'Informe o nome.',
            'whatsapp.required' => 'Informe o número de WhatsApp.',
            'senha.required'    => 'Informe a senha.',
            'senha.confirmed'   => 'As senhas não conferem.',
        ]);

        $numero = preg_replace('/\D/', '', $request->whatsapp);
        if (strlen($numero) <= 11) {
            $numero = '55' . $numero;
        }

        $user = User::create([
            'name'     => $request->nome,
            'password' => Hash::make($request->senha),
            'whatsapp' => $numero,
            'adm'      => $request->input('tipo') === 'adm' ? 1 : 0,
            'func'     => $request->input('tipo') === 'func' ? 1 : 0,
        ]);

        event(new Registered($user));

        return redirect()->back()->with('msg', 'Usuário adicionado com sucesso');
    }

    public function delete(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|integer|gt:1',
        ]);

        if ($validation->fails()) {
            return redirect()->back()->with('msgErro', 'Falha ao excluir usuário!');
        }

        User::find($request['id'])->update(['excluido' => 1]);
        return redirect()->back()->with('msg', 'Usuario excluido com sucesso!');
    }

    public function editar(Request $request)
    {
        $request->validate([
            'id'           => 'required|integer',
            'nome_edt'     => 'required|string|max:255',
            'whatsapp_edt' => 'nullable|string|max:20',
        ]);

        if ($request->filled('senha_edt')) {
            if ($request['senha_edt'] != $request['senha_confirmation_edt']) {
                return redirect()->back()->with('msgErro', 'Senhas não conferem!');
            }
            User::find($request['id'])->update([
                'password' => Hash::make($request['senha_edt']),
            ]);
        }

        $updateData = [
            'name' => $request['nome_edt'],
            'adm'  => $request['tipo_edt'] == 'adm' ? 1 : 0,
            'func' => $request['tipo_edt'] == 'func' ? 1 : 0,
        ];

        if ($request->filled('whatsapp_edt')) {
            $numero = preg_replace('/\D/', '', $request['whatsapp_edt']);
            if (strlen($numero) <= 11) {
                $numero = '55' . $numero;
            }
            $updateData['whatsapp'] = $numero;
        }

        User::find($request['id'])->update($updateData);
        return redirect()->back()->with('msg', 'Usuario atualizado com sucesso!');
    }

    public function search(Request $request)
    {
        abort_unless(auth()->user()->adm || auth()->user()->func, 403);

        $q      = trim($request->q);
        $campos = ['id', 'name', 'whatsapp', 'adm', 'func'];

        if ($q === '') {
            $users = User::select($campos)->where('excluido', 0)->orderBy('name')->limit(50)->get();
        } else {
            $users = User::select($campos)
                ->where('excluido', 0)
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('whatsapp', 'like', "%{$q}%");
                })
                ->orderBy('name')
                ->get();
        }

        return response()->json($users);
    }
}

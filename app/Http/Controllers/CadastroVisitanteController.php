<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CadastroVisitanteController extends Controller
{
    /**
     * Rota externa (sem auth): visitante se cadastra sozinho.
     */
    public function criar()
    {
        return view('agendafacil.cadastro');
    }

    public function salvar(Request $request)
    {
        // E-mail opcional: vazio vira null (coluna nullable; unique ignora null).
        $request->merge(['email' => filled($request->input('email')) ? trim((string) $request->input('email')) : null]);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'telefone' => ['required', 'string', 'max:30'],
        ], [
            'name.required' => 'Informe seu nome.',
            'email.email' => 'Informe um e-mail válido ou deixe em branco.',
            'email.unique' => 'Este e-mail jǭ estǭ cadastrado. Use a tela de acesso.',
            'telefone.required' => 'Informe seu telefone (usado no acesso por 4 d��gitos).',
        ]);

        User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'telefone' => $this->telefoneComDdi($dados['telefone']),
            'password' => Hash::make(Str::random(16)),
            'role' => 'user',
            'active' => true,
        ]);

        return redirect()
            ->route('agendafacil.acesso')
            ->with('sucesso', 'Cadastro realizado! Agora digite os 4 últimos dígitos do seu telefone para continuar.');
    }

    /**
     * Normaliza o telefone com o DDI +55 fixo do formulário.
     */
    protected function telefoneComDdi(string $telefone): string
    {
        $digitos = preg_replace('/\D/', '', $telefone) ?? '';

        if (str_starts_with($digitos, '55') && strlen($digitos) > 11) {
            return '+55 ' . substr($digitos, 2);
        }

        return '+55 ' . $digitos;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use Illuminate\Http\Request;

class ConfiguracaoController extends Controller
{
    /**
     * Área de configuração dos disparos: horário padrão ou envio na hora.
     */
    public function index()
    {
        return view('admin.configuracoes', [
            'modo' => Configuracao::modoDisparo(),
            'horario' => Configuracao::horarioDisparo(),
            'modoFluxo' => Configuracao::modoFluxoAgendamento(),
        ]);
    }

    public function salvar(Request $request)
    {
        $dados = $request->validate([
            'disparo_modo' => ['required', 'in:agendado,imediato'],
            'disparo_horario' => ['required', 'date_format:H:i'],
            'agendamento_modo' => ['required', 'in:producao,teste'],
        ]);

        Configuracao::set(Configuracao::DISPARO_MODO, $dados['disparo_modo']);
        Configuracao::set(Configuracao::DISPARO_HORARIO, $dados['disparo_horario']);
        Configuracao::set(Configuracao::AGENDAMENTO_MODO, $dados['agendamento_modo']);

        return back()->with('sucesso', 'Configurações de disparo salvas!');
    }
}

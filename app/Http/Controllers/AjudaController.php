<?php

namespace App\Http\Controllers;

class AjudaController extends Controller
{
    /**
     * Página pública (guest): passo a passo para criar e configurar
     * o bot do Telegram, grupos e vínculo com o sistema.
     */
    public function botTelegram()
    {
        return view('ajuda.bot-telegram');
    }
}

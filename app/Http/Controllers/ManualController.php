<?php

namespace App\Http\Controllers;

/**
 * Manual do sistema (admin): página única com o funcionamento completo —
 * fluxo do visitante, telas administrativas, papéis, grupos, disparos
 * automáticos e solução de problemas.
 */
class ManualController extends Controller
{
    public function index()
    {
        return view('admin.manual');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class HashTestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($password)
    {
        //
        $hash = Hash::make($password);

        // Verificar se o hash corresponde à senha
        if (Hash::check($password, $hash)) {
            return response()->json([
                'status' => 'Senha verificada com sucesso!',
                'password' => $password,
                'hash' => $hash
            ]);
        } else {
            return response()->json(['status' => 'Falha na verificação da senha']);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

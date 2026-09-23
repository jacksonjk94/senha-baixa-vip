<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $primaryKey = 'chave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['chave', 'valor'];

    public const DISPARO_MODO = 'disparo_modo'; // agendado|imediato

    public const DISPARO_HORARIO = 'disparo_horario'; // H:i

    /**
     * Lê uma configuração (com defaults sensatos).
     */
    public static function get(string $chave, ?string $padrao = null): ?string
    {
        return Cache::rememberForever("configuracao.{$chave}", function () use ($chave, $padrao) {
            return static::query()->where('chave', $chave)->value('valor') ?? $padrao;
        });
    }

    /**
     * Grava uma configuração e invalida o cache.
     */
    public static function set(string $chave, ?string $valor): void
    {
        static::query()->updateOrCreate(['chave' => $chave], ['valor' => $valor]);
        Cache::forget("configuracao.{$chave}");
    }

    public static function modoDisparo(): string
    {
        return static::get(static::DISPARO_MODO, 'agendado') === 'imediato' ? 'imediato' : 'agendado';
    }

    public static function horarioDisparo(): string
    {
        $valor = (string) static::get(static::DISPARO_HORARIO, '08:00');

        return preg_match('/^\d{2}:\d{2}$/', $valor) ? $valor : '08:00';
    }
}

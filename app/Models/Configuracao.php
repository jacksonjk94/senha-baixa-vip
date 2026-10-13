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

    public const AGENDAMENTO_MODO = 'agendamento_modo'; // producao|teste

    public const DISPARO_LATENCIA_ATIVO = 'disparo_latencia_ativo'; // 1|0

    public const DISPARO_LATENCIA_MS = 'disparo_latencia_ms'; // ms medidos

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

    public static function modoFluxoAgendamento(): string
    {
        return static::get(static::AGENDAMENTO_MODO, 'producao') === 'teste' ? 'teste' : 'producao';
    }

    public static function horarioDisparo(): string
    {
        $valor = (string) static::get(static::DISPARO_HORARIO, '08:00');

        return preg_match('/^\d{2}:\d{2}$/', $valor) ? $valor : '08:00';
    }

    /**
     * Disparos com latência/antecedência: habilitado por padrão.
     */
    public static function latenciaAtiva(): bool
    {
        return static::get(static::DISPARO_LATENCIA_ATIVO, '1') !== '0';
    }

    /**
     * Latência medida do disparo (ms). Limites 0–10000.
     */
    public static function latenciaMs(): int
    {
        return max(0, min(10000, (int) static::get(static::DISPARO_LATENCIA_MS, '250')));
    }
}

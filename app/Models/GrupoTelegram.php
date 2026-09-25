<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrupoTelegram extends Model
{
    use HasFactory;

    protected $table = 'grupo_telegrams';

    public const MODO_ABERTO = 'aberto';
    public const MODO_FECHADO = 'fechado';

    protected $fillable = [
        'nome',
        'chat_id',
        'tipo',
        'modo',
        'horario_abertura',
        'horario_fechamento',
        'horario_disparo',
        'dias_semana',
        'usuario_id',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'horario_abertura' => 'datetime:H:i',
        'horario_fechamento' => 'datetime:H:i',
        'horario_disparo' => 'datetime:H:i',
    ];

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('ordem')->orderBy('nome');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function ehFechado(): bool
    {
        return $this->modo === self::MODO_FECHADO;
    }

    /**
     * Etiqueta exibida ao lado do nome: [Aberto] ou [Fechado].
     */
    public function etiquetaModo(): string
    {
        return $this->ehFechado() ? '[Fechado]' : '[Aberto]';
    }

    /**
     * Dias de funcionamento (ISO 1=seg … 7=dom). Vazio = todos os dias.
     *
     * @return int[]
     */
    public function diasFuncionamento(): array
    {
        if (! filled($this->dias_semana)) {
            return [];
        }

        return collect(explode(',', (string) $this->dias_semana))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d >= 1 && $d <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function funcionaEm(Carbon $data): bool
    {
        $dias = $this->diasFuncionamento();

        return $dias === [] || in_array($data->dayOfWeekIso, $dias, true);
    }

    /**
     * Próximo dia válido de funcionamento a partir de uma base (inclusive).
     */
    public function proximoDiaValido(Carbon $base): Carbon
    {
        $dia = $base->copy()->startOfDay();

        for ($i = 0; $i < 14; $i++) {
            if ($this->funcionaEm($dia)) {
                return $dia;
            }

            $dia->addDay();
        }

        return $dia;
    }

    public function aberturaEm(Carbon $dia): ?Carbon
    {
        return $this->horario_abertura
            ? $dia->copy()->setTimeFromTimeString($this->horario_abertura->format('H:i'))
            : null;
    }

    public function fechamentoEm(Carbon $dia): ?Carbon
    {
        return $this->horario_fechamento
            ? $dia->copy()->setTimeFromTimeString($this->horario_fechamento->format('H:i'))
            : null;
    }

    public function disparoEm(Carbon $dia): ?Carbon
    {
        return $this->horario_disparo
            ? $dia->copy()->setTimeFromTimeString($this->horario_disparo->format('H:i'))
            : null;
    }
}

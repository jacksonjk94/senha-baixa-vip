<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    use HasFactory;

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_ENVIADO = 'enviado';
    public const STATUS_ERRO = 'erro';
    public const STATUS_CANCELADO = 'cancelado';

    public const DESTINO_GRUPO = 'grupo';
    public const DESTINO_PESSOA = 'pessoa';

    protected $fillable = [
        'user_id',
        'grupo_telegram_id',
        'nome_visitante',
        'dados_senha',
        'senha_codigo',
        'texto',
        'posicao',
        'total_grupo_horario',
        'destino_tipo',
        'destino_id',
        'destino_nome',
        'data_hora_envio',
        'status',
        'mensagem_erro',
        'enviado_em',
        'ip_origem',
    ];

    protected $casts = [
        'data_hora_envio' => 'datetime',
        'enviado_em' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoTelegram::class, 'grupo_telegram_id');
    }

    public function scopePendentes($query)
    {
        return $query->where('status', self::STATUS_PENDENTE);
    }

    public function scopeVencidos($query)
    {
        return $query->where('data_hora_envio', '<=', now());
    }

    public function scopeNaoCancelados($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELADO);
    }

    /**
     * SenhaBaixa v2: posição = ordem de envio no mesmo grupo + mesmo minuto
     * (cancelados não contam).
     */
    public static function proximaPosicao(int $grupoId, \DateTimeInterface $dataHora, ?int $excetoId = null): int
    {
        $inicio = \Carbon\Carbon::parse($dataHora)->startOfMinute();
        $fim = (clone $inicio)->endOfMinute();

        return static::query()
            ->where('grupo_telegram_id', $grupoId)
            ->whereBetween('data_hora_envio', [$inicio, $fim])
            ->naoCancelados()
            ->when($excetoId, fn ($q) => $q->where('id', '!=', $excetoId))
            ->count() + 1;
    }

    public static function totalGrupoHorario(int $grupoId, \DateTimeInterface $dataHora, ?int $excetoId = null): int
    {
        $inicio = \Carbon\Carbon::parse($dataHora)->startOfMinute();
        $fim = (clone $inicio)->endOfMinute();

        return static::query()
            ->where('grupo_telegram_id', $grupoId)
            ->whereBetween('data_hora_envio', [$inicio, $fim])
            ->naoCancelados()
            ->when($excetoId, fn ($q) => $q->where('id', '!=', $excetoId))
            ->count();
    }

    /**
     * Item 4/5 — Grupo FECHADO: posição = ordem de confirmação dentro da
     * rodada (mesmo grupo, mesmo dia). Cancelados não contam.
     */
    public static function proximaPosicaoRodada(int $grupoId, \DateTimeInterface $data, ?int $excetoId = null): int
    {
        return static::totalRodada($grupoId, $data, $excetoId) + 1;
    }

    public static function totalRodada(int $grupoId, \DateTimeInterface $data, ?int $excetoId = null): int
    {
        return static::query()
            ->where('grupo_telegram_id', $grupoId)
            ->whereDate('data_hora_envio', \Carbon\Carbon::parse($data)->toDateString())
            ->naoCancelados()
            ->when($excetoId, fn ($q) => $q->where('id', '!=', $excetoId))
            ->count();
    }

    /**
     * Item 5.6 — Recalcula posições após cancelamento, na ordem de
     * confirmação. Escopo: rodada (fechado) ou minuto (aberto).
     */
    public static function recalcularPosicoes(int $grupoId, \DateTimeInterface $dataHora, string $modo): void
    {
        $query = static::query()
            ->where('grupo_telegram_id', $grupoId)
            ->naoCancelados();

        if ($modo === GrupoTelegram::MODO_FECHADO) {
            $query->whereDate('data_hora_envio', \Carbon\Carbon::parse($dataHora)->toDateString());
        } else {
            $inicio = \Carbon\Carbon::parse($dataHora)->startOfMinute();
            $fim = (clone $inicio)->endOfMinute();
            $query->whereBetween('data_hora_envio', [$inicio, $fim]);
        }

        $agendamentos = $query->orderBy('created_at')->orderBy('id')->get();
        $total = $agendamentos->count();

        foreach ($agendamentos->values() as $i => $agendamento) {
            $agendamento->update([
                'posicao' => $i + 1,
                'total_grupo_horario' => $total,
            ]);
        }
    }

    /**
     * Texto de exibição da posição: "5ª de 12".
     */
    public function posicaoTexto(): string
    {
        $total = $this->total_grupo_horario ?? $this->posicao ?? 1;

        return "{$this->posicao}ª de {$total}";
    }

    public function marcarComoCancelado(): void
    {
        $this->update(['status' => self::STATUS_CANCELADO]);
    }

    public function marcarComoEnviado(): void
    {
        $this->update([
            'status' => self::STATUS_ENVIADO,
            'mensagem_erro' => null,
            'enviado_em' => now(),
        ]);
    }

    public function marcarComoErro(string $mensagem): void
    {
        $this->update([
            'status' => self::STATUS_ERRO,
            'mensagem_erro' => mb_substr($mensagem, 0, 1000),
        ]);
    }

    /**
     * Monta o texto da mensagem.
     * SenhaBaixa v2: texto livre digitado vai exatamente como aparece no grupo.
     * Fluxo legado: formato "SENHA AUTORIZADA…".
     */
    public function textoMensagem(): string
    {
        if (filled($this->texto)) {
            return (string) $this->texto;
        }

        $quando = $this->data_hora_envio?->format('d/m/Y H:i') ?? '-';

        return "✅ SENHA AUTORIZADA\n"
            . "Visitante: {$this->nome_visitante}\n"
            . "Ala/Unidade: {$this->destino_nome}\n"
            . "Horário: {$quando}\n"
            . "Senha: {$this->senha_codigo}";
    }
}

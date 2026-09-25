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
    public static function proximaPosicao(int $grupoId, \DateTimeInterface $dataHora): int
    {
        $inicio = \Carbon\Carbon::parse($dataHora)->startOfMinute();
        $fim = (clone $inicio)->endOfMinute();

        return static::query()
            ->where('grupo_telegram_id', $grupoId)
            ->whereBetween('data_hora_envio', [$inicio, $fim])
            ->naoCancelados()
            ->count() + 1;
    }

    public static function totalGrupoHorario(int $grupoId, \DateTimeInterface $dataHora): int
    {
        $inicio = \Carbon\Carbon::parse($dataHora)->startOfMinute();
        $fim = (clone $inicio)->endOfMinute();

        return static::query()
            ->where('grupo_telegram_id', $grupoId)
            ->whereBetween('data_hora_envio', [$inicio, $fim])
            ->naoCancelados()
            ->count();
    }

    /**
     * Próximo número da senha (sufixo "xNÚMERO" do texto, ex: "Nome x40").
     * Sequência por grupo (cancelados fora); sem grupo, sequência geral.
     */
    public static function proximaSenhaNumero(?int $grupoId = null): int
    {
        $textos = static::query()
            ->whereNotNull('texto')
            ->when($grupoId, fn ($q) => $q->where('grupo_telegram_id', $grupoId))
            ->naoCancelados()
            ->pluck('texto');

        $max = 0;

        foreach ($textos as $texto) {
            if (preg_match('/x\s*(\d+)\s*$/i', trim((string) $texto), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
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

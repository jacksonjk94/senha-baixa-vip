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

    public const DESTINO_GRUPO = 'grupo';
    public const DESTINO_PESSOA = 'pessoa';

    protected $fillable = [
        'user_id',
        'grupo_telegram_id',
        'nome_visitante',
        'dados_senha',
        'senha_codigo',
        'destino_tipo',
        'destino_id',
        'destino_nome',
        'data_hora_envio',
        'status',
        'mensagem_erro',
        'enviado_em',
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
     * Monta o texto padrão da senha autorizada.
     */
    public function textoMensagem(): string
    {
        $quando = $this->data_hora_envio?->format('d/m/Y H:i') ?? '-';

        return "✅ SENHA AUTORIZADA\n"
            . "Visitante: {$this->nome_visitante}\n"
            . "Ala/Unidade: {$this->destino_nome}\n"
            . "Horário: {$quando}\n"
            . "Senha: {$this->senha_codigo}";
    }
}

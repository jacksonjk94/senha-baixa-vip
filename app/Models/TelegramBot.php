<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramBot extends Model
{
    protected $fillable = [
        'name',
        'username',
        'token',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Bot padrão ativo (para fluxo, disparos e widget).
     */
    public static function defaultBot(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Normaliza username sem "@" inicial.
     */
    public function setUsernameAttribute(?string $value): void
    {
        $this->attributes['username'] = ltrim(trim((string) $value), '@');
    }

    /**
     * Define este como padrão (único), somente se ativo.
     */
    public function markAsDefault(): void
    {
        static::query()->where('is_default', true)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }
}

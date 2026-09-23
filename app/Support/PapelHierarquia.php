<?php

namespace App\Support;

use App\Models\User;

/**
 * Hierarquia administrativa (nível menor = mais poder).
 *
 * 1. Usuários de sistema: root, system, master, super-admin, admin.
 * 2. Usuários cadastrados (visitantes): user.
 */
class PapelHierarquia
{
    public const ORDEM = [
        'root' => 0,
        'system' => 1,
        'master' => 2,
        'super-admin' => 3,
        'admin' => 4,
        'user' => 5,
        'guest' => 6,
    ];

    public const SISTEMA = ['root', 'system', 'master', 'super-admin', 'admin'];

    public const VISITANTES = ['user'];

    public static function nivel(?string $role): int
    {
        return self::ORDEM[$role] ?? 99;
    }

    public static function ehSistema(?string $role): bool
    {
        return in_array($role, self::SISTEMA, true);
    }

    public static function ehVisitante(?string $role): bool
    {
        return in_array($role, self::VISITANTES, true);
    }

    /**
     * Papéis que o ator pode VER na tela de Usuários:
     * abaixo do seu + pares (mesmo nível) se can_manage_admins.
     *
     * @return string[]
     */
    public static function visiveisPara(User $ator): array
    {
        $meuNivel = self::nivel($ator->role);
        $visiveis = [];

        foreach (self::SISTEMA as $role) {
            if (self::nivel($role) > $meuNivel) {
                $visiveis[] = $role;
            }
        }

        if ($ator->can_manage_admins && self::ehSistema($ator->role)) {
            $visiveis[] = $ator->role;
        }

        return array_values(array_unique($visiveis));
    }

    /**
     * Papéis que o ator pode ATRIBUIR ao criar/editar:
     * abaixo do seu + próprio nível se can_manage_admins.
     *
     * @return string[]
     */
    public static function atribuiveisPara(User $ator): array
    {
        return self::visiveisPara($ator);
    }

    /**
     * Pode gerenciar (editar/excluir) o alvo?
     * Abaixo: sempre. Mesmo nível: só com can_manage_admins. Acima/nulo: nunca.
     * A si mesmo: nunca por aqui (evita auto-bloqueio).
     */
    public static function podeGerenciar(User $ator, User $alvo): bool
    {
        if ($ator->is($alvo)) {
            return false;
        }

        $nivelAtor = self::nivel($ator->role);
        $nivelAlvo = self::nivel($alvo->role);

        if ($nivelAlvo > $nivelAtor) {
            return true;
        }

        return $nivelAlvo === $nivelAtor && (bool) $ator->can_manage_admins;
    }
}

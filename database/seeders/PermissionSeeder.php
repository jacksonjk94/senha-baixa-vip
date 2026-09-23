<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Guards que serão usados
        $guards = [
            'web',
            // 'sanctum',
        ];

        // ✅ Lista de permissões
        $permissions = [
            '*', // Controle total do sistema
            'manage users',
            'manage system data',
            'edit all users',
            'edit selected users',
            'edit system data',
            'manage own profile',
            'edit own profile',
            'delete own profile',
            'respond own profile',

            // Negócio
            'manage own clients',
            'create lpco',
            'view lpco',
            'edit lpco',
            'view import process',
        ];

        // 🔄 Criação de permissões para cada guard
        foreach ($guards as $guard) {
            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => $guard]
                );
            }
        }

        // ✅ Definição de roles e suas permissões
        $rolesWithPermissions = [
            'root' => ['*','manage users','manage system data'],
            'system' => ['*','manage users','manage system data'],
            'master' => ['*', 'manage users', 'manage system data'],
            'super-admin' => ['*', 'manage users', 'manage system data'],
            'admin' => ['manage users', 'manage system data'],
            'user' => ['manage own profile', 'edit system data'],
            'guest' => [],
        ];

        // 🔄 Criação de roles para cada guard
        foreach ($guards as $guard) {
            foreach ($rolesWithPermissions as $roleName => $rolePermissions) {
                $role = Role::firstOrCreate(
                    ['name' => $roleName, 'guard_name' => $guard]
                );

                // Sincroniza permissões para o role atual
                $role->syncPermissions($rolePermissions);
            }
        }

        $this->command->info("✅ Permissões e roles criados para os guards 'web' e 'sanctum'!");
    }
}

/**
 * PHP ARTISAN DB:SEED
 * php artisan db:seed --class=PermissionsSeeder
**/


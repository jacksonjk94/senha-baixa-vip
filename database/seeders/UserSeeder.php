<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create "System" User with all permissions and roles
        $userSystem = User::firstOrCreate(
            ['email' => 'system@system.com'],
            [
                'active' => true,
                'name' => 'System',
                'username' => 'system',
                'password' => Hash::make('system'),
                'role' => 'system',
                'can_manage_admins' => true,
                'system_theme' => 'dark',
            ]
        );

        // Create "Admin" User with all permissions and roles
        $userAdmin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'active' => true,
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
                'can_manage_admins' => true,
                'system_theme' => 'light',
            ]
        );

        // Visitante de teste — telefone +55 41 98843 2148 (últimos 4: 2148)
        // Garante que o fluxo "Acesso por 4 dígitos" encontre o registro.
        User::firstOrCreate(
            ['email' => 'jackson.visitante@teste.com'],
            [
                'active' => true,
                'name' => 'Jackson',
                'username' => 'jacksonjk94',
                'password' => Hash::make('password'),
                'role' => 'user',
                'system_theme' => 'light',
                'telefone' => '+55 41 98843 2148',
                'ultimos_digitos_telefone' => '2148',
                'telegram_username' => 'jacksonjk94',
            ]
        );
    }
}

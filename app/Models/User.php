<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Jetstream\HasTeams;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'active',
        'name',
        'tax_id',
        'email',
        'username',
        'password',
        'role',
        'can_manage_admins',
        'system_theme',
        'telegram_id',
        'telegram_username',
        'telegram_token',
        'ultimos_digitos_telefone',
        'telefone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'can_manage_admins' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Gerar um código para uri padrão "3ec67525-f2db-458d-9c75-e21c383248a3" ao ser criado novo registro
            $model->uuid = (string) Str::uuid();
        });

        static::saving(function ($user) {
            // Mantém ultimos_digitos_telefone sincronizado com o telefone.
            // Cobre cadastros onde só o telefone foi informado (ex.: "+55 41 98843-2148").
            $soNumeros = preg_replace('/\D/', '', (string) $user->telefone);
            if (filled($soNumeros) && strlen($soNumeros) >= 4) {
                $sufixo = substr($soNumeros, -4);
                if ($user->isDirty('telefone') || empty($user->ultimos_digitos_telefone)) {
                    $user->ultimos_digitos_telefone = $sufixo;
                }
            }

            // Se o role foi alterado ou está sendo criado, atualiza as permissões
            if ($user->isDirty('role') || !$user->exists) {
                $user->updateRoleAndPermissions();
            }
        });
    }

    // Definir o formato do UUID
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // Método para atualizar as roles e permissões do usuário
    public function updateRoleAndPermissions()
    {
        // Remover todas as roles e permissões antes de definir novamente
        $this->syncRoles([]); // Remove todas as roles
        $this->syncPermissions([]); // Remove todas as permissões

        // Atribuir a nova role
        if ($this->role) {
            $this->assignRole($this->role);

            // Buscar e adicionar as permissões associadas ao role
            $role = Role::where('name', $this->role)->first();
            if ($role) {
                $this->syncPermissions($role->permissions);
            }
        }
    }

    /**
     * Relationship with the models.
     */
    public function agendamentos()
    {
        return $this->hasMany(\App\Models\Agendamento::class);
    }

    public function gruposTelegram()
    {
        return $this->hasMany(\App\Models\GrupoTelegram::class, 'usuario_id');
    }

    public function telegramConectado(): bool
    {
        return filled($this->telegram_id);
    }

}

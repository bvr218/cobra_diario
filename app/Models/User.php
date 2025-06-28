<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'oficina_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    public function clientesRegistrados(): HasMany
    {
        return $this->hasMany(Cliente::class, 'registrado_por');
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class, 'agente_asignado');
    }



    public function abonos(): HasMany
    {
        return $this->hasMany(Abono::class, 'registrado_por_id');
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oficina_id');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'user_id');
    }

    public function dineroBase(): HasOne
    {
        return $this->hasOne(DineroBase::class);
    }

    public function historialMovimientos(): HasMany
    {
        return $this->hasMany(HistorialMovimiento::class);
    }


    protected static function booted()
    {
        static::created(function ($user) {
            // Solo lo crea si aún no existe
            if (!$user->dineroBase) {
                $user->dineroBase()->create([
                    'monto' => 0,
                    'monto_general' => 0,
                ]);
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return !is_null(request()->user());
    }

}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
//use Hexters\HexaLite\HexaLiteRolePermission;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles;
    use HasFactory, Notifiable;
  //  use HexaLiteRolePermission;
    /** @use HasFactory<UserFactory> */

    protected $guard_name = 'admin'; // важно для spatie/laravel-permission
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'position_id',
        'admin_start_page',
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
    public function position()
    {
        return $this->belongsTo(Position::class);
    }
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        // Allow any user that has at least one role (guard: admin)
        return $this->roles()->where('guard_name', 'admin')->exists();
    }
}

<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class GudangUser extends Authenticatable implements FilamentUser, HasName
{
    use Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_PABRIK = 'pabrik';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'akses_keuangan',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'akses_keuangan' => 'boolean',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // Semua akun di tabel gudang_users otomatis boleh akses panel 'gudang'
        return $panel->getId() === 'gudang';
    }

    public function isPabrik(): bool
    {
        return $this->role === self::ROLE_PABRIK;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function bisaAksesKeuangan(): bool
    {
        return (bool) $this->akses_keuangan;
    }

    /**
     * Nama yang tampil di pojok kanan atas panel. Ditambahin label
     * role biar jelas ini akun admin atau pabrik yang lagi login.
     */
    public function getFilamentName(): string
    {
        $labelRole = $this->isPabrik() ? 'Pabrik' : 'Admin';

        return "{$this->name} ({$labelRole})";
    }
}
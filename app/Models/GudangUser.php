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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
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

    /**
     * Menu Keuangan (Biaya Operasional, Penjualan, Harga Acuan,
     * Pembelian/Invoice, Laporan Laba Rugi) HANYA untuk satu akun
     * Umma (admin sistem), dicek lewat email di config/gudang.php --
     * BUKAN lewat kolom 'role', karena akun gudang_users lain juga
     * bisa saja punya role 'admin' tapi tidak boleh akses Keuangan.
     */
    public function canAksesKeuangan(): bool
    {
        return strcasecmp(
            (string) $this->email,
            (string) config('gudang.email_akses_keuangan')
        ) === 0;
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
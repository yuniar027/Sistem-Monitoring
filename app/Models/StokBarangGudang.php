<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StokBarangGudang extends Model
{
    use HasFactory;

    protected $table = 'stok_barang_gudang';

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'kategori',
        'stok_aman',
    ];

    protected $casts = [
        'stok_aman' => 'decimal:2',
    ];

    public const KATEGORI_AWAN = 'awan';
    public const KATEGORI_ORIGAMI = 'origami';

    protected static function booted(): void
    {
        static::creating(function (self $barang) {
            // Auto-generate kode_barang HANYA untuk kategori Awan.
            // Origami sudah punya kode dari pabrik, jadi harus diisi manual.
            if ($barang->kategori === self::KATEGORI_AWAN && empty($barang->kode_barang)) {
                $barang->kode_barang = static::generateKodeBarang($barang->nama_barang);
            }
        });
    }

    /**
     * Akhiran yang dianggap varian ukuran dari barang dasar yang sama,
     * contoh: "KOALA COKLAT BT", "KOALA COKLAT PD", "KOALA COKLAT PJ"
     * semuanya punya nama dasar "KOALA COKLAT".
     */
    public const AKHIRAN_VARIAN = ['BT', 'PD', 'PJ'];

    /**
     * Ambil nama dasar barang (tanpa akhiran BT/PD/PJ), dipakai untuk
     * mengelompokkan barang yang sebenarnya satu jenis tapi beda ukuran.
     */
    public static function hitungNamaDasar(string $namaBarang): string
    {
        $kata = preg_split('/\s+/', trim($namaBarang));
        $akhiran = Str::upper(end($kata));

        if (in_array($akhiran, self::AKHIRAN_VARIAN, true) && count($kata) > 1) {
            array_pop($kata);

            return implode(' ', $kata);
        }

        return trim($namaBarang);
    }

    /**
     * Akhiran varian barang ini (BT/PD/PJ), atau null kalau bukan
     * bagian dari grup varian.
     */
    public function getAkhiranVarianAttribute(): ?string
    {
        $kata = preg_split('/\s+/', trim($this->nama_barang));
        $akhiran = Str::upper(end($kata));

        return in_array($akhiran, self::AKHIRAN_VARIAN, true) ? $akhiran : null;
    }

    public function getNamaDasarAttribute(): string
    {
        return static::hitungNamaDasar($this->nama_barang);
    }

    /**
     * Generate kode dari inisial tiap kata nama_barang, contoh:
     * "SET TUPAI BT" -> "STB-001". Kalau sudah ada yang sama,
     * nomor urut naik otomatis sampai ketemu yang unik.
     */
    public static function generateKodeBarang(string $namaBarang): string
    {
        $inisial = collect(preg_split('/\s+/', trim($namaBarang)))
            ->filter()
            ->map(fn ($kata) => Str::upper(Str::substr($kata, 0, 1)))
            ->take(4)
            ->implode('');

        $inisial = $inisial ?: 'BRG';

        $nomor = 1;
        do {
            $kode = sprintf('%s-%03d', $inisial, $nomor);
            $sudahAda = static::where('kode_barang', $kode)->exists();
            $nomor++;
        } while ($sudahAda);

        return $kode;
    }

    public function variasi(): HasMany
    {
        return $this->hasMany(StokVariasiGudang::class, 'barang_gudang_id');
    }

    public function harian(): HasMany
    {
        return $this->hasMany(StokHarianGudang::class, 'barang_gudang_id');
    }

    public function alokasiKhusus(): HasMany
    {
        return $this->hasMany(StokAlokasiKhususHarian::class, 'barang_gudang_id');
    }

    /**
     * Ambil snapshot harian untuk tanggal tertentu (default: hari ini).
     * Return null kalau belum di-generate oleh stok:generate-harian.
     */
    public function harianPadaTanggal(?string $tanggal = null): ?StokHarianGudang
    {
        $tanggal = $tanggal ? Carbon::parse($tanggal) : today();

        return $this->harian()->whereDate('tanggal', $tanggal)->first();
    }
}
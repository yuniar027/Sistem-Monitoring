<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\StokBarangGudang;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_variasi_gudang', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('barang_gudang_id');
            $table->string('nama_dasar')->nullable()->after('kategori');
        });

        // Backfill pakai accessor asli StokBarangGudang::hitungNamaDasar(),
        // supaya identik dengan yang dipakai grouping di StokVariasiHarianResource.
        DB::table('stok_variasi_gudang')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $barang = StokBarangGudang::find($row->barang_gudang_id);
                if (!$barang) continue;
                DB::table('stok_variasi_gudang')->where('id', $row->id)->update([
                    'kategori'   => $barang->kategori,
                    'nama_dasar' => $barang->nama_dasar,
                ]);
            }
        });

        Schema::table('stok_variasi_gudang', function (Blueprint $table) {
            // nama constraint default Laravel/Postgres dari migration awal
            $table->dropUnique('stok_variasi_gudang_barang_gudang_id_kode_variasi_unique');
            $table->unique(['kategori', 'nama_dasar', 'kode_variasi'], 'stok_variasi_gudang_kategori_nama_dasar_kode_unique');
            $table->foreignId('barang_gudang_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stok_variasi_gudang', function (Blueprint $table) {
            $table->dropUnique('stok_variasi_gudang_kategori_nama_dasar_kode_unique');
            $table->unique(['barang_gudang_id', 'kode_variasi'], 'stok_variasi_gudang_barang_gudang_id_kode_variasi_unique');
            $table->dropColumn(['kategori', 'nama_dasar']);
        });
    }
};
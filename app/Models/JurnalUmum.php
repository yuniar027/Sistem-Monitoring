<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JurnalUmum extends Model
{
    use HasFactory;

    protected $table = 'jurnal_umum';

    protected $fillable = [
        'tanggal', 'kode_akun', 'keterangan', 'debit', 'kredit', 'sumber_tipe', 'sumber_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
    ];

    public function sumber()
    {
        return $this->morphTo(__FUNCTION__, 'sumber_tipe', 'sumber_id');
    }
}

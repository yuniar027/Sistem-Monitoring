<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrutanKedatangan extends Model
{
    protected $table = 'urutan_kedatangan';

    protected $fillable = ['tanggal', 'packer_id', 'urutan'];

    protected $casts = ['tanggal' => 'date'];

    public function packer()
    {
        return $this->belongsTo(Packer::class, 'packer_id');
    }
}
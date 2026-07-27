<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Packer extends Model
{
    protected $table = 'packers';

    protected $fillable = ['nama', 'status'];

    public function tugasPacking()
    {
        return $this->hasMany(TugasPacking::class, 'ditugaskan_ke');
    }
}

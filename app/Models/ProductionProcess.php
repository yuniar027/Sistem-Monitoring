<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionProcess extends Model
{
    protected $table = 'production_processes';

    protected $fillable = [
        'kode_proses',
        'nama_proses',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(ProductionProcessTarget::class);
    }
}

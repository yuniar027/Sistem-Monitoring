<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use HasFactory;

    protected $table = 'error_log';

    public $timestamps = false;

    protected $fillable = [
        'source', 'payload', 'error_message', 'resolved', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'resolved' => 'boolean',
        'created_at' => 'datetime',
    ];
}

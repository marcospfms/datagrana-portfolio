<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'key',
        'url_base',
        'status',
        'request_counter',
        'request_limit',
        'type_limit',
        'plan',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'request_counter' => 'integer',
            'request_limit' => 'integer',
        ];
    }
}

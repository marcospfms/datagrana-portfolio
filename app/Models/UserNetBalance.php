<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNetBalance extends Model
{
    use HasFactory;

    protected $table = 'user_net_balance';

    protected $fillable = [
        'user_id',
        'consolidated_id',
        'date',
        'net_balance',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'net_balance' => 'decimal:8',
        ];
    }

    public function consolidated(): BelongsTo
    {
        return $this->belongsTo(Consolidated::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

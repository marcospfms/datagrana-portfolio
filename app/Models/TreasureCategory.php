<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreasureCategory extends Model
{
    use HasFactory;

    protected $table = 'treasure_categories';

    protected $fillable = [
        'coin_id',
        'name',
        'short_name',
        'reference',
        'list_updated_at',
        'can_set_net_balance',
        'color_hex',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'list_updated_at' => 'datetime',
            'can_set_net_balance' => 'boolean',
        ];
    }

    public function coin(): BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    public function treasures(): HasMany
    {
        return $this->hasMany(Treasure::class);
    }
}

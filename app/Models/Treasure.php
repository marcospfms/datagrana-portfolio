<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treasure extends Model
{
    use HasFactory;

    protected $table = 'treasures';

    protected $fillable = [
        'treasure_category_id',
        'name',
        'expiration_date',
        'status',
        'is_overdue',
        'can_buy',
        'can_sell',
        'code',
        'last_unit_price',
        'last_unit_price_updated',
        'imported_with',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date' => 'datetime',
            'status' => 'boolean',
            'is_overdue' => 'boolean',
            'can_buy' => 'boolean',
            'can_sell' => 'boolean',
            'last_unit_price' => 'decimal:8',
            'last_unit_price_updated' => 'datetime',
        ];
    }

    public function treasureCategory(): BelongsTo
    {
        return $this->belongsTo(TreasureCategory::class);
    }

    public function consolidated(): HasMany
    {
        return $this->hasMany(Consolidated::class);
    }
}

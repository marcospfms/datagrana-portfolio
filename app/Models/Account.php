<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_id',
        'account',
        'nickname',
        'default',
    ];

    protected function casts(): array
    {
        return [
            'default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function consolidated(): HasMany
    {
        return $this->hasMany(Consolidated::class);
    }

    public function hasActivePositions(): bool
    {
        if (!class_exists(Consolidated::class)) {
            return false;
        }

        return $this->consolidated()->where('closed', false)->exists();
    }

    public function scopeDefault($query)
    {
        return $query->where('default', true);
    }
}

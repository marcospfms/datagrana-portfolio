<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyTicker extends Model
{
    use HasFactory;

    protected $table = 'company_tickers';

    protected $fillable = [
        'company_id',
        'code',
        'trade_code',
        'status',
        'can_update',
        'last_price',
        'last_price_updated',
        'last_earnings_updated',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'can_update' => 'boolean',
            'last_price' => 'decimal:8',
            'last_price_updated' => 'datetime',
            'last_earnings_updated' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function consolidated(): HasMany
    {
        return $this->hasMany(Consolidated::class);
    }

    public function compositions(): HasMany
    {
        return $this->hasMany(Composition::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}

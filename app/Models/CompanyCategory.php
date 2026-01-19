<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyCategory extends Model
{
    use HasFactory;

    protected $table = 'company_category';

    protected $fillable = [
        'coin_id',
        'name',
        'short_name',
        'reference',
        'status',
        'color_hex',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function coin(): BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}

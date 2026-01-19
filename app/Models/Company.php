<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_category_id',
        'name',
        'status',
        'cnpj',
        'nickname',
        'photo',
        'segment',
        'sector',
        'subsector',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function companyCategory(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class);
    }

    public function category(): BelongsTo
    {
        return $this->companyCategory();
    }

    public function tickers(): HasMany
    {
        return $this->hasMany(CompanyTicker::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}

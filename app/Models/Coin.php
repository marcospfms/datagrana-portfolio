<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coin extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'currency_symbol',
        'currency_code',
    ];

    public function companyCategories(): HasMany
    {
        return $this->hasMany(CompanyCategory::class);
    }
}

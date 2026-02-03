<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EarningType extends Model
{
    use HasFactory;

    protected $table = 'earning_type';

    protected $fillable = [
        'name',
        'short_name',
        'label',
        'key',
        'icon',
        'hex_color',
    ];

    public function companyEarnings(): HasMany
    {
        return $this->hasMany(CompanyEarning::class);
    }
}

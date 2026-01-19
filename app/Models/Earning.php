<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Earning extends Model
{
    use HasFactory;

    protected $table = 'earnings';

    protected $fillable = [
        'consolidated_id',
        'earning_type_id',
        'company_earning_id',
        'date',
        'quantity',
        'net_value',
        'gross_value',
        'tax',
        'imported_with',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'quantity' => 'decimal:8',
            'net_value' => 'decimal:8',
            'gross_value' => 'decimal:8',
            'tax' => 'decimal:8',
        ];
    }

    public function consolidated(): BelongsTo
    {
        return $this->belongsTo(Consolidated::class);
    }
}

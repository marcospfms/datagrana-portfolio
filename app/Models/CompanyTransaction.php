<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTransaction extends Model
{
    use HasFactory;

    protected $table = 'company_transactions';

    protected $fillable = [
        'consolidated_id',
        'date',
        'operation',
        'quantity',
        'price',
        'total_value',
        'imported_with',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'quantity' => 'decimal:8',
            'price' => 'decimal:8',
            'total_value' => 'decimal:8',
        ];
    }

    public function consolidated(): BelongsTo
    {
        return $this->belongsTo(Consolidated::class);
    }
}

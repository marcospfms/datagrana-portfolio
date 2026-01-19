<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasureTransaction extends Model
{
    use HasFactory;

    protected $table = 'treasure_transaction';

    protected $fillable = [
        'consolidated_id',
        'date',
        'operation',
        'invested_value',
        'quantity',
        'price',
        'imported_with',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'invested_value' => 'decimal:8',
            'quantity' => 'decimal:8',
            'price' => 'decimal:8',
        ];
    }

    public function consolidated(): BelongsTo
    {
        return $this->belongsTo(Consolidated::class);
    }
}

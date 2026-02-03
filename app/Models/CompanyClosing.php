<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyClosing extends Model
{
    use HasFactory;

    protected $table = 'company_closings';

    protected $fillable = [
        'company_ticker_id',
        'date',
        'open',
        'high',
        'low',
        'price',
        'volume',
        'previous_close',
        'splitted',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'open' => 'decimal:8',
            'high' => 'decimal:8',
            'low' => 'decimal:8',
            'price' => 'decimal:8',
            'volume' => 'decimal:8',
            'previous_close' => 'decimal:8',
            'splitted' => 'integer',
        ];
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyEarning extends Model
{
    use HasFactory;

    protected $table = 'company_earnings';

    protected $fillable = [
        'company_ticker_id',
        'earning_type_id',
        'origin',
        'status',
        'value',
        'approved_date',
        'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'value' => 'decimal:8',
            'approved_date' => 'date',
            'payment_date' => 'date',
        ];
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }

    public function earningType(): BelongsTo
    {
        return $this->belongsTo(EarningType::class);
    }
}

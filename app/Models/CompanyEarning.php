<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    public function getIncomeTaxAttribute(): float
    {
        $earningTypeName = $this->earningType?->name;

        if (! $earningTypeName) {
            return 1.0;
        }

        $typeCompanyDividends = [
            'Amortização' => 'AMORT',
            'Rend. Tributado' => 'RENTRIB',
            'Rendimento' => 'REN',
            'Dividendo' => 'DIV',
            'JCP' => 'JCP',
        ];

        $typeIncomeTax = [
            'AMORT' => 1.0,
            'RENTRIB' => 0.85,
            'REN' => 1.0,
            'DIV' => 1.0,
            'JCP' => 0.85,
        ];

        $taxKey = $typeCompanyDividends[$earningTypeName] ?? null;

        if (! $taxKey) {
            return 1.0;
        }

        return (float) ($typeIncomeTax[$taxKey] ?? 1.0);
    }

    public function calculateValues(float $quantity): array
    {
        $grossValue = (float) $this->value * $quantity;
        $incomeTax = $this->income_tax;
        $netValue = $grossValue * $incomeTax;
        $taxValue = $grossValue - $netValue;

        return [
            'gross_value' => $grossValue,
            'net_value' => $netValue,
            'income_tax' => $incomeTax,
            'tax' => $taxValue,
            'quantity' => $quantity,
        ];
    }
}

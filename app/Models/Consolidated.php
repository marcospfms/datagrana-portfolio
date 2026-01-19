<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consolidated extends Model
{
    use HasFactory;

    protected $table = 'consolidated';

    protected $fillable = [
        'account_id',
        'treasure_id',
        'company_ticker_id',
        'average_purchase_price',
        'quantity_current',
        'total_purchased',
        'closed',
        'average_selling_price',
        'total_sold',
        'quantity_purchased',
        'quantity_sold',
    ];

    protected function casts(): array
    {
        return [
            'average_purchase_price' => 'decimal:8',
            'quantity_current' => 'decimal:8',
            'total_purchased' => 'decimal:8',
            'closed' => 'boolean',
            'average_selling_price' => 'decimal:8',
            'total_sold' => 'decimal:8',
            'quantity_purchased' => 'decimal:8',
            'quantity_sold' => 'decimal:8',
        ];
    }

    protected $appends = [
        'balance',
        'net_balance',
        'profit',
        'profit_percentage',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }

    public function treasure(): BelongsTo
    {
        return $this->belongsTo(Treasure::class);
    }

    public function companyTransactions(): HasMany
    {
        return $this->hasMany(CompanyTransaction::class);
    }

    public function treasureTransactions(): HasMany
    {
        return $this->hasMany(TreasureTransaction::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    public function userNetBalances(): HasMany
    {
        return $this->hasMany(UserNetBalance::class);
    }

    protected function balance(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->quantity_current <= 0) {
                    return 0.0;
                }

                $currentPrice = $this->getCurrentPrice();

                return $this->quantity_current * $currentPrice;
            }
        );
    }

    protected function netBalance(): Attribute
    {
        return Attribute::make(
            get: function () {
                $latestNetBalance = $this->userNetBalances()
                    ->latest('date')
                    ->value('net_balance');

                return $latestNetBalance ?? $this->balance;
            }
        );
    }

    protected function profit(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->quantity_current <= 0) {
                    return ($this->total_sold - $this->total_purchased);
                }

                return $this->balance - $this->total_purchased;
            }
        );
    }

    protected function profitPercentage(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalInvested = $this->total_purchased - ($this->total_sold ?? 0);

                if ($totalInvested <= 0) {
                    return 0.0;
                }

                return ($this->profit / $totalInvested) * 100;
            }
        );
    }

    public function scopeOpen($query)
    {
        return $query->where('closed', false);
    }

    public function scopeClosed($query)
    {
        return $query->where('closed', true);
    }

    public function scopeForUser($query, User $user)
    {
        $accountIds = $user->accounts()->pluck('id');

        return $query->whereIn('account_id', $accountIds);
    }

    private function getCurrentPrice(): float
    {
        if ($this->company_ticker_id) {
            return $this->companyTicker?->last_price ?? (float) $this->average_purchase_price;
        }

        if ($this->treasure_id) {
            return $this->treasure?->last_unit_price ?? (float) $this->average_purchase_price;
        }

        return (float) $this->average_purchase_price;
    }
}

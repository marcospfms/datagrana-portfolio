<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Account;
use App\Models\Composition;
use App\Models\Consolidated;
use App\Models\Portfolio;

class UserSubscriptionUsage extends Model
{
    protected $table = 'user_subscription_usage';

    protected $fillable = [
        'user_id',
        'user_subscription_id',
        'current_portfolios',
        'current_compositions',
        'current_positions',
        'current_accounts',
        'last_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'current_portfolios' => 'integer',
            'current_compositions' => 'integer',
            'current_positions' => 'integer',
            'current_accounts' => 'integer',
            'last_calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function recalculate(): void
    {
        $this->current_portfolios = Portfolio::where('user_id', $this->user_id)->count();

        $this->current_compositions = Composition::whereHas('portfolio', function ($query) {
            $query->where('user_id', $this->user_id);
        })->count();

        $this->current_positions = Consolidated::whereHas('account', function ($query) {
            $query->where('user_id', $this->user_id);
        })->where('closed', false)->count();

        $this->current_accounts = Account::where('user_id', $this->user_id)->count();

        $this->last_calculated_at = now();
        $this->save();
    }

    public function getCompositionsByPortfolio(): array
    {
        return Composition::whereHas('portfolio', function ($query) {
            $query->where('user_id', $this->user_id);
        })
            ->selectRaw('portfolio_id, count(*) as total')
            ->groupBy('portfolio_id')
            ->pluck('total', 'portfolio_id')
            ->toArray();
    }

    public function getMaxCompositionsPerPortfolio(): int
    {
        $counts = $this->getCompositionsByPortfolio();

        if (!$counts) {
            return 0;
        }

        return (int) max($counts);
    }

    public function incrementCounter(string $counter, int $amount = 1): void
    {
        $this->{$counter} = $this->{$counter} + $amount;
        $this->save();
    }

    public function decrementCounter(string $counter, int $amount = 1): void
    {
        $this->{$counter} = max(0, $this->{$counter} - $amount);
        $this->save();
    }
}

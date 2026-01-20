<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Portfolio extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'portfolios';

    protected $appends = [
        'total_percentage',
        'total_percentage_by_category',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'month_value',
        'target_value',
    ];

    protected function casts(): array
    {
        return [
            'month_value' => 'decimal:2',
            'target_value' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function compositions(): HasMany
    {
        return $this->hasMany(Composition::class);
    }

    public function compositionHistories(): HasMany
    {
        return $this->hasMany(CompositionHistory::class)->withTrashed();
    }

    public function compositionsTreasure(): HasMany
    {
        return $this->compositions()
            ->whereNotNull('treasure_id')
            ->with('treasure.treasureCategory');
    }

    public function compositionsCompany(): HasMany
    {
        return $this->compositions()
            ->whereNotNull('company_ticker_id')
            ->with('companyTicker.company.companyCategory');
    }

    protected function totalPercentage(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->compositions()->sum('percentage'), 2)
        );
    }

    protected function totalPercentageByCategory(): Attribute
    {
        return Attribute::make(
            get: function () {
                $compositions = $this->compositions()
                    ->with(['treasure.treasureCategory', 'companyTicker.company.companyCategory'])
                    ->get();

                $categories = [];

                foreach ($compositions as $composition) {
                    if ($composition->treasure_id) {
                        $category = $composition->treasure?->treasureCategory;
                        $label = 'Renda Fixa - ' . ($category?->name ?? 'Sem categoria');
                        $type = 'treasure';
                    } else {
                        $category = $composition->companyTicker?->company?->companyCategory;
                        $label = 'Renda Variavel - ' . ($category?->name ?? 'Sem categoria');
                        $type = 'company';
                    }

                    if (!isset($categories[$label])) {
                        $categories[$label] = [
                            'label' => $label,
                            'type' => $type,
                            'category' => $category,
                            'sum' => 0,
                        ];
                    }

                    $categories[$label]['sum'] += (float) $composition->percentage;
                }

                return collect($categories)->map(fn ($item) => (object) $item);
            }
        );
    }
}

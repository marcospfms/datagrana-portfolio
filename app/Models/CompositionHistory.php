<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompositionHistory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'composition_histories';

    protected $fillable = [
        'portfolio_id',
        'treasure_id',
        'company_ticker_id',
        'percentage',
        'reason',
        'created_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function treasure(): BelongsTo
    {
        return $this->belongsTo(Treasure::class);
    }

    public function companyTicker(): BelongsTo
    {
        return $this->belongsTo(CompanyTicker::class);
    }
}

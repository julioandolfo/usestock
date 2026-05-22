<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'strategy',
        'value',
        'min_credits',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'min_credits' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}

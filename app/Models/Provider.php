<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'host',
        'type',
        'logo',
        'resolution',
        'license',
        'upstream_price',
        'upstream_price_bonus',
        'is_premium',
        'api_access',
        'enabled',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
            'api_access' => 'boolean',
            'enabled' => 'boolean',
            'upstream_price' => 'decimal:4',
            'upstream_price_bonus' => 'decimal:4',
            'synced_at' => 'datetime',
        ];
    }

    public function pricingRule(): ?PricingRule
    {
        return PricingRule::where('provider_id', $this->id)
            ->where('active', true)
            ->latest('id')
            ->first();
    }

    public function downloadRequests(): HasMany
    {
        return $this->hasMany(DownloadRequest::class);
    }
}

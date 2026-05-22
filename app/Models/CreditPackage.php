<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'credits',
        'bonus_credits',
        'price_cents',
        'currency',
        'featured',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'bonus_credits' => 'integer',
            'price_cents' => 'integer',
            'featured' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function totalCredits(): int
    {
        return $this->credits + $this->bonus_credits;
    }

    public function priceFormatted(): string
    {
        return number_format($this->price_cents / 100, 2, ',', '.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\CreditPackage;
use App\Models\PricingRule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        PricingRule::firstOrCreate(
            ['provider_id' => null, 'strategy' => 'multiplier'],
            ['value' => 2.0, 'min_credits' => 1, 'active' => true]
        );

        $defaults = [
            ['name' => 'Iniciante', 'credits' => 50, 'bonus_credits' => 0, 'price_cents' => 2500, 'sort_order' => 1],
            ['name' => 'Plus', 'credits' => 110, 'bonus_credits' => 10, 'price_cents' => 5000, 'sort_order' => 2, 'featured' => true],
            ['name' => 'Pro', 'credits' => 240, 'bonus_credits' => 30, 'price_cents' => 10000, 'sort_order' => 3],
        ];

        foreach ($defaults as $row) {
            CreditPackage::firstOrCreate(
                ['name' => $row['name']],
                array_merge([
                    'description' => null,
                    'currency' => 'BRL',
                    'active' => true,
                    'featured' => $row['featured'] ?? false,
                ], $row)
            );
        }
    }
}

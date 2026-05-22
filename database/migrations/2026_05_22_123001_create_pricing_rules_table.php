<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('Null = fallback default for any provider without a specific rule.');
            $table->enum('strategy', ['fixed', 'multiplier'])->default('multiplier')
                ->comment('fixed = absolute credit cost; multiplier = upstream_price × factor.');
            $table->decimal('value', 10, 4)->default(2.0)
                ->comment('When fixed: credit amount. When multiplier: factor applied to upstream price.');
            $table->unsignedInteger('min_credits')->default(1)
                ->comment('Floor for the resulting cost so cheap items still have minimum margin.');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};

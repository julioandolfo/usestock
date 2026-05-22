<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('credits')->comment('Base credits granted on purchase.');
            $table->unsignedInteger('bonus_credits')->default(0)
                ->comment('Promotional extra credits added on top of base.');
            $table->unsignedInteger('price_cents')->comment('Price in BRL cents.');
            $table->string('currency', 3)->default('BRL');
            $table->boolean('featured')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('active');
            $table->index('featured');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_packages');
    }
};

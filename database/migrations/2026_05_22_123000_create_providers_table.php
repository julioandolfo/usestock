<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->string('host')->nullable();
            $table->string('type')->comment('provType from API, e.g. shutterstock_photo');
            $table->string('logo')->nullable();
            $table->string('resolution')->nullable();
            $table->string('license')->nullable();
            $table->decimal('upstream_price', 10, 4)->default(0)
                ->comment('provPrice from upstream API.');
            $table->decimal('upstream_price_bonus', 10, 4)->default(0)
                ->comment('provPriceBonus from upstream API.');
            $table->boolean('is_premium')->default(false);
            $table->boolean('api_access')->default(true);
            $table->boolean('enabled')->default(true)
                ->comment('Admin toggle: is this provider exposed to users?');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'type']);
            $table->index('enabled');
            $table->index('is_premium');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};

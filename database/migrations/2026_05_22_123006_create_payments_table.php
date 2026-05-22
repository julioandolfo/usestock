<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('mercadopago');
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_preference_id')->nullable()->index();
            $table->enum('method', ['pix', 'credit_card', 'debit_card', 'boleto', 'other'])->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->unsignedInteger('credits_to_grant');
            $table->enum('status', ['pending', 'in_process', 'approved', 'rejected', 'cancelled', 'refunded', 'expired'])
                ->default('pending');
            $table->json('provider_payload')->nullable()->comment('Latest webhook/status payload from provider.');
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->ipAddress('payer_ip')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

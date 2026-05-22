<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Admin user that triggered manual operations, when applicable.');
            $table->enum('type', [
                'purchase',        // user bought a package
                'admin_credit',    // manual admin grant
                'admin_debit',     // manual admin deduction
                'download_charge', // debit triggered by a download
                'refund',          // refund of a failed download
                'bonus',           // promotional/system bonus
                'adjustment',      // any reconciliation
            ]);
            $table->integer('amount')->comment('Signed integer; positive = credit in, negative = credit out.');
            $table->unsignedBigInteger('balance_after');
            $table->string('reference_type')->nullable()
                ->comment('Polymorphic ref, e.g. App\\Models\\Payment or App\\Models\\DownloadRequest.');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('getstocks_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('download_request_id')->nullable()
                ->constrained('download_requests')->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 8)->default('GET');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['endpoint', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('getstocks_api_logs');
    }
};

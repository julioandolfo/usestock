<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('download_requests', function (Blueprint $table) {
            $table->unsignedInteger('served_count')->default(0)
                ->after('credits_charged')
                ->comment('How many times the file was actually downloaded by the user.');
            $table->timestamp('last_served_at')->nullable()->after('served_count');
        });
    }

    public function down(): void
    {
        Schema::table('download_requests', function (Blueprint $table) {
            $table->dropColumn(['served_count', 'last_served_at']);
        });
    }
};

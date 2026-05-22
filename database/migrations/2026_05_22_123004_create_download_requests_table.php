<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('download_batches')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();

            $table->text('source_url');
            $table->string('provider_slug')->nullable();
            $table->string('item_type')->nullable()->comment('e.g. shutterstock_photo');
            $table->boolean('is_premium')->default(true);

            $table->string('upstream_item_id')->nullable();
            $table->string('upstream_item_slug')->nullable();
            $table->text('upstream_thumb_url')->nullable();
            $table->string('item_name')->nullable();
            $table->string('item_d_code')->nullable()->comment('itemDCode from download-status response.');
            $table->text('upstream_download_link')->nullable();

            $table->string('file_name')->nullable();
            $table->string('file_extension', 16)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('storage_disk')->default('downloads');

            $table->unsignedInteger('credits_charged')->default(0);
            $table->decimal('upstream_cost', 10, 4)->nullable()
                ->comment('Snapshot of upstream price at request time, for auditing.');

            $table->enum('status', [
                'queued',
                'resolving',     // calling getinfo
                'requesting',    // calling getlink, awaiting webhook/poll
                'downloading',   // streaming the final file to storage
                'ready',         // file is on our disk, user can download
                'expired',       // ttl passed, file removed but record kept
                'failed',
                'refunded',
            ])->default('queued');

            $table->string('failure_reason')->nullable();
            $table->json('upstream_response')->nullable()->comment('Raw response snapshot for debugging.');
            $table->unsignedTinyInteger('poll_attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->ipAddress('user_ip')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index('provider_slug');
            $table->index('upstream_item_id');
            $table->index('item_d_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_requests');
    }
};

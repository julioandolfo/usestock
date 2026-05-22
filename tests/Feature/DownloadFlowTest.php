<?php

use App\Jobs\ProcessDownloadJob;
use App\Models\DownloadRequest;
use App\Models\User;
use App\Services\Downloads\DownloadOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('debits credits and dispatches a job for a single link submit', function () {
    Queue::fake();
    $user = User::factory()->create(['credits_balance' => 100]);

    $orchestrator = app(DownloadOrchestrator::class);
    $result = $orchestrator->submit($user, ['https://example.com/a'], isPremium: true);

    expect($result['items'])->toHaveCount(1);
    expect($user->fresh()->credits_balance)->toBeLessThan(100);
    Queue::assertPushed(ProcessDownloadJob::class);
});

it('rejects when user does not have enough credits', function () {
    $user = User::factory()->create(['credits_balance' => 0]);
    $orchestrator = app(DownloadOrchestrator::class);

    expect(fn () => $orchestrator->submit($user, ['https://example.com/a'], isPremium: true))
        ->toThrow(RuntimeException::class, 'Créditos insuficientes');
});

it('reuses an existing ready download for the same source URL without charging again', function () {
    Queue::fake();
    $user = User::factory()->create(['credits_balance' => 100]);

    DownloadRequest::create([
        'user_id' => $user->id,
        'source_url' => 'https://example.com/dup',
        'is_premium' => true,
        'status' => DownloadRequest::STATUS_READY,
        'storage_path' => 'path.jpg',
        'storage_disk' => 'downloads',
        'file_name' => 'path.jpg',
        'credits_charged' => 5,
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $before = $user->fresh()->credits_balance;
    $result = app(DownloadOrchestrator::class)->submit($user, ['https://example.com/dup'], isPremium: true);

    expect($result['reused'])->toBe(1);
    expect($user->fresh()->credits_balance)->toBe($before); // not charged
    Queue::assertNothingPushed();
});

it('rejects when concurrency limit is reached', function () {
    $user = User::factory()->create(['credits_balance' => 1000]);

    foreach (range(1, 3) as $i) {
        DownloadRequest::create([
            'user_id' => $user->id,
            'source_url' => "https://example.com/{$i}",
            'is_premium' => true,
            'status' => DownloadRequest::STATUS_DOWNLOADING,
            'credits_charged' => 1,
        ]);
    }

    expect(fn () => app(DownloadOrchestrator::class)->submit($user, ['https://example.com/new'], true))
        ->toThrow(RuntimeException::class, 'Limite');
});

<?php

namespace App\Events;

use App\Models\DownloadRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DownloadStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly DownloadRequest $download) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.' . $this->download->user_id . '.downloads'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'download.status';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->download->public_id,
            'status' => $this->download->status,
            'item_name' => $this->download->item_name,
            'provider_slug' => $this->download->provider_slug,
            'file_name' => $this->download->file_name,
            'file_size_bytes' => $this->download->file_size_bytes,
            'failure_reason' => $this->download->failure_reason,
            'ready_at' => $this->download->ready_at?->toIso8601String(),
            'expires_at' => $this->download->expires_at?->toIso8601String(),
        ];
    }
}

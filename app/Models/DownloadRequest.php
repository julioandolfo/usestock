<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DownloadRequest extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RESOLVING = 'resolving';

    public const STATUS_REQUESTING = 'requesting';

    public const STATUS_DOWNLOADING = 'downloading';

    public const STATUS_READY = 'ready';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'public_id',
        'user_id',
        'batch_id',
        'provider_id',
        'source_url',
        'provider_slug',
        'item_type',
        'is_premium',
        'upstream_item_id',
        'upstream_item_slug',
        'upstream_thumb_url',
        'item_name',
        'item_d_code',
        'upstream_download_link',
        'file_name',
        'file_extension',
        'file_size_bytes',
        'storage_path',
        'storage_disk',
        'credits_charged',
        'upstream_cost',
        'status',
        'failure_reason',
        'upstream_response',
        'poll_attempts',
        'expires_at',
        'completed_at',
        'ready_at',
        'user_ip',
    ];

    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
            'file_size_bytes' => 'integer',
            'credits_charged' => 'integer',
            'upstream_cost' => 'decimal:4',
            'upstream_response' => 'array',
            'poll_attempts' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DownloadRequest $request): void {
            $request->public_id ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DownloadBatch::class, 'batch_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(GetstocksApiLog::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->storage_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isFinalState(): bool
    {
        return in_array($this->status, [
            self::STATUS_READY,
            self::STATUS_EXPIRED,
            self::STATUS_FAILED,
            self::STATUS_REFUNDED,
        ], true);
    }
}

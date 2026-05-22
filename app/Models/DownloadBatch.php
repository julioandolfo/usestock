<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DownloadBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'name',
        'total_items',
        'completed_items',
        'failed_items',
        'total_credits_charged',
        'status',
        'zip_requested',
        'zip_path',
        'zip_ready_at',
    ];

    protected function casts(): array
    {
        return [
            'total_items' => 'integer',
            'completed_items' => 'integer',
            'failed_items' => 'integer',
            'total_credits_charged' => 'integer',
            'zip_requested' => 'boolean',
            'zip_ready_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DownloadBatch $batch): void {
            $batch->public_id ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function downloadRequests(): HasMany
    {
        return $this->hasMany(DownloadRequest::class, 'batch_id');
    }
}

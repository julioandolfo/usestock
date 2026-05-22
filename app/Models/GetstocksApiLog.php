<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GetstocksApiLog extends Model
{
    use HasFactory;

    protected $table = 'getstocks_api_logs';

    protected $fillable = [
        'download_request_id',
        'endpoint',
        'method',
        'response_status',
        'request_payload',
        'response_payload',
        'duration_ms',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function downloadRequest(): BelongsTo
    {
        return $this->belongsTo(DownloadRequest::class);
    }
}

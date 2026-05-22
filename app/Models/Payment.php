<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROCESS = 'in_process';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'public_id',
        'user_id',
        'credit_package_id',
        'provider',
        'provider_payment_id',
        'provider_preference_id',
        'method',
        'amount_cents',
        'currency',
        'credits_to_grant',
        'status',
        'provider_payload',
        'failure_reason',
        'paid_at',
        'expires_at',
        'payer_ip',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'credits_to_grant' => 'integer',
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->public_id ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class, 'credit_package_id');
    }
}

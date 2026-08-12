<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A claim that a payment was made — not itself a ledger entry. It counts
 * toward balances only once confirmed.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @param  Builder<Payment>  $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', PaymentStatus::Confirmed);
    }

    /**
     * True when an admin confirmed a different figure to the one claimed.
     */
    public function wasAdjusted(): bool
    {
        return $this->claimed_amount_cents !== null
            && $this->claimed_amount_cents !== $this->amount_cents;
    }
}

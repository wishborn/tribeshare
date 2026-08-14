<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\HatType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'carried_balance_limit_cents', 'monthly_booking_cap', 'billing_suspended'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'billing_suspended' => 'boolean',
        ];
    }

    /**
     * Defaults for values the application reads back.
     *
     * A database default is applied by the DATABASE, so it is absent from the
     * model instance that inserted the row — reading it straight after
     * create() yields null and every limit check compares against nothing.
     *
     * These live here rather than in a `creating` hook because seeders
     * routinely mute model events (`WithoutModelEvents`), which would skip a
     * hook silently and leave the column null again. Attribute defaults apply
     * on instantiation and cannot be muted.
     *
     * Keep in step with the column defaults in the users migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'carried_balance_limit_cents' => 1000_00,
        'billing_suspended' => false,
    ];

    /** @return HasMany<Hat, $this> */
    public function hats(): HasMany
    {
        return $this->hasMany(Hat::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<PayoutRequest, $this> */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /** @return MorphMany<LedgerEntry, $this> */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'owner');
    }

    /** @return BelongsToMany<Asset, $this> */
    public function pooledAssets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_pool_members')
            ->withTimestamps();
    }

    /**
     * Whether this member holds the given hat type, optionally scoped.
     *
     * A globally-scoped ("all") hat satisfies any scope. Pass $strict to
     * require the hat be scoped exactly to the entity — which sensitive LLC
     * powers do.
     */
    public function hasHat(HatType $type, ?Model $scope = null, bool $strict = false): bool
    {
        $query = $this->hats()->active()->where('type', $type);

        if ($scope !== null) {
            $strict
                ? $query->scopedStrictlyTo($scope)
                : $query->applyingTo($scope);
        }

        return $query->exists();
    }

    public function isRcm(): bool
    {
        return $this->hasHat(HatType::Rcm);
    }

    /**
     * Booking priority for an asset — decides who may bump whom.
     *
     * Deliberately uses direct asset hats only; there is no LLC cascade.
     */
    public function bookingPriorityFor(Asset $asset): int
    {
        if ($this->isRcm()) {
            return HatType::Rcm->bookingPriority();
        }

        $priorities = $this->hats()
            ->active()
            ->scopedStrictlyTo($asset)
            ->get()
            ->all();

        return array_reduce(
            $priorities,
            fn (int $highest, Hat $hat) => max($highest, $hat->type->bookingPriority()),
            1,
        );
    }
}

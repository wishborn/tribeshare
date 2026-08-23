<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\HatType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;
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
    // RoutesNotifications rather than the whole Notifiable trait: we want
    // Laravel's delivery (Fortify sends password resets through notify())
    // but not its HasDatabaseNotifications relation, which would shadow our
    // own notifications() with one pointing at a table we do not use.
    use HasFactory, HasUuids, PasskeyAuthenticatable, RoutesNotifications, TwoFactorAuthenticatable;

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
            'recycled_at' => 'datetime',
            'is_super_admin' => 'boolean',
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
        'is_super_admin' => false,
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

    /** @return HasMany<Suspension, $this> */
    public function suspensions(): HasMany
    {
        return $this->hasMany(Suspension::class);
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
    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<MemberRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(MemberRequest::class, 'requested_by');
    }

    /** @return BelongsToMany<Conversation, $this> */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->wherePivotNull('left_at')
            ->withPivot(['archived_at', 'joined_at', 'left_at']);
    }

    /** @return HasMany<MemberRemovalQueue, $this> */
    public function removalQueues(): HasMany
    {
        return $this->hasMany(MemberRemovalQueue::class);
    }

    /** @return HasMany<LlcLeaveQueue, $this> */
    public function leaveQueues(): HasMany
    {
        return $this->hasMany(LlcLeaveQueue::class);
    }

    /**
     * Removed, but still readable — their bookings, ledger entries and
     * messages all reference them, and the history has to keep making sense.
     */
    public function isRecycled(): bool
    {
        return $this->recycled_at !== null;
    }

    /**
     * Members holding an active hat, optionally of given types and scoped to
     * a given entity.
     *
     * One definition of a query three subsystems were each building by hand.
     *
     * @param  Builder<User>  $query
     * @param  HatType|array<int, HatType>|null  $types
     */
    public function scopeHoldingHat(Builder $query, HatType|array|null $types = null, ?Model $scope = null): void
    {
        $wanted = $types === null ? [] : (is_array($types) ? $types : [$types]);

        $query->whereHas('hats', function (Builder $hats) use ($wanted, $scope): void {
            $hats->where('active', true);

            if ($wanted !== []) {
                $hats->whereIn('type', array_map(fn (HatType $type) => $type->value, $wanted));
            }

            if ($scope !== null) {
                $hats->where('scopeable_type', $scope->getMorphClass())
                    ->where('scopeable_id', $scope->getKey());
            }
        });
    }

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

    /**
     * The content authority — regions, LLCs, assets, members, and access to
     * regional hats.
     */
    public function isRcm(): bool
    {
        return $this->hasHat(HatType::Rcm);
    }

    /**
     * The platform operator. A separate domain from the RCM, not a superset:
     * an Admin outranks an RCM for granting purposes but holds no authority
     * over an asset's bookings or an LLC's members.
     */
    public function isAdmin(): bool
    {
        return $this->hasHat(HatType::Admin);
    }

    /**
     * The first Admin ever created. Cannot be demoted or removed, and is the
     * only member who may appoint or remove other Admins.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin && $this->isAdmin();
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

<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A thread.
 *
 * @property bool $is_direct
 * @property CarbonImmutable|null $last_message_at
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['is_direct' => false];

    protected function casts(): array
    {
        return [
            'is_direct' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    /** @return HasMany<ConversationParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Members currently in the thread — those who have not left.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->wherePivotNull('left_at')
            ->withPivot(['archived_at', 'joined_at', 'left_at']);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return MorphTo<Model, $this> */
    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function includes(User $user): bool
    {
        return $this->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
    }

    /**
     * The stable key that deduplicates a 1:1 thread.
     *
     * Sorted, so the pair produces the same key whichever member starts it.
     *
     * @param  array<int, string>  $userIds
     */
    public static function directKeyFor(array $userIds): string
    {
        sort($userIds);

        return implode(':', $userIds);
    }

    /** @param  Builder<Conversation>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas(
            'participants',
            fn ($q) => $q->where('user_id', $user->id)->whereNull('left_at'),
        );
    }
}

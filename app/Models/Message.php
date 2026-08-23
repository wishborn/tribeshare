<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message in a thread.
 */
class Message extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['body_deleted_at' => 'datetime'];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return HasMany<MessageRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * Deleted by its sender: the body is gone but the message keeps its place
     * in the thread, so the conversation still reads in order.
     */
    public function bodyWasDeleted(): bool
    {
        return $this->body_deleted_at !== null;
    }

    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }
}

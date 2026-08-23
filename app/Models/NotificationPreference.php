<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether a member wants a class of notification, and by which channel.
 *
 * Consulted on every send. In the prototype these were written by one action,
 * read by one screen, and consulted nowhere — a setting with no effect, which
 * is worse than either honouring it or removing the screen.
 */
class NotificationPreference extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'in_app' => true,
        'push' => true,
    ];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'push' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

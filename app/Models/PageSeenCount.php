<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The count a member had already seen when they last opened a page.
 *
 * A badge returns when the live count exceeds this. Server-computed now:
 * the client no longer receives the whole state to derive counts from.
 */
class PageSeenCount extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'seen_count' => 'integer',
            'seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

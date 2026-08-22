<?php

namespace App\Models;

use Database\Factories\CalendarRulePriorityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's standing within a slice.
 *
 * @property int $position
 * @property bool $cannot_book
 * @property bool $unbumpable
 */
class CalendarRulePriority extends Model
{
    /** @use HasFactory<CalendarRulePriorityFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cannot_book' => 'boolean',
            'unbumpable' => 'boolean',
        ];
    }

    /** @return BelongsTo<CalendarRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CalendarRule::class, 'calendar_rule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

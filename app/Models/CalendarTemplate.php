<?php

namespace App\Models;

use Database\Factories\CalendarTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved month of rules.
 *
 * @property array<int, array<string, mixed>> $rules
 * @property int $source_days_in_month
 */
class CalendarTemplate extends Model
{
    /** @use HasFactory<CalendarTemplateFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Whether applying this template to a month of the given length would
     * drop rules off the end.
     *
     * The template records where it came from precisely so this can be
     * answered rather than silently truncated.
     */
    public function wouldTruncateInto(int $daysInMonth): bool
    {
        return $this->source_days_in_month > $daysInMonth
            && collect($this->rules)->contains(fn (array $rule) => ($rule['day'] ?? 0) > $daysInMonth);
    }
}

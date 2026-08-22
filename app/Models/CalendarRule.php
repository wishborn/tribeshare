<?php

namespace App\Models;

use Database\Factories\CalendarRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What is true of a slice of a day.
 *
 * @property int $day
 * @property int $meso_start
 * @property int $meso_end
 * @property bool $bookable
 * @property float $price_multiplier_pct
 * @property bool $draft
 * @property array<int, string>|null $allowed_slot_types
 */
class CalendarRule extends Model
{
    /** @use HasFactory<CalendarRuleFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bookable' => 'boolean',
            'draft' => 'boolean',
            'price_multiplier_pct' => 'float',
            'allowed_slot_types' => 'array',
        ];
    }

    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /** @return HasMany<CalendarRulePriority, $this> */
    public function priorities(): HasMany
    {
        return $this->hasMany(CalendarRulePriority::class)->orderBy('position');
    }

    /**
     * How many mesos this rule covers.
     */
    public function mesoCount(): int
    {
        return max(0, $this->meso_end - $this->meso_start);
    }

    /**
     * Whether this rule covers a given meso of its day. Half-open, so a rule
     * ending where another begins does not overlap it.
     */
    public function covers(int $meso): bool
    {
        return $meso >= $this->meso_start && $meso < $this->meso_end;
    }

    /**
     * Rules overlapping a meso range on a given day.
     *
     * @param  Builder<CalendarRule>  $query
     */
    public function scopeCovering(Builder $query, int $day, int $mesoStart, int $mesoEnd): void
    {
        $query->where('day', $day)
            ->where('meso_start', '<', $mesoEnd)
            ->where('meso_end', '>', $mesoStart);
    }

    /** @param  Builder<CalendarRule>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('draft', false);
    }
}

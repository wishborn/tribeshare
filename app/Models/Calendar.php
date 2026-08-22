<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CalendarFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One month of availability.
 *
 * @property string $month YYYY-MM
 * @property CarbonImmutable|null $published_at
 */
class Calendar extends Model
{
    /** @use HasFactory<CalendarFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<CalendarRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(CalendarRule::class);
    }

    /** @return HasMany<CalendarRule, $this> */
    public function publishedRules(): HasMany
    {
        return $this->rules()->where('draft', false);
    }

    /** @return HasMany<CalendarRule, $this> */
    public function draftRules(): HasMany
    {
        return $this->rules()->where('draft', true);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function hasDraft(): bool
    {
        return $this->draftRules()->exists();
    }

    /** @param  Builder<Calendar>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * The months a range of time touches — a booking that spans a month
     * boundary belongs to both, which a date-string prefix could never
     * express.
     *
     * @return array<int, string>
     */
    public static function monthsSpanning(\DateTimeInterface $from, \DateTimeInterface $until): array
    {
        $cursor = CarbonImmutable::instance($from)->startOfMonth();
        $end = CarbonImmutable::instance($until);
        $months = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return $months;
    }
}

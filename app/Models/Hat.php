<?php

namespace App\Models;

use App\Enums\HatType;
use Database\Factories\HatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property HatType $type
 * @property bool $active
 * @property string|null $scopeable_type
 * @property string|null $scopeable_id
 */
class Hat extends Model
{
    /** @use HasFactory<HatFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => HatType::class,
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A null scope is the "all" scope — authority everywhere.
     */
    public function isGlobalScope(): bool
    {
        return $this->scopeable_id === null;
    }

    /**
     * Hats that apply to the given entity, including globally-scoped ones.
     *
     * @param  Builder<Hat>  $query
     */
    public function scopeApplyingTo(Builder $query, Model $entity): void
    {
        $query->where(function (Builder $q) use ($entity) {
            $q->whereNull('scopeable_id')
                ->orWhere(function (Builder $inner) use ($entity) {
                    $inner->where('scopeable_type', $entity->getMorphClass())
                        ->where('scopeable_id', $entity->getKey());
                });
        });
    }

    /**
     * Hats scoped EXACTLY to the given entity, excluding globally-scoped
     * ones. Sensitive LLC powers require this stricter form.
     *
     * @param  Builder<Hat>  $query
     */
    public function scopeScopedStrictlyTo(Builder $query, Model $entity): void
    {
        $query->where('scopeable_type', $entity->getMorphClass())
            ->where('scopeable_id', $entity->getKey());
    }

    /**
     * @param  Builder<Hat>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}

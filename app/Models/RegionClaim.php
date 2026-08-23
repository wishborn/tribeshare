<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An insurance claim, tracked through its life.
 *
 * Its own entity, not a document with extra fields. A claim has a lifecycle
 * documents do not, and the prototype's version — a status string overwritten
 * in place, filed among the paperwork — could not answer when it changed or
 * who changed it.
 *
 * @property ClaimStatus $status
 * @property CarbonImmutable $filed_on
 * @property CarbonImmutable|null $settled_on
 */
class RegionClaim extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'filed',
        'claimed_cents' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'incident_on' => 'date',
            'filed_on' => 'date',
            'settled_on' => 'date',
        ];
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    /** @return HasMany<RegionClaimEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RegionClaimEvent::class);
    }

    /** @return BelongsToMany<RegionDocument, $this> */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(RegionDocument::class, 'region_claim_documents');
    }

    /** @param  Builder<RegionClaim>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', array_map(
            fn (ClaimStatus $status) => $status->value,
            array_filter(ClaimStatus::cases(), fn (ClaimStatus $status) => $status->isOpen()),
        ));
    }
}

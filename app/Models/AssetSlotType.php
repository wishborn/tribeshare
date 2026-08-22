<?php

namespace App\Models;

use Database\Factories\AssetSlotTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A duration an asset offers, and what it costs.
 *
 * @property string $key
 * @property int $duration_mesos
 * @property int $price_cents
 * @property bool $approval_required
 * @property bool $bump_allowed
 */
class AssetSlotType extends Model
{
    /** @use HasFactory<AssetSlotTypeFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'approval_required' => 'boolean',
            'bump_allowed' => 'boolean',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @param  Builder<AssetSlotType>  $query */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }
}

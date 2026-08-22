<?php

namespace App\Models;

use Database\Factories\AssetUnitPriceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A metered rate — cents per unit.
 *
 * @property string $unit
 * @property int $rate_cents
 */
class AssetUnitPrice extends Model
{
    /** @use HasFactory<AssetUnitPriceFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * What a reported quantity of this unit costs.
     */
    public function chargeFor(int|float $quantity): int
    {
        return (int) round($this->rate_cents * $quantity);
    }
}

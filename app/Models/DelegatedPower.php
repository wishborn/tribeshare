<?php

namespace App\Models;

use App\Enums\PowerTier;
use Database\Factories\DelegatedPowerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entity's override of one delegated power for one tier.
 *
 * @property string $power
 * @property PowerTier $tier
 * @property bool $granted
 */
class DelegatedPower extends Model
{
    /** @use HasFactory<DelegatedPowerFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tier' => PowerTier::class,
            'granted' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function powerable(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in a claim's life.
 *
 * @property ClaimStatus|null $from_status
 * @property ClaimStatus $to_status
 */
class RegionClaimEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_status' => ClaimStatus::class,
            'to_status' => ClaimStatus::class,
        ];
    }

    /** @return BelongsTo<RegionClaim, $this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(RegionClaim::class, 'region_claim_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

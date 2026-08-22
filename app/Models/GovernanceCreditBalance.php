<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a member has left to spend across proposals.
 *
 * A budget, not a per-proposal allowance — the prototype reset the
 * allowance for every proposal, which removed the scarcity that makes
 * spending a real choice.
 *
 * @property int $credits_remaining
 */
class GovernanceCreditBalance extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['allocated_at' => 'datetime'];
    }

    /** @return BelongsTo<GovernanceConfig, $this> */
    public function config(): BelongsTo
    {
        return $this->belongsTo(GovernanceConfig::class, 'governance_config_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

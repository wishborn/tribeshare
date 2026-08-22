<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A constituency that must be carried on its own terms.
 */
class StakeholderClass extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quorum_pct' => 'float', 'pass_pct' => 'float'];
    }

    /** @return BelongsTo<GovernanceConfig, $this> */
    public function config(): BelongsTo
    {
        return $this->belongsTo(GovernanceConfig::class, 'governance_config_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'stakeholder_class_members')->withTimestamps();
    }
}

<?php

namespace App\Models;

use App\Enums\VotingModel;
use Database\Factories\GovernanceConfigFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * How one entity decides things.
 *
 * @property VotingModel $model
 * @property float $quorum_pct
 * @property float $pass_pct
 * @property int $voting_window_days
 * @property int $execution_delay_days
 * @property float $petition_threshold_pct
 * @property int $voting_credits
 */
class GovernanceConfig extends Model
{
    /** @use HasFactory<GovernanceConfigFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'enabled' => false,
        'model' => 'one_member_one_vote',
        'quorum_pct' => 50,
        'pass_pct' => 60,
        'voting_window_days' => 7,
        'execution_delay_days' => 2,
        'petition_enabled' => true,
        'petition_threshold_pct' => 20,
        'who_can_propose' => 'owners',
        'voting_credits' => 100,
        'credit_period_days' => 30,
    ];

    protected function casts(): array
    {
        return [
            'model' => VotingModel::class,
            'enabled' => 'boolean',
            'petition_enabled' => 'boolean',
            'quorum_pct' => 'float',
            'pass_pct' => 'float',
            'petition_threshold_pct' => 'float',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function governable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<Proposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /** @return HasMany<StakeholderClass, $this> */
    public function stakeholderClasses(): HasMany
    {
        return $this->hasMany(StakeholderClass::class);
    }

    /** @return HasMany<GovernanceCreditBalance, $this> */
    public function creditBalances(): HasMany
    {
        return $this->hasMany(GovernanceCreditBalance::class);
    }

    /**
     * Members granted the right to propose outright, independently of their
     * hats — the escape hatch from `who_can_propose`.
     *
     * @return BelongsToMany<User, $this>
     */
    public function proposalRights(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proposal_rights')->withTimestamps();
    }
}

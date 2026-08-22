<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use Carbon\CarbonImmutable;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property ProposalStatus $status
 * @property ProposalType $type
 * @property CarbonImmutable|null $voting_closes_at
 * @property CarbonImmutable|null $executes_at
 * @property array<string, mixed>|null $action_payload
 * @property int $execution_delay_days
 */
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'type' => ProposalType::class,
            'action_payload' => 'array',
            'voting_opens_at' => 'datetime',
            'voting_closes_at' => 'datetime',
            'executes_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GovernanceConfig, $this> */
    public function config(): BelongsTo
    {
        return $this->belongsTo(GovernanceConfig::class, 'governance_config_id');
    }

    /** @return MorphTo<Model, $this> */
    public function governable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /** @return HasMany<ProposalSignature, $this> */
    public function signatures(): HasMany
    {
        return $this->hasMany(ProposalSignature::class);
    }

    /** @return HasMany<ProposalVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ProposalVote::class);
    }

    /** @return HasMany<ProposalDelegation, $this> */
    public function delegations(): HasMany
    {
        return $this->hasMany(ProposalDelegation::class);
    }

    /** @return HasMany<ProposalCreditSpend, $this> */
    public function creditSpends(): HasMany
    {
        return $this->hasMany(ProposalCreditSpend::class);
    }

    /** @return BelongsTo<Proposal, $this> */
    public function repealOf(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'repeal_of_id');
    }

    public function isOpenForVoting(): bool
    {
        return $this->status === ProposalStatus::Voting;
    }

    public function votingHasClosed(?\DateTimeInterface $now = null): bool
    {
        $now ??= now();

        return $this->voting_closes_at !== null && $this->voting_closes_at->lessThanOrEqualTo($now);
    }

    /** @param  Builder<Proposal>  $query */
    public function scopeAwaitingTally(Builder $query): void
    {
        $query->where('status', ProposalStatus::Voting)
            ->whereNotNull('voting_closes_at')
            ->where('voting_closes_at', '<=', now());
    }

    /** @param  Builder<Proposal>  $query */
    public function scopeDueForExecution(Builder $query): void
    {
        $query->where('status', ProposalStatus::Passed)
            ->whereNotNull('executes_at')
            ->where('executes_at', '<=', now());
    }
}

<?php

namespace App\Services\Governance;

use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Enums\VoteDirection;
use App\Models\GovernanceConfig;
use App\Models\GovernanceCreditBalance;
use App\Models\Proposal;
use App\Models\ProposalDelegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Proposals, from raising one to applying it.
 */
class GovernanceService
{
    public function __construct(
        private readonly EligibilityResolver $eligibility,
        private readonly VoteTally $tally,
        private readonly DelegationResolver $delegations,
        private readonly ProposalExecutor $executor,
    ) {}

    public function configFor(Model $governable): GovernanceConfig
    {
        return GovernanceConfig::firstOrCreate([
            'governable_type' => $governable->getMorphClass(),
            'governable_id' => $governable->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function propose(
        User $proposer,
        Model $governable,
        ProposalType $type,
        string $title,
        array $payload = [],
        ProposalStatus $launchAs = ProposalStatus::Draft,
        ?string $locksField = null,
        ?Proposal $repealOf = null,
    ): Proposal {
        $config = $this->configFor($governable);

        $proposal = new Proposal([
            'governance_config_id' => $config->id,
            'governable_type' => $governable->getMorphClass(),
            'governable_id' => $governable->getKey(),
            'type' => $type,
            'title' => $title,
            'proposed_by' => $proposer->id,
            'status' => $launchAs,
            'execution_delay_days' => $config->execution_delay_days,
            'action_payload' => $payload,
            'locks_field' => $locksField,
            'repeal_of_id' => $repealOf?->id,
        ]);

        if ($launchAs === ProposalStatus::Voting) {
            $this->stampVotingWindow($proposal, $config);
        }

        $proposal->save();

        return $proposal;
    }

    /**
     * Sign a petition. Reaching the threshold opens the vote immediately.
     */
    public function sign(Proposal $proposal, User $user): Proposal
    {
        if ($proposal->status !== ProposalStatus::Petition) {
            throw new RuntimeException('That proposal is not gathering signatures.');
        }

        $proposal->signatures()->firstOrCreate(['user_id' => $user->id]);

        $eligible = $this->eligibility->for($proposal->governable);
        $signatures = $proposal->signatures()->count();

        $threshold = $proposal->config->petition_threshold_pct;
        $reached = count($eligible) > 0
            && ($signatures / count($eligible) * 100) >= $threshold;

        if ($reached) {
            $this->openVote($proposal);
        }

        return $proposal->refresh();
    }

    public function openVote(Proposal $proposal): Proposal
    {
        if (! in_array($proposal->status, [ProposalStatus::Draft, ProposalStatus::Petition], true)) {
            throw new RuntimeException('That proposal cannot be opened for voting.');
        }

        $this->stampVotingWindow($proposal, $proposal->config);
        $proposal->status = ProposalStatus::Voting;
        $proposal->save();

        return $proposal;
    }

    /**
     * Cast a vote.
     *
     * Casting **revokes any delegation** the member made on this proposal —
     * delegation is exclusive, so nobody's preference is ever counted twice.
     */
    public function castVote(Proposal $proposal, User $user, VoteDirection $direction, float $weight = 1.0, ?string $blockReason = null): void
    {
        $this->assertVotable($proposal);

        DB::transaction(function () use ($proposal, $user, $direction, $weight, $blockReason): void {
            $proposal->delegations()->where('from_user_id', $user->id)->delete();

            $proposal->votes()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'direction' => $direction,
                    'weight' => $weight,
                    'block_reason' => $blockReason,
                    'cast_at' => now(),
                ],
            );
        });

        $this->closeIfSettled($proposal->refresh());
    }

    /**
     * Hand a vote to another member. Transitive, exclusive, cycle-free.
     */
    public function delegate(Proposal $proposal, User $from, User $to): ProposalDelegation
    {
        $this->assertVotable($proposal);

        $edges = $proposal->delegations()
            ->pluck('to_user_id', 'from_user_id')
            ->all();

        $this->delegations->assertNoCycle($from->id, $to->id, $edges);

        return DB::transaction(function () use ($proposal, $from, $to): ProposalDelegation {
            // Delegating surrenders your own vote until you revoke it.
            $proposal->votes()->where('user_id', $from->id)->delete();

            return $proposal->delegations()->updateOrCreate(
                ['from_user_id' => $from->id],
                ['to_user_id' => $to->id],
            );
        });
    }

    /**
     * Spend credits under the quadratic model.
     *
     * Credits come from a **budget across proposals**, so spending here
     * leaves less for everything else — which is the whole point.
     */
    public function spendCredits(Proposal $proposal, User $user, int $credits, VoteDirection $direction): void
    {
        $this->assertVotable($proposal);

        if (! $proposal->config->model->usesCredits()) {
            throw new RuntimeException('This proposal is not decided by spending credits.');
        }

        if ($credits < 1) {
            throw new RuntimeException('A spend must be at least one credit.');
        }

        DB::transaction(function () use ($proposal, $user, $credits, $direction): void {
            $balance = $this->creditBalanceFor($proposal->config, $user);

            $existing = $proposal->creditSpends()->where('user_id', $user->id)->first();
            $alreadySpent = $existing === null ? 0 : $existing->credits;

            // Changing a spend returns what was already committed before
            // taking the new amount.
            $available = $balance->credits_remaining + $alreadySpent;

            if ($credits > $available) {
                throw new RuntimeException("That would spend {$credits} credits, and only {$available} remain.");
            }

            $balance->update(['credits_remaining' => $available - $credits]);

            $proposal->creditSpends()->updateOrCreate(
                ['user_id' => $user->id],
                ['credits' => $credits, 'direction' => $direction],
            );
        });

        $this->closeIfSettled($proposal->refresh());
    }

    public function withdraw(Proposal $proposal, User $user): void
    {
        if ($proposal->proposed_by !== $user->id) {
            throw new RuntimeException('Only the proposer may withdraw a proposal.');
        }

        if (! $proposal->status->isOpen()) {
            throw new RuntimeException('That proposal has already been decided.');
        }

        $proposal->update(['status' => ProposalStatus::Withdrawn]);
    }

    /**
     * Tally a proposal whose voting has finished.
     *
     * Passing **always** stamps the cooling-off delay. The prototype applied
     * a proposal immediately when it was executed straight out of voting,
     * honouring the delay only on the other path.
     */
    public function close(Proposal $proposal): Proposal
    {
        if ($proposal->status !== ProposalStatus::Voting) {
            return $proposal;
        }

        $proposal->loadMissing(['votes', 'delegations', 'creditSpends', 'config']);
        $result = $this->tally->for($proposal, $this->eligibility->for($proposal->governable));

        $proposal->update($result->passed
            ? [
                'status' => ProposalStatus::Passed,
                'executes_at' => now()->addDays($proposal->execution_delay_days),
            ]
            : ['status' => ProposalStatus::Failed]);

        return $proposal->refresh();
    }

    /**
     * The scheduled sweep: close finished votes, then apply what is due.
     *
     * Idempotent — safe to run repeatedly, and a no-op when nothing has
     * come round.
     *
     * @return array{closed: int, executed: int}
     */
    public function sweep(): array
    {
        $closed = 0;
        $executed = 0;

        Proposal::query()->awaitingTally()->get()->each(function (Proposal $proposal) use (&$closed): void {
            $this->close($proposal);
            $closed++;
        });

        Proposal::query()->dueForExecution()->get()->each(function (Proposal $proposal) use (&$executed): void {
            $this->executor->execute($proposal);
            $executed++;
        });

        return ['closed' => $closed, 'executed' => $executed];
    }

    public function creditBalanceFor(GovernanceConfig $config, User $user): GovernanceCreditBalance
    {
        return GovernanceCreditBalance::firstOrCreate(
            ['governance_config_id' => $config->id, 'user_id' => $user->id],
            ['credits_remaining' => $config->voting_credits, 'allocated_at' => now()],
        );
    }

    private function stampVotingWindow(Proposal $proposal, GovernanceConfig $config): void
    {
        $proposal->voting_opens_at = now();
        $proposal->voting_closes_at = now()->addDays($config->voting_window_days);
    }

    private function assertVotable(Proposal $proposal): void
    {
        if ($proposal->status !== ProposalStatus::Voting) {
            throw new RuntimeException('That proposal is not open for voting.');
        }
    }

    /**
     * Close early once everyone eligible has taken part, or the window has
     * expired.
     *
     * Full turnout settles a proposal rather than idling out the window — a
     * two-member LLC should not wait a week for a decision both members have
     * already made. The cost is that nobody can revise once the last member
     * is heard from, which is what the cooling-off delay is for: nothing is
     * applied immediately, so there is still room to raise a repeal.
     */
    private function closeIfSettled(Proposal $proposal): void
    {
        $eligible = $this->eligibility->for($proposal->governable);

        $participants = $proposal->config->model->usesCredits()
            ? $proposal->creditSpends()->count()
            : $proposal->votes()->count() + $proposal->delegations()->count();

        if ($proposal->votingHasClosed() || ($eligible !== [] && $participants >= count($eligible))) {
            $this->close($proposal);
        }
    }
}

<?php

namespace App\Policies;

use App\Enums\HatType;
use App\Enums\ProposalStatus;
use App\Models\Asset;
use App\Models\GovernanceConfig;
use App\Models\Llc;
use App\Models\Proposal;
use App\Models\Region;
use App\Models\User;
use App\Services\Governance\EligibilityResolver;
use App\Services\Permissions\HatService;
use App\Services\Permissions\SuspensionService;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may take part in governance.
 *
 * The prototype settled `whoCanPropose` in the authoring page, which is the
 * one governance file that was never read — so this is written from the
 * config's own vocabulary rather than reproduced.
 */
class ProposalPolicy
{
    public function __construct(
        private readonly HatService $hats,
        private readonly EligibilityResolver $eligibility,
        private readonly SuspensionService $suspensions,
    ) {}

    public function view(User $user, Proposal $proposal): bool
    {
        return $this->isEligible($user, $proposal->governable);
    }

    /**
     * Raising a proposal: whoever `who_can_propose` admits, plus anyone
     * granted the right explicitly.
     *
     * The explicit grant is deliberately independent of hats — it is the
     * escape hatch a small LLC needs when one member should be able to raise
     * something without being made an owner.
     */
    public function create(User $user, GovernanceConfig $config): bool
    {
        $entity = $config->governable;

        if (! $config->enabled || ! $this->isEligible($user, $entity)) {
            return false;
        }

        if ($config->proposalRights()->whereKey($user->id)->exists()) {
            return true;
        }

        return match ($config->who_can_propose) {
            'anyone', 'members' => true,
            'managers' => $this->holdsAtLeast($user, $entity, manager: true),
            default => $this->holdsAtLeast($user, $entity, manager: false),
        };
    }

    /**
     * Signing a petition, and voting, are the same question: are you one of
     * the people this decision belongs to?
     */
    public function sign(User $user, Proposal $proposal): bool
    {
        return $proposal->status === ProposalStatus::Petition
            && $proposal->config->petition_enabled
            && $this->isEligible($user, $proposal->governable);
    }

    public function vote(User $user, Proposal $proposal): bool
    {
        return $proposal->isOpenForVoting()
            && $this->isEligible($user, $proposal->governable);
    }

    public function withdraw(User $user, Proposal $proposal): bool
    {
        return $proposal->proposed_by === $user->id && $proposal->status->isOpen();
    }

    /**
     * Eligibility, minus anyone suspended from the entity in question.
     *
     * A suspended member is still on the roll — they are counted in the
     * eligible set the quorum divides by — but they may not act. Voting is
     * participation, and suspension is the withdrawal of participation.
     */
    private function isEligible(User $user, ?Model $entity): bool
    {
        if ($entity === null) {
            return false;
        }

        $llc = match (true) {
            $entity instanceof Llc => $entity,
            $entity instanceof Asset => $entity->llc,
            default => null,
        };

        if ($llc !== null && $this->suspensions->isSuspendedFrom($user, $llc)) {
            return false;
        }

        return in_array($user->id, $this->eligibility->for($entity), true);
    }

    /**
     * Standing over the entity — owner, or manager and above when the config
     * opens proposing that wide.
     */
    private function holdsAtLeast(User $user, ?Model $entity, bool $manager): bool
    {
        return match (true) {
            $entity instanceof Llc => $this->hats->holds($user, $manager ? HatType::LlcManager : HatType::LlcOwner, $entity),
            $entity instanceof Asset => $this->hats->holds($user, $manager ? HatType::AssetManager : HatType::AssetOwner, $entity),
            $entity instanceof Region => $this->hats->holds($user, HatType::RegionOwner, $entity),
            default => false,
        };
    }
}

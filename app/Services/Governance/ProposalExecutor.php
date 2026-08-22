<?php

namespace App\Services\Governance;

use App\Enums\HatType;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Models\Asset;
use App\Models\GovernanceLock;
use App\Models\Hat;
use App\Models\Llc;
use App\Models\Proposal;
use App\Models\Region;
use App\Models\User;
use App\Services\Permissions\HatService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applying a decision that carried.
 *
 * **Guards always win.** Every branch here goes through the ordinary
 * services, so a vote is subject to the same invariants as any other actor:
 * it cannot strip a member's last membership, orphan an entity, or set a fee
 * above the cap.
 *
 * The prototype wrote state directly and escaped all three. Grants already
 * went through the proper handler, which is what makes the bypass in the
 * removal branches look like an oversight rather than a position anybody
 * took.
 *
 * A consequence: a proposal can now **pass its vote and still fail to
 * apply**. That is recorded with a reason rather than silently doing
 * nothing.
 */
class ProposalExecutor
{
    public function __construct(private readonly HatService $hats) {}

    public function execute(Proposal $proposal): void
    {
        DB::transaction(function () use ($proposal): void {
            try {
                $this->apply($proposal);
            } catch (RuntimeException $e) {
                // Passed the vote, refused at execution. Say so.
                $proposal->update([
                    'status' => ProposalStatus::Blocked,
                    'failure_reason' => $e->getMessage(),
                ]);

                return;
            }

            $this->placeLock($proposal);

            $proposal->update([
                'status' => ProposalStatus::Executed,
                'executed_at' => now(),
            ]);
        });
    }

    private function apply(Proposal $proposal): void
    {
        $payload = $proposal->action_payload ?? [];
        $entity = $proposal->governable;

        match ($proposal->type) {
            ProposalType::ChangeFee => $this->changeFee($entity, $payload),
            ProposalType::AddMember => $this->addMember($entity, $payload),
            ProposalType::RemoveMember => $this->removeMember($entity, $payload),
            ProposalType::GrantHat => $this->grantHat($entity, $payload),
            ProposalType::RevokeHat => $this->revokeHat($entity, $payload),
            ProposalType::ChangeGovernanceModel => $this->changeGovernanceModel($proposal, $payload),
            ProposalType::Repeal => $this->repeal($proposal),
            ProposalType::ChangeAssetSetting,
            ProposalType::ChangeDamageDeposit,
            ProposalType::ChangeInsurance,
            ProposalType::ChangeOverhead => $this->changeAssetSetting($entity, $payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function changeFee(mixed $entity, array $payload): void
    {
        $pct = (float) ($payload['fee_pct'] ?? 0);
        $max = (float) config('tribeshare.fees.max_percent');

        // The cap holds for a vote exactly as it holds for an owner. The
        // prototype clamped governance to 100% while the ordinary path
        // clamped to 10, so a vote could set a fee ten times the maximum.
        if ($pct < 0 || $pct > $max) {
            throw new RuntimeException("A booking fee may not exceed {$max}%; the proposal asked for {$pct}%.");
        }

        if (! $entity instanceof Llc && ! $entity instanceof Region) {
            throw new RuntimeException('Only an LLC or a region charges a booking fee.');
        }

        $entity->update([
            'booking_fee_pct' => $pct,
            ...(isset($payload['fee_min_cents']) ? ['booking_fee_min_cents' => (int) $payload['fee_min_cents']] : []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function addMember(mixed $entity, array $payload): void
    {
        $user = User::findOrFail((string) $payload['user_id']);

        $type = $entity instanceof Asset ? HatType::AssetPoolMember : HatType::LlcMember;

        $this->hats->grant($user, $type, $entity);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function removeMember(mixed $entity, array $payload): void
    {
        $type = $entity instanceof Asset ? HatType::AssetPoolMember : HatType::LlcMember;

        $hat = Hat::query()
            ->where('user_id', $payload['user_id'])
            ->where('type', $type)
            ->scopedStrictlyTo($entity)
            ->firstOrFail();

        // Through the guarded service — so a vote cannot strip a member's
        // last membership any more than an RCM can.
        $this->hats->revoke($hat);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function grantHat(mixed $entity, array $payload): void
    {
        $user = User::findOrFail((string) $payload['user_id']);
        $type = HatType::from($payload['hat_type']);

        $this->hats->grant($user, $type, $entity);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function revokeHat(mixed $entity, array $payload): void
    {
        $hat = Hat::query()
            ->where('user_id', $payload['user_id'])
            ->where('type', HatType::from($payload['hat_type']))
            ->scopedStrictlyTo($entity)
            ->firstOrFail();

        // Guarded: a vote cannot leave an entity without an owner.
        $this->hats->revoke($hat);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function changeGovernanceModel(Proposal $proposal, array $payload): void
    {
        $proposal->config->update($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function changeAssetSetting(mixed $entity, array $payload): void
    {
        if (! $entity instanceof Asset) {
            throw new RuntimeException('Only an asset carries settings.');
        }

        $field = $payload['field'] ?? null;

        if ($field === null) {
            throw new RuntimeException('The proposal did not name a field to change.');
        }

        // Only fields the asset genuinely exposes. The prototype wrote any
        // dotted path with no validation, including the pricing and
        // contribution fields other rules constrain.
        $allowed = [
            'no_cancel_minutes', 'bump_cutoff_minutes', 'min_book_ahead_mesos',
            'bookend_before_mesos', 'bookend_after_mesos', 'max_group_size',
            'allow_give_up', 'allow_event_hosting', 'allow_ride_hosting',
            'pool_closed', 'pool_approval_by_admins', 'auto_join_pool',
            'stated_value_cents', 'invisible',
        ];

        if (! in_array($field, $allowed, true)) {
            throw new RuntimeException("A proposal may not change \"{$field}\".");
        }

        $entity->update([$field => $payload['value']]);
    }

    private function repeal(Proposal $proposal): void
    {
        $target = $proposal->repealOf;

        if ($target === null) {
            throw new RuntimeException('The repeal does not name a proposal to undo.');
        }

        GovernanceLock::query()->where('proposal_id', $target->id)->delete();

        $target->update(['status' => ProposalStatus::Repealed]);
    }

    /**
     * Freeze a field, so the decision cannot be quietly reversed.
     */
    private function placeLock(Proposal $proposal): void
    {
        if ($proposal->locks_field === null) {
            return;
        }

        GovernanceLock::updateOrCreate(
            [
                'lockable_type' => $proposal->governable_type,
                'lockable_id' => $proposal->governable_id,
                'field' => $proposal->locks_field,
            ],
            [
                'proposal_id' => $proposal->id,
                'locked_at' => now(),
            ],
        );
    }
}

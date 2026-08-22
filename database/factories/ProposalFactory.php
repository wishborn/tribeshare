<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Models\GovernanceConfig;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    public function definition(): array
    {
        return [
            'governance_config_id' => GovernanceConfig::factory(),
            'governable_type' => 'llc',
            'governable_id' => null,
            'type' => ProposalType::ChangeFee,
            'title' => 'A proposal',
            'proposed_by' => User::factory(),
            'status' => ProposalStatus::Voting,
            'execution_delay_days' => 2,
            'action_payload' => ['fee_pct' => 5],
        ];
    }

    public function under(GovernanceConfig $config): static
    {
        return $this->state(fn () => [
            'governance_config_id' => $config->id,
            'governable_type' => $config->governable_type,
            'governable_id' => $config->governable_id,
        ]);
    }

    public function voting(): static
    {
        return $this->state(fn () => [
            'status' => ProposalStatus::Voting,
            'voting_opens_at' => now(),
            'voting_closes_at' => now()->addDays(7),
        ]);
    }

    public function status(ProposalStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\VotingModel;
use App\Models\GovernanceConfig;
use App\Models\Llc;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<GovernanceConfig>
 */
class GovernanceConfigFactory extends Factory
{
    protected $model = GovernanceConfig::class;

    public function definition(): array
    {
        return [
            'governable_type' => (new Llc)->getMorphClass(),
            'governable_id' => Llc::factory(),
            'enabled' => true,
        ];
    }

    public function governing(Model $entity): static
    {
        return $this->state(fn () => [
            'governable_type' => $entity->getMorphClass(),
            'governable_id' => $entity->getKey(),
        ]);
    }

    public function using(VotingModel $model): static
    {
        return $this->state(fn () => ['model' => $model]);
    }

    public function thresholds(float $quorumPct, float $passPct): static
    {
        return $this->state(fn () => ['quorum_pct' => $quorumPct, 'pass_pct' => $passPct]);
    }
}

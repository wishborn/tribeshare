<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'owner_type' => (new User)->getMorphClass(),
            'owner_id' => User::factory(),
            'direction' => LedgerDirection::Debit,
            'label' => LedgerLabel::AssetCharge,
            'amount_cents' => 10_00,
            'description' => fake()->words(3, true),
        ];
    }

    public function ownedBy(Model $owner): static
    {
        return $this->state(fn () => [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
    }

    public function charge(int $cents): static
    {
        return $this->state(fn () => [
            'direction' => LedgerDirection::Debit,
            'label' => LedgerLabel::AssetCharge,
            'amount_cents' => $cents,
        ]);
    }

    public function income(int $cents): static
    {
        return $this->state(fn () => [
            'direction' => LedgerDirection::Credit,
            'label' => LedgerLabel::AssetIncome,
            'amount_cents' => $cents,
        ]);
    }

    /**
     * Raised in the past, so ageing can be exercised without travelling.
     */
    public function agedDays(int $days): static
    {
        return $this->state(fn () => ['created_at' => now()->subDays($days)]);
    }
}

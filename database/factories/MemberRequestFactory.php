<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\MemberRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<MemberRequest>
 */
class MemberRequestFactory extends Factory
{
    protected $model = MemberRequest::class;

    public function definition(): array
    {
        return [
            'type' => RequestType::JoinLlc,
            'status' => RequestStatus::Pending,
            'requested_by' => User::factory(),
        ];
    }

    public function of(RequestType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function targeting(Model $target): static
    {
        return $this->state(fn () => [
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
        ]);
    }
}

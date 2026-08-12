<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutRequest>
 */
class PayoutRequestFactory extends Factory
{
    protected $model = PayoutRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount_cents' => 25_00,
            'status' => PayoutStatus::Pending,
            'requested_at' => now(),
        ];
    }
}

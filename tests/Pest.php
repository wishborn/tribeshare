<?php

use App\Enums\HatType;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Enums\VotingModel;
use App\Models\GovernanceConfig;
use App\Models\Llc;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Permissions\HatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Members of an LLC, and so eligible to vote on it.
 *
 * @return array<int, User>
 */
function members(Llc $llc, int $count): array
{
    return collect(range(1, $count))
        ->map(function () use ($llc) {
            $user = User::factory()->create();
            app(HatService::class)->grant($user, HatType::LlcMember, $llc);

            return $user;
        })
        ->all();
}

/**
 * A proposal open for voting under a given model and thresholds.
 */
function proposalUsing(Llc $llc, VotingModel $model, float $quorum = 50, float $pass = 60): Proposal
{
    $config = GovernanceConfig::factory()->governing($llc)->using($model)->thresholds($quorum, $pass)->create();

    return Proposal::factory()->under($config)->voting()->create();
}

/**
 * A proposal that has already carried and whose cooling-off has elapsed.
 *
 * @param  array<string, mixed>  $payload
 */
function carried(Model $entity, ProposalType $type, array $payload, ?string $locksField = null): Proposal
{
    $config = GovernanceConfig::firstOrCreate([
        'governable_type' => $entity->getMorphClass(),
        'governable_id' => $entity->getKey(),
    ], ['enabled' => true]);

    return Proposal::factory()->under($config)->create([
        'type' => $type,
        'status' => ProposalStatus::Passed,
        'action_payload' => $payload,
        'locks_field' => $locksField,
        'executes_at' => now()->subMinute(),
    ]);
}

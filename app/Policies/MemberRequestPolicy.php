<?php

namespace App\Policies;

use App\Enums\HatType;
use App\Enums\LlcPower;
use App\Models\Asset;
use App\Models\Llc;
use App\Models\MemberRequest;
use App\Models\User;
use App\Services\Permissions\HatService;
use App\Services\Permissions\PowerService;

/**
 * Who may act on a request.
 */
class MemberRequestPolicy
{
    public function __construct(
        private readonly PowerService $powers,
        private readonly HatService $hats,
    ) {}

    public function view(User $user, MemberRequest $request): bool
    {
        return $request->requested_by === $user->id || $this->resolve($user, $request);
    }

    /**
     * Only the member who raised a request may withdraw it.
     */
    public function withdraw(User $user, MemberRequest $request): bool
    {
        return $request->requested_by === $user->id && $request->status->isPending();
    }

    /**
     * Only someone with the relevant power may resolve one — and never the
     * member who raised it, however senior they are. Approving your own
     * request is not a decision.
     */
    public function resolve(User $user, MemberRequest $request): bool
    {
        if (! $request->status->isPending() || $request->requested_by === $user->id) {
            return false;
        }

        if ($user->isRcm()) {
            return true;
        }

        $llc = $this->llcFor($request);

        if ($llc === null) {
            return false;
        }

        return $this->powers->onLlc($user, $llc, LlcPower::ManageMembers)
            || $this->hats->holds($user, HatType::LlcOwner, $llc);
    }

    private function llcFor(MemberRequest $request): ?Llc
    {
        $target = $request->target;

        return match (true) {
            $target instanceof Llc => $target,
            $target instanceof Asset => $target->llc,
            default => null,
        };
    }
}

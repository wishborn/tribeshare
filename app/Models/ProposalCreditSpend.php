<?php

namespace App\Models;

use App\Enums\VoteDirection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credits a member put behind a direction.
 *
 * @property int $credits
 * @property VoteDirection $direction
 */
class ProposalCreditSpend extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['direction' => VoteDirection::class];
    }

    /**
     * The weight this spend carries.
     *
     * Square root, so influence costs its square — four credits buy twice
     * the say of one, not four times.
     */
    public function weight(): float
    {
        return sqrt($this->credits);
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

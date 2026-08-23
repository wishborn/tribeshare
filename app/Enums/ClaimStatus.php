<?php

namespace App\Enums;

/**
 * Where an insurance claim has got to.
 *
 * The prototype stored a claim as a document with a status string, which is
 * why it had no lifecycle worth the name. A claim is a thing that happens
 * over time; a document is not.
 */
enum ClaimStatus: string
{
    case Filed = 'filed';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Denied = 'denied';
    case Paid = 'paid';
    case Closed = 'closed';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Denied, self::Paid, self::Closed], true);
    }

    /**
     * Which statuses may follow this one.
     *
     * @return array<int, self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Filed => [self::UnderReview, self::Denied, self::Closed],
            self::UnderReview => [self::Approved, self::Denied, self::Closed],
            self::Approved => [self::Paid, self::Closed],
            self::Denied, self::Paid => [self::Closed],
            self::Closed => [],
        };
    }

    public function canBecome(self $next): bool
    {
        return in_array($next, $this->next(), true);
    }
}

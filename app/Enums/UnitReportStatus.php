<?php

namespace App\Enums;

enum UnitReportStatus: string
{
    case AwaitingSubmission = 'awaiting_submission';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
}

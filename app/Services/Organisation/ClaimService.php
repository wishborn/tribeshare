<?php

namespace App\Services\Organisation;

use App\Enums\ClaimStatus;
use App\Models\Region;
use App\Models\RegionClaim;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Insurance claims, through their life.
 *
 * A claim is its own concept, not a document with extra fields. The
 * prototype filed them inside the region's document library and moved them
 * along by overwriting a status string — so a claim could go from filed
 * straight to paid, and nothing recorded when or by whom.
 */
class ClaimService
{
    /**
     * File a claim.
     */
    public function file(
        Region $region,
        User $by,
        string $title,
        CarbonInterface $incidentOn,
        int $claimedCents = 0,
        ?string $description = null,
        ?string $reference = null,
        ?Model $subject = null,
    ): RegionClaim {
        return DB::transaction(function () use ($region, $by, $title, $incidentOn, $claimedCents, $description, $reference, $subject): RegionClaim {
            $claim = RegionClaim::create([
                'region_id' => $region->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'reference' => $reference,
                'title' => $title,
                'description' => $description,
                'status' => ClaimStatus::Filed,
                'incident_on' => $incidentOn,
                'filed_on' => now(),
                'claimed_cents' => $claimedCents,
                'filed_by' => $by->id,
            ]);

            $this->record($claim, null, ClaimStatus::Filed, $by, 'Filed.');

            return $claim;
        });
    }

    /**
     * Move a claim along, refusing a step it cannot take.
     *
     * @throws RuntimeException when the transition is not one this status
     *                          allows
     */
    public function advance(
        RegionClaim $claim,
        ClaimStatus $to,
        User $by,
        ?string $note = null,
        ?int $settledCents = null,
    ): RegionClaim {
        if (! $claim->status->canBecome($to)) {
            throw new RuntimeException(
                "A claim that is {$claim->status->value} cannot become {$to->value}."
            );
        }

        return DB::transaction(function () use ($claim, $to, $by, $note, $settledCents): RegionClaim {
            $from = $claim->status;

            $claim->update([
                'status' => $to,
                'settled_cents' => $to === ClaimStatus::Paid ? ($settledCents ?? $claim->claimed_cents) : $claim->settled_cents,
                'settled_on' => $to->isOpen() ? $claim->settled_on : now(),
            ]);

            $this->record($claim, $from, $to, $by, $note);

            return $claim->refresh();
        });
    }

    /**
     * Attach a document from the region's library to a claim.
     */
    public function attachDocument(RegionClaim $claim, string $documentId): void
    {
        $claim->documents()->syncWithoutDetaching([$documentId]);
    }

    private function record(RegionClaim $claim, ?ClaimStatus $from, ClaimStatus $to, User $by, ?string $note): void
    {
        $claim->events()->create([
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'recorded_by' => $by->id,
        ]);
    }
}

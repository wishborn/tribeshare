<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ClaimStatus;
use App\Enums\HatType;
use App\Enums\LedgerDirection;
use App\Enums\LedgerLabel;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Enums\RegionDocumentCategory;
use App\Enums\RequestType;
use App\Enums\UnitReportStatus;
use App\Enums\VoteDirection;
use App\Enums\VotingModel;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Hat;
use App\Models\LedgerEntry;
use App\Models\Llc;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Proposal;
use App\Models\Region;
use App\Models\RegionDocument;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Governance\GovernanceService;
use App\Services\Governance\ProposalExecutor;
use App\Services\Ledger\LedgerService;
use App\Services\Messaging\MessagingService;
use App\Services\Organisation\ClaimService;
use App\Services\Organisation\OffboardingService;
use App\Services\Requests\RequestService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * The demo dataset — run on every rebuild for local browser testing.
 *
 * Everything is authored RELATIVE TO NOW. There is deliberately no
 * runtime-adjustable clock (time travel lives in tests only), so every state
 * worth seeing has to be visible the moment this finishes: charges at every
 * age from fresh to overdue, bookings in every status, an overnight stay, a
 * suspended member, credit both matured and still maturing.
 *
 * Bookings go through BookingService rather than the factory, so the ledger
 * is populated by the same code the application uses.
 */
class TribeShareDemoSeeder extends Seeder
{
    public function run(): void
    {
        $bookings = app(BookingService::class);
        $ledger = app(LedgerService::class);

        // --- Org structure ----------------------------------------------
        $region = Region::create([
            'name' => 'Wisconsin',
            'icon' => '🌊',
            'description' => 'The founding region.',
            'booking_fee_pct' => 5,
            'booking_fee_min_cents' => 2_00,
        ]);

        $northwoods = Llc::create([
            'region_id' => $region->id,
            'name' => 'Northwoods Collective',
            'icon' => '🌲',
            'booking_fee_pct' => 10,
        ]);

        $lakeside = Llc::create([
            'region_id' => $region->id,
            'name' => 'Lakeside Holdings',
            'icon' => '⛵',
            'booking_fee_pct' => 7,
            'booking_fee_min_cents' => 1_50,
        ]);

        // --- People ------------------------------------------------------
        $rcm = $this->member('Regional Controller', 'rcm@tribeshare.test');
        Hat::create(['user_id' => $rcm->id, 'type' => HatType::Rcm]);

        $ada = $this->member('Ada Whitfield', 'ada@tribeshare.test');
        $ben = $this->member('Ben Okoro', 'ben@tribeshare.test');
        $cleo = $this->member('Cleo Marchetti', 'cleo@tribeshare.test');
        $dev = $this->member('Dev Ramanathan', 'dev@tribeshare.test');

        // Faye holds no assets and earns nothing, which is what makes her a
        // usable example of a suspended member: an asset owner's income would
        // be allocated against the charge and settle it.
        $faye = $this->member('Faye Lindqvist', 'faye@tribeshare.test');

        // Ada owns the cabin and manages the boat; Ben owns the truck.
        foreach ([$ada, $ben, $cleo, $dev, $faye] as $member) {
            Hat::create([
                'user_id' => $member->id,
                'type' => HatType::RegionalMember,
                'scopeable_type' => $region->getMorphClass(),
                'scopeable_id' => $region->id,
            ]);
        }

        foreach ([$ada, $ben, $cleo] as $member) {
            Hat::create([
                'user_id' => $member->id,
                'type' => HatType::LlcMember,
                'scopeable_type' => $northwoods->getMorphClass(),
                'scopeable_id' => $northwoods->id,
            ]);
        }

        Hat::create([
            'user_id' => $ada->id,
            'type' => HatType::LlcOwner,
            'scopeable_type' => $northwoods->getMorphClass(),
            'scopeable_id' => $northwoods->id,
        ]);

        Hat::create([
            'user_id' => $dev->id,
            'type' => HatType::LlcMember,
            'scopeable_type' => $lakeside->getMorphClass(),
            'scopeable_id' => $lakeside->id,
        ]);

        // --- Assets ------------------------------------------------------
        $cabin = Asset::create([
            'llc_id' => $northwoods->id,
            'main_owner_id' => $ada->id,
            'name' => 'Birchwood Cabin',
            'type' => 'cabin',
            'settings' => [
                'voluntary_contrib_llc_pct' => 10,
                'voluntary_contrib_region_pct' => 5,
                'no_cancel_minutes' => 1440,
            ],
        ]);

        $boat = Asset::create([
            'llc_id' => $lakeside->id,
            'main_owner_id' => $dev->id,
            'name' => 'Serenity (24ft)',
            'type' => 'boat',
            'settings' => [
                'group_price_mode' => 'premium',
                'group_premium_cents' => 15_00,
            ],
        ]);

        $truck = Asset::create([
            'llc_id' => $northwoods->id,
            'main_owner_id' => $ben->id,
            'name' => 'Workhorse F-250',
            'type' => 'vehicle',
            'settings' => [
                // Metered: usage is reported after the booking completes.
                'unit_prices' => [['unit' => 'mile', 'price_cents' => 65]],
            ],
        ]);

        $cabin->poolMembers()->attach([$ben->id, $cleo->id]);
        // Ben and Cleo are in Northwoods, not Lakeside — pool membership is
        // what gives them access to the boat across the LLC boundary.
        $boat->poolMembers()->attach([$ada->id, $ben->id, $cleo->id]);
        $truck->poolMembers()->attach([$ada->id, $cleo->id, $dev->id]);

        Hat::create([
            'user_id' => $ada->id,
            'type' => HatType::AssetOwner,
            'scopeable_type' => $cabin->getMorphClass(),
            'scopeable_id' => $cabin->id,
        ]);

        Hat::create([
            'user_id' => $ben->id,
            'type' => HatType::AssetManager,
            'scopeable_type' => $cabin->getMorphClass(),
            'scopeable_id' => $cabin->id,
        ]);

        // --- Bookings, across every status --------------------------------
        // Past: completed, aged past its due date but still inside the grace
        // period — so it reads as "due" without suspending Cleo. Faye is the
        // suspended member; pushing this past 21 days would refuse every
        // later booking Cleo makes.
        $past = $bookings->book(
            user: $cleo, asset: $cabin,
            startsAt: $this->at(-16, 14), endsAt: $this->at(-16, 18),
            basePriceCents: 120_00, slotType: 'afternoon',
        );
        $past->update(['status' => BookingStatus::Completed]);
        $this->ageEntriesOf($past, 16);

        // Recent: completed a few days ago, charge still comfortably current.
        $recent = $bookings->book(
            user: $ben, asset: $boat,
            startsAt: $this->at(-4, 9), endsAt: $this->at(-4, 17),
            basePriceCents: 80_00, slotType: 'day',
        );
        $recent->update(['status' => BookingStatus::Completed]);
        $this->ageEntriesOf($recent, 4);

        // Metered: completed, awaiting a usage report.
        $metered = $bookings->book(
            user: $cleo, asset: $truck,
            startsAt: $this->at(-2, 8), endsAt: $this->at(-2, 12),
            basePriceCents: 40_00, slotType: 'morning',
        );
        $metered->update(['status' => BookingStatus::Completed]);
        $metered->unitReport()->create([
            'status' => UnitReportStatus::AwaitingSubmission,
            'suggested_charge_cents' => 0,
        ]);

        // In flight right now.
        $bookings->book(
            user: $ben, asset: $truck,
            startsAt: now()->subHour(), endsAt: now()->addHours(3),
            basePriceCents: 40_00, slotType: 'morning',
        )->update(['status' => BookingStatus::Active]);

        // Upcoming, confirmed.
        $bookings->book(
            user: $cleo, asset: $cabin,
            startsAt: $this->at(6, 15), endsAt: $this->at(6, 20),
            basePriceCents: 120_00, slotType: 'afternoon',
        );

        // Upcoming, awaiting the owner's approval. Cleo, not Ben — Ben
        // manages the cabin, so his own bookings confirm immediately.
        $bookings->book(
            user: $cleo, asset: $cabin,
            startsAt: $this->at(9, 10), endsAt: $this->at(9, 16),
            basePriceCents: 120_00, slotType: 'day',
            requiresApproval: true,
        );

        // An overnight stay — the thing the prototype could not express.
        $overnight = $bookings->book(
            user: $ada, asset: $boat,
            startsAt: $this->at(12, 20), endsAt: $this->at(13, 8),
            basePriceCents: 180_00, slotType: 'overnight',
        );

        // Offered up, still unclaimed.
        $overnight->offer()->create([
            'offered_at' => now()->subHours(6),
            'giver_pct' => 0,
            'picker_pct' => 100,
        ]);

        // --- Money, at every stage ---------------------------------------
        // Ben settles up in full.
        Payment::create([
            'user_id' => $ben->id,
            'amount_cents' => 100_00,
            'status' => PaymentStatus::Confirmed,
            'submitted_at' => now()->subDays(3),
            'confirmed_at' => now()->subDays(2),
            'confirmed_by' => $rcm->id,
            'note' => 'Bank transfer',
        ]);

        // Cleo has a payment waiting on an RCM.
        Payment::create([
            'user_id' => $cleo->id,
            'amount_cents' => 60_00,
            'claimed_amount_cents' => 60_00,
            'status' => PaymentStatus::Pending,
            'submitted_at' => now()->subHours(20),
            'note' => 'Cheque posted Monday',
        ]);

        // Faye is genuinely overdue: raised 26 days ago, so past its 14-day
        // due date plus the 7-day grace period, with no income to allocate
        // against it. The flag below only mirrors what the ledger already
        // says — suspension is derived, never declared.
        LedgerEntry::create([
            'owner_type' => $faye->getMorphClass(),
            'owner_id' => $faye->id,
            'direction' => LedgerDirection::Debit,
            'label' => LedgerLabel::AssetCharge,
            'amount_cents' => 95_00,
            'description' => 'Serenity (24ft) — winter haul-out',
            'created_at' => now()->subDays(26),
            'due_at' => now()->subDays(12),
        ]);
        $faye->update(['billing_suspended' => true]);

        // Ada has credit: some matured and payable, some still locked.
        LedgerEntry::create([
            'owner_type' => $ada->getMorphClass(),
            'owner_id' => $ada->id,
            'direction' => LedgerDirection::Credit,
            'label' => LedgerLabel::AssetIncome,
            'amount_cents' => 45_00,
            'description' => 'Income: Birchwood Cabin',
            'created_at' => now()->subDays(11),
        ]);

        LedgerEntry::create([
            'owner_type' => $ada->getMorphClass(),
            'owner_id' => $ada->id,
            'direction' => LedgerDirection::Credit,
            'label' => LedgerLabel::AssetIncome,
            'amount_cents' => 30_00,
            'description' => 'Income: Birchwood Cabin',
            'created_at' => now()->subDays(2),
        ]);

        // Cleo asked for her credit back and is waiting on a decision.
        PayoutRequest::create([
            'user_id' => $cleo->id,
            'amount_cents' => 18_00,
            'status' => PayoutStatus::Pending,
            'requested_at' => now()->subDay(),
        ]);

        // A charge raised against the wrong member, then corrected. The
        // ledger keeps both rows — the mistake and its reversal — because
        // history is never rewritten.
        $misposted = LedgerEntry::create([
            'owner_type' => $ben->getMorphClass(),
            'owner_id' => $ben->id,
            'direction' => LedgerDirection::Debit,
            'label' => LedgerLabel::AssetCharge,
            'amount_cents' => 55_00,
            'description' => 'Serenity (24ft) — mooring fee',
            'created_at' => now()->subDays(9),
        ]);

        $ledger->reverse($misposted, 'Charged to the wrong member', $rcm);

        // --- Governance, at every stage ----------------------------------
        $this->seedGovernance($northwoods, $cabin, $ada, $ben, $cleo);

        // --- Conversations, requests, claims and a departure --------------
        $this->seedCommunications($northwoods, $cabin, $ada, $ben, $cleo, $rcm);
        $this->seedRequests($northwoods, $lakeside, $cabin, $ada, $dev, $faye);
        $this->seedRegionLibrary($region, $cabin, $rcm);
        $this->seedOffboarding($lakeside, $dev);

        $this->command->info(
            'Seeded 1 region, 2 LLCs, 3 assets, 6 members, 7 bookings, 4 proposals, '
            .'3 conversations, 3 requests, 2 claims and a queued departure.'
        );
    }

    /**
     * Governance in every state worth looking at: one gathering signatures,
     * one open with votes already cast, one that carried and is serving out
     * its cooling-off, and one that carried but was refused at execution.
     *
     * That last state only exists because guards always win, so the demo
     * shows it rather than leaving it to a test.
     */
    private function seedGovernance(Llc $llc, Asset $asset, User $ada, User $ben, User $cleo): void
    {
        $governance = app(GovernanceService::class);

        $config = $governance->configFor($llc);
        $config->update([
            'enabled' => true,
            'model' => VotingModel::OneMemberOneVote,
            'quorum_pct' => 50,
            'pass_pct' => 60,
            'who_can_propose' => 'members',
        ]);

        // Gathering signatures - one of three members so far.
        $petition = $governance->propose(
            $cleo, $llc, ProposalType::ChangeAssetSetting,
            'Shorten the cancellation window on the cabin',
            ['field' => 'no_cancel_minutes', 'value' => 720],
            ProposalStatus::Petition,
        );
        $petition->signatures()->create(['user_id' => $cleo->id]);

        // Open, with two of three heard from - enough for quorum, and short
        // of the turnout that would settle it early.
        $open = $governance->propose(
            $ada, $llc, ProposalType::ChangeFee,
            'Lower the booking fee to 8%',
            ['fee_pct' => 8],
            ProposalStatus::Voting,
        );
        $governance->castVote($open, $ada, VoteDirection::Yes);
        $governance->castVote($open->refresh(), $ben, VoteDirection::Abstain);

        // Carried, waiting out its cooling-off, and locking the field it
        // settles so an owner cannot quietly reverse it.
        Proposal::create([
            'governance_config_id' => $config->id,
            'governable_type' => $asset->getMorphClass(),
            'governable_id' => $asset->id,
            'type' => ProposalType::ChangeAssetSetting,
            'title' => 'Raise the cabin group limit to eight',
            'proposed_by' => $ben->id,
            'status' => ProposalStatus::Passed,
            'execution_delay_days' => 2,
            'action_payload' => ['field' => 'max_group_size', 'value' => 8],
            'locks_field' => 'max_group_size',
            'voting_opens_at' => now()->subDays(8),
            'voting_closes_at' => now()->subDay(),
            'executes_at' => now()->addDay(),
        ]);

        // Passed its vote and refused at execution: Cleo belongs to this LLC
        // and nowhere else, so removing her would leave her with no
        // membership. Nobody may do that, a vote included.
        $refused = Proposal::create([
            'governance_config_id' => $config->id,
            'governable_type' => $llc->getMorphClass(),
            'governable_id' => $llc->id,
            'type' => ProposalType::RemoveMember,
            'title' => 'Remove Cleo Marchetti from the collective',
            'proposed_by' => $ben->id,
            'status' => ProposalStatus::Passed,
            'execution_delay_days' => 2,
            'action_payload' => ['user_id' => $cleo->id],
            'voting_opens_at' => now()->subDays(10),
            'voting_closes_at' => now()->subDays(3),
            'executes_at' => now()->subDay(),
        ]);

        app(ProposalExecutor::class)->execute($refused);
    }

    /**
     * Threads worth opening: a direct exchange, a group with an attachment
     * left unread, and one the recipient has archived.
     */
    private function seedCommunications(Llc $llc, Asset $asset, User $ada, User $ben, User $cleo, User $rcm): void
    {
        $messaging = app(MessagingService::class);

        $direct = $messaging->openDirect($ada, $ben);
        $messaging->send($direct, $ada, 'Are you taking the truck up on Saturday?');
        $messaging->send($direct->refresh(), $ben, 'Planning to. I can bring the trailer.');

        // Unread for Cleo, so the messages badge has something to show.
        $group = $messaging->openGroup($ada, [$ben, $cleo], 'Cabin weekend', $asset);
        $messaging->send($group, $ada, 'Bringing the good coffee. Anyone need a lift?');

        // Archived by one member only — it stays in everyone else's list.
        $withRcm = $messaging->openDirect($cleo, $rcm);
        $messaging->send($withRcm, $cleo, 'Could you look at the mooring charge on my account?');
        $messaging->archive($withRcm->refresh(), $rcm);
    }

    /**
     * The approval queue, with one of each outcome still visible.
     */
    private function seedRequests(Llc $northwoods, Llc $lakeside, Asset $cabin, User $ada, User $dev, User $faye): void
    {
        $requests = app(RequestService::class);

        // Waiting on a decision: Dev wants into Northwoods.
        $requests->raise($dev, RequestType::JoinLlc, $northwoods, 'I am up that way most weekends.');

        // Waiting on a decision: Faye wants access to the cabin pool.
        $requests->raise($faye, RequestType::JoinPool, $cabin, 'Happy to help with upkeep.');

        // Resolved, so the history is not uniformly pending.
        $settled = $requests->raise($faye, RequestType::JoinLlc, $lakeside, 'Closer to home.');
        $requests->approve($settled, $ada, 'Welcome aboard.');
    }

    /**
     * A region's paperwork, and two claims at different stages.
     */
    private function seedRegionLibrary(Region $region, Asset $asset, User $rcm): void
    {
        $claims = app(ClaimService::class);

        $policy = RegionDocument::create([
            'region_id' => $region->id,
            'category' => RegionDocumentCategory::Insurance,
            'title' => 'Master liability policy 2026',
            'path' => 'documents/policy-2026.pdf',
            'original_name' => 'policy-2026.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $rcm->id,
        ]);

        RegionDocument::create([
            'region_id' => $region->id,
            'category' => RegionDocumentCategory::Contracts,
            'title' => 'Boat slip lease',
            'path' => 'documents/slip-lease.pdf',
            'original_name' => 'slip-lease.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $rcm->id,
        ]);

        // Open, and part-way through.
        $open = $claims->file(
            $region, $rcm,
            'Storm damage to the cabin roof',
            now()->subWeeks(3),
            claimedCents: 4_500_00,
            subject: $asset,
        );
        $claims->advance($open, ClaimStatus::UnderReview, $rcm, 'Adjuster booked.');
        $claims->attachDocument($open->refresh(), $policy->id);

        // Settled for less than was claimed, which is the usual outcome.
        $paid = $claims->file(
            $region, $rcm,
            'Trailer hitch replacement',
            now()->subMonths(4),
            claimedCents: 800_00,
        );
        $claims->advance($paid, ClaimStatus::UnderReview, $rcm);
        $claims->advance($paid->refresh(), ClaimStatus::Approved, $rcm);
        $claims->advance($paid->refresh(), ClaimStatus::Paid, $rcm, 'Settled.', settledCents: 640_00);
    }

    /**
     * A departure waiting on its obligations.
     *
     * Dev has an unpaid balance, so the queue is visible and stationary —
     * which is the interesting state, not the one that fires immediately.
     */
    private function seedOffboarding(Llc $llc, User $dev): void
    {
        app(OffboardingService::class)->queueLeave($dev, $llc);
    }

    private function member(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * A time relative to today, so the demo always straddles "now".
     */
    private function at(int $daysFromNow, int $hour): CarbonInterface
    {
        return now()->addDays($daysFromNow)->setTime($hour, 0);
    }

    /**
     * Backdate a booking's ledger entries so charge ageing is visible.
     *
     * Entries are immutable through the model, so this is a deliberate
     * seed-time write straight to the table — never something application
     * code should do.
     */
    private function ageEntriesOf(Booking $booking, int $daysAgo): void
    {
        $createdAt = now()->subDays($daysAgo);

        LedgerEntry::withoutEvents(function () use ($booking, $createdAt): void {
            LedgerEntry::query()
                ->where('booking_id', $booking->id)
                ->update([
                    'created_at' => $createdAt,
                    'month_key' => $createdAt->format('Y-m'),
                    'due_at' => $createdAt->copy()->addDays((int) config('tribeshare.billing.due_days')),
                ]);
        });
    }
}

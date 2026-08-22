<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Everything read on the booking path comes out of the JSON blob
            // and becomes a column. Presentation and assurance fields stay in
            // `settings` — nothing on a hot path reads them.

            // --- Lifecycle ------------------------------------------------
            // draft -> pending -> approved
            $table->string('status')->default('approved')->after('type');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            // Settings edited but not yet live. Publishing promotes these and
            // grandfathers existing bookings onto the terms they were made
            // under.
            $table->json('draft_settings')->nullable();

            // --- Booking rules --------------------------------------------
            $table->unsignedInteger('no_cancel_minutes')->default(1440);
            $table->unsignedInteger('bump_cutoff_minutes')->default(1440);
            $table->unsignedSmallInteger('min_book_ahead_mesos')->default(0);
            $table->unsignedSmallInteger('bookend_before_mesos')->default(0);
            $table->unsignedSmallInteger('bookend_after_mesos')->default(0);
            $table->unsignedSmallInteger('max_group_size')->default(1);

            // --- Group pricing --------------------------------------------
            // none | multiplier | premium
            $table->string('group_price_mode')->default('none');
            $table->decimal('group_multiplier', 6, 3)->default(1);
            $table->unsignedBigInteger('group_premium_cents')->default(0);

            // --- Voluntary contributions ----------------------------------
            // Redirected out of the owner's income. Their sum may never
            // exceed 100, or a booking would credit more than it debits —
            // validated on write rather than clamped at posting time.
            $table->decimal('voluntary_contrib_llc_pct', 5, 2)->default(0);
            $table->decimal('voluntary_contrib_region_pct', 5, 2)->default(0);

            // --- Offer-up --------------------------------------------------
            $table->boolean('allow_give_up')->default(true);
            $table->decimal('offer_giver_pct', 5, 2)->default(0);
            $table->decimal('offer_picker_pct', 5, 2)->default(100);
            $table->unsignedInteger('offer_retract_minutes')->nullable();

            // --- Pool ------------------------------------------------------
            $table->boolean('pool_closed')->default(false);
            $table->boolean('pool_approval_by_admins')->default(true);
            $table->boolean('auto_join_pool')->default(false);

            // --- Hosting ---------------------------------------------------
            $table->boolean('allow_event_hosting')->default(false);
            $table->boolean('allow_ride_hosting')->default(false);

            // --- Misc ------------------------------------------------------
            $table->unsignedBigInteger('stated_value_cents')->default(0);
            $table->boolean('invisible')->default(false);

            // `person` assets price by service type rather than slot, and
            // default to a different offer-up split.
            $table->string('subtype')->default('standard');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'approved_at', 'verified_at', 'draft_settings',
                'no_cancel_minutes', 'bump_cutoff_minutes', 'min_book_ahead_mesos',
                'bookend_before_mesos', 'bookend_after_mesos', 'max_group_size',
                'group_price_mode', 'group_multiplier', 'group_premium_cents',
                'voluntary_contrib_llc_pct', 'voluntary_contrib_region_pct',
                'allow_give_up', 'offer_giver_pct', 'offer_picker_pct', 'offer_retract_minutes',
                'pool_closed', 'pool_approval_by_admins', 'auto_join_pool',
                'allow_event_hosting', 'allow_ride_hosting',
                'stated_value_cents', 'invisible', 'subtype',
            ]);
        });
    }
};

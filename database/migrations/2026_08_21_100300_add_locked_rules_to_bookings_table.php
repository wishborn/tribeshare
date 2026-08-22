<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Grandfathering.
            //
            // Publishing an asset's settings snapshots the terms live
            // bookings were made under, so changing the cancellation window
            // or the offer-up split cannot retroactively alter a booking
            // somebody already holds.
            //
            // The prototype kept these in an opaque `lockedRules` blob; they
            // are columns here because the cancellation path reads them.
            $table->unsignedInteger('locked_no_cancel_minutes')->nullable();
            $table->decimal('locked_offer_giver_pct', 5, 2)->nullable();
            $table->decimal('locked_offer_picker_pct', 5, 2)->nullable();
            $table->boolean('locked_allow_give_up')->nullable();
            $table->timestamp('rules_locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'locked_no_cancel_minutes',
                'locked_offer_giver_pct',
                'locked_offer_picker_pct',
                'locked_allow_give_up',
                'rules_locked_at',
            ]);
        });
    }
};

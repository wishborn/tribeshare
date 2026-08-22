<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What is true of a slice of a day.
        //
        // Stored as RANGES, not one row per meso. A fully ruled 31-day month
        // would otherwise be 7,440 rows per asset, and authoring is
        // range-based anyway — contiguous mesos overwhelmingly share a rule.
        // Expansion to individual mesos happens when reading, if at all.
        Schema::create('calendar_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete();

            // Day of month, and a half-open meso range within it (0..240).
            $table->unsignedTinyInteger('day');
            $table->unsignedSmallInteger('meso_start');
            $table->unsignedSmallInteger('meso_end');

            // Rules opt slices IN: a meso with no rule is not bookable, so an
            // unpublished or unruled month sells nothing by accident. This
            // flag exists to close a slice that a wider rule opened.
            $table->boolean('bookable')->default(true);

            // 100 means normal price. 150 adds half again, 50 halves it.
            //
            // NOT an uplift — the prototype's calculator read it as
            // `base * (1 + m/100)` while its own authoring default was 100,
            // so every slot saved at the default silently priced at double.
            $table->decimal('price_multiplier_pct', 6, 2)->default(100);

            $table->unsignedSmallInteger('min_book_ahead_mesos')->nullable();

            // Offer-up split override for this slice, when set. Null inherits
            // the asset's.
            $table->decimal('offer_giver_pct', 5, 2)->nullable();
            $table->decimal('offer_picker_pct', 5, 2)->nullable();

            // Which slot durations may be used here. Null means all enabled.
            $table->json('allowed_slot_types')->nullable();

            // Rules are edited as a draft and published together. One
            // mechanism, shared with the asset's own settings draft.
            $table->boolean('draft')->default(false);

            $table->timestamps();

            $table->index(['calendar_id', 'draft', 'day'], 'calendar_rules_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_rules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('asset_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();

            // Snapshot, not a shortcut for a join: an asset can move between
            // LLCs, and a booking is a financial record that must stay
            // explicable on its own terms.
            $table->foreignUuid('llc_id')->constrained()->restrictOnDelete();

            $table->foreignUuid('booking_group_id')->nullable()
                ->constrained()->nullOnDelete();

            // Multi-item assets. No FK yet — collection items are modelled
            // in the assets pass.
            $table->uuid('collection_item_id')->nullable();

            // --- Time -------------------------------------------------------
            // Absolute instants rather than (date, start meso, end meso), so
            // a booking can span midnight. ends_at is stored rather than
            // derived so overlap is a plain indexed range comparison.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // Redundant with the pair above, but pricing and allocation are
            // expressed in mesos and recomputing this everywhere is noise.
            $table->unsignedSmallInteger('duration_mesos');

            // --- Status -----------------------------------------------------
            // pending | confirmed | active | completed | denied | cancelled | bumped
            $table->string('status')->default('pending');

            // Snapshot of the holder's booking priority (1 pool member … 5
            // RCM) at creation. Drives who may bump whom.
            $table->unsignedTinyInteger('priority')->default(1);

            $table->string('slot_type')->nullable();
            $table->string('service_type')->nullable();

            // --- Pricing snapshot -------------------------------------------
            // Every input to the price is frozen here. Asset prices, calendar
            // multipliers and fee rates all change underneath; a booking must
            // always be able to explain its own total.
            $table->unsignedBigInteger('base_price_cents')->default(0);
            $table->decimal('price_multiplier_pct', 6, 2)->default(0);
            $table->unsignedBigInteger('per_person_cents')->default(0);
            $table->unsignedBigInteger('fee_base_cents')->default(0);

            $table->decimal('llc_fee_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('llc_fee_min_cents')->default(0);
            $table->unsignedBigInteger('llc_fee_cents')->default(0);

            $table->decimal('region_fee_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('region_fee_min_cents')->default(0);
            $table->unsignedBigInteger('region_fee_cents')->default(0);

            $table->unsignedBigInteger('total_cents')->default(0);

            // --- Bumping ----------------------------------------------------
            $table->foreignUuid('bumped_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('bumped_at')->nullable();

            // This booking displaced others when it was made.
            $table->boolean('bullied')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Overlap detection scans this.
            $table->index(['asset_id', 'starts_at', 'ends_at']);
            // The scheduled sweep scans this.
            $table->index(['status', 'starts_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

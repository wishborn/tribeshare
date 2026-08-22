<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which durations an asset offers, and what each costs.
        //
        // **This is where a booking's base price comes from.** It was buried
        // in the settings blob, which is why the prototype's own pricing
        // helper — the one that looked authoritative — was dead code that
        // never read it.
        Schema::create('asset_slot_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();

            // 12min, 1hr, 1day, 1week, 1month, ...
            $table->string('key');
            $table->string('label')->nullable();

            // The catalogue runs from 2 mesos to 7,200 — a month. Durations
            // beyond a day were unusable while a booking sat on one date.
            $table->unsignedInteger('duration_mesos');

            $table->unsignedBigInteger('price_cents')->default(0);
            $table->boolean('enabled')->default(true);
            $table->boolean('approval_required')->default(false);
            $table->boolean('bump_allowed')->default(true);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['asset_id', 'key']);
            $table->index(['asset_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_slot_types');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a Manager or Admin may do on a given LLC or asset.
        //
        // Owners always hold every power, so they are never rows here — only
        // the two delegated tiers are. Each entity overrides its own table;
        // absence means the shipped default applies.
        //
        // A table rather than twenty boolean columns: the set differs between
        // LLCs (8) and assets (12), it is read on every authorization check,
        // and it has to be queryable.
        Schema::create('delegated_powers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The LLC or asset whose table this is.
            $table->uuidMorphs('powerable');

            // manager | admin
            $table->string('tier');

            // e.g. canApproveBookings, canAssignHats
            $table->string('power');

            $table->boolean('granted');

            $table->timestamps();

            $table->unique(['powerable_type', 'powerable_id', 'tier', 'power'], 'delegated_powers_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegated_powers');
    }
};

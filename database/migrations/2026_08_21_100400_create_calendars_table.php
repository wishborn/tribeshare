<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One month of availability for one schedulable thing.
        //
        // Polymorphic because collection items schedule separately from the
        // asset that holds them — though the prototype's publish overwrote
        // every item's calendar with the parent's, which is not reproduced.
        Schema::create('calendars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('schedulable');

            // YYYY-MM
            $table->char('month', 7);

            // An unpublished month is not bookable at all. Explicit publish
            // and unpublish rather than the prototype's toggle, which flipped
            // whichever way it happened to be pointing.
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('published_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['schedulable_type', 'schedulable_id', 'month'], 'calendars_unique_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};

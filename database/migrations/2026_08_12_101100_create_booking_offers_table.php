<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();

            $table->dateTime('offered_at');

            // Snapshotted at offer time. The applicable split depends on the
            // asset AND on the calendar rules in force for the booking's
            // starting meso, so a later config change must not silently
            // reprice an offer already on the table.
            $table->decimal('giver_pct', 5, 2)->default(0);
            $table->decimal('picker_pct', 5, 2)->default(100);

            $table->foreignUuid('picked_up_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('picked_up_at')->nullable();
            $table->dateTime('retracted_at')->nullable();

            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_offers');
    }
};

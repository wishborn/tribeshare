<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A booking may be hosted as an event others attend. The prototype
        // held this as a dozen nullable columns on the booking, which is why
        // its cancellation logic has to special-case three shapes.
        Schema::create('booking_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('host_user_id')->constrained('users')->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('host_fee_cents')->default(0);

            // When the host cancels, the event dissolves and attendees are
            // notified — but the underlying booking survives as a normal
            // cancelled booking.
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->unique('booking_id');
        });

        Schema::create('booking_event_attendees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_event_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // going | maybe | declined
            $table->string('rsvp_status')->default('going');
            $table->dateTime('left_at')->nullable();

            $table->timestamps();

            $table->unique(['booking_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_event_attendees');
        Schema::dropIfExists('booking_events');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A vehicle booking may be converted into a ride that others join,
        // with mileage logged against it.
        Schema::create('booking_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('driver_user_id')->constrained('users')->restrictOnDelete();

            $table->unsignedInteger('miles_logged')->default(0);
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->unique('booking_id');
        });

        Schema::create('booking_ride_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_ride_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();

            $table->timestamps();

            $table->unique(['booking_ride_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_ride_passengers');
        Schema::dropIfExists('booking_rides');
    }
};

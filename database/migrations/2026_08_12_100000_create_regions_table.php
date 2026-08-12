<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();

            // Hidden regions are visible to RCMs only.
            $table->boolean('visible')->default(true);

            // Charged per person as max(feeBase * pct, min).
            $table->decimal('booking_fee_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('booking_fee_min_cents')->default(0);

            // A region queued for retirement freezes booking on every asset
            // beneath it, so the booking guard walks asset -> llc -> region.
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('queued_for_retirement_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};

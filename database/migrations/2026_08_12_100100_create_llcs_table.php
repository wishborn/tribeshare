<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llcs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('region_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();

            // Fees mirror regions exactly: percentage plus optional flat
            // minimum. The prototype gave the minimum to regions only; the
            // two are unified here so both use one code path.
            $table->decimal('booking_fee_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('booking_fee_min_cents')->default(0);

            // Delegated manager/admin power tables. Stays JSON until the
            // permissions pass models it properly.
            $table->json('settings')->nullable();

            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('queued_for_retirement_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('region_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llcs');
    }
};

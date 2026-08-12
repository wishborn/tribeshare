<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // equal | custom
            $table->string('split_mode')->default('equal');

            // Under a custom split one member pays this share and the rest
            // divide the remainder.
            $table->decimal('custom_pct', 5, 2)->nullable();

            // The prototype inferred the custom payer from list position,
            // which is order-dependent and changes when someone cancels.
            // Naming them explicitly removes that fragility.
            $table->foreignUuid('custom_payer_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->unsignedSmallInteger('size');
            $table->unsignedBigInteger('total_cents');

            // none | multiplier | premium
            $table->string('price_mode')->default('none');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_groups');
    }
};

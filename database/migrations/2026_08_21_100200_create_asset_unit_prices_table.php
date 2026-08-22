<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Metered rates — per mile, per gallon, per session.
        //
        // The prototype defined these as `rate` and read them as
        // `pricePerUnit`, so any asset configured from its own type defaults
        // computed NaN for every metered charge. One name, and a column
        // rather than a blob key, makes that class of mistake impossible.
        Schema::create('asset_unit_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();

            // The unit as reported by the member: "Mile", "Gallon", "Session".
            $table->string('unit');
            $table->string('label')->nullable();

            // Cents per unit. Integer like all other money — a rate of
            // $0.35/mile is 35.
            $table->unsignedBigInteger('rate_cents');

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['asset_id', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_unit_prices');
    }
};

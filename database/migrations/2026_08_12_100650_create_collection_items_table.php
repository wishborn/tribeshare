<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named sub-items of a collection asset — a tool chest's tools, a
        // fleet's vehicles.
        Schema::create('collection_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();

            $table->string('name', 25);
            $table->string('icon')->nullable();
            $table->string('image')->nullable();

            // How many identical units this item represents.
            //
            // This is why booking conflict is NOT "one per slot": an item with
            // quantity 3 admits three concurrent bookings. The invariant is
            // that overlapping live bookings must not exceed it.
            $table->unsignedInteger('quantity')->default(1);

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_items');
    }
};

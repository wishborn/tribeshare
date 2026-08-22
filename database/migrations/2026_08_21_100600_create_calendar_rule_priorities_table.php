<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who gets this slice, and in what order.
        //
        // The prototype wrote this list under two different names with two
        // incompatible entry shapes, so one of the two code paths silently
        // did nothing. One name, one shape.
        //
        // Its "solo vs group" distinction collapses neatly here: members
        // sharing a `position` ARE a group. No separate entry type needed.
        //
        // Ordering only breaks ties between members of equal hat rank —
        // hats gate, lists order. The two exceptions below do override.
        Schema::create('calendar_rule_priorities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('calendar_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Equal positions share a rank.
            $table->unsignedInteger('position');

            // Bars this member from the slice whatever their hat says.
            $table->boolean('cannot_book')->default(false);

            // Protects their booking from being displaced by rank alone.
            $table->boolean('unbumpable')->default(false);

            $table->timestamps();

            $table->unique(['calendar_rule_id', 'user_id'], 'calendar_rule_priorities_unique');
            $table->index(['calendar_rule_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_rule_priorities');
    }
};

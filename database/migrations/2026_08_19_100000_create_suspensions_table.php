<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suspension cuts across hats entirely: a member may hold every power
        // and still be barred.
        //
        // Modelled ONCE, as entries. The prototype stored global suspension
        // twice — a boolean on the member AND an entry scoped "all" — and
        // carried a legacy fallback for when the two disagreed. One fact, two
        // representations, guaranteed to drift.
        //
        // A null scope IS the global suspension. Billing suspension is not
        // here at all: it is derived from the ledger.
        Schema::create('suspensions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Null scope means everywhere; otherwise the LLC it applies to.
            $table->nullableUuidMorphs('scopeable');

            $table->foreignUuid('suspended_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            $table->timestamp('suspended_at');

            // Set rather than deleted, so a member's history survives.
            $table->timestamp('lifted_at')->nullable();
            $table->foreignUuid('lifted_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'lifted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspensions');
    }
};

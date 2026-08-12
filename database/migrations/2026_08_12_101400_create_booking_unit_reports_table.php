<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assets may define unit prices (per mile, per engine-hour, ...). The
        // member reports usage after the booking completes; an owner reviews
        // and may adjust the quantities before accepting. So a metered
        // booking's final charge is not known at booking time.
        Schema::create('booking_unit_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();

            $table->foreignUuid('submitted_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();

            // unit => quantity
            $table->json('quantities')->nullable();
            $table->unsignedBigInteger('suggested_charge_cents')->default(0);

            $table->foreignUuid('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->json('final_quantities')->nullable();
            $table->unsignedBigInteger('final_charge_cents')->nullable();

            // awaiting_submission | pending_review | approved
            $table->string('status')->default('awaiting_submission');

            // Accepting a report MUST post the charge. The prototype added it
            // to the booking and told the member it was "added to your
            // balance", but wrote no ledger entry — so metered usage was
            // never actually billed. This records the entry that bills it.
            $table->foreignUuid('ledger_entry_id')->nullable()
                ->constrained('ledger_entries')->nullOnDelete();

            $table->timestamps();

            $table->unique('booking_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_unit_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // A member, an LLC or a region.
            $table->uuidMorphs('owner');

            // debit | credit
            $table->string('direction');

            // asset_charge | asset_income | llc_fee | regional_fee
            // | unit_charge | payout | reversal
            $table->string('label');

            // Always positive; `direction` carries the sign.
            $table->unsignedBigInteger('amount_cents');

            $table->string('description')->nullable();

            $table->foreignUuid('booking_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignUuid('asset_id')->nullable()
                ->constrained()->nullOnDelete();

            // YYYY-MM, derived from created_at on write. Stored rather than
            // derived because period reporting groups by it constantly and a
            // functional index is not portable to SQLite.
            $table->char('month_key', 7);

            // Charges only: creation + the configured due window.
            $table->dateTime('due_at')->nullable();

            // Set on balancing entries. Corrections are new rows pointing at
            // what they reverse — never edits.
            $table->foreignUuid('reverses_id')->nullable()
                ->constrained('ledger_entries')->nullOnDelete();
            $table->string('reason')->nullable();

            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // Deliberately created_at only. This table is append-only, and an
            // updated_at column would invite updates.
            $table->timestamp('created_at')->nullable();

            // Every balance query walks this.
            $table->index(['owner_type', 'owner_id', 'created_at'], 'ledger_owner_created_idx');
            $table->index(['month_key', 'owner_type', 'owner_id'], 'ledger_month_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};

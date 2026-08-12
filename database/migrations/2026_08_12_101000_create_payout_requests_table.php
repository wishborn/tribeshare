<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();

            $table->unsignedBigInteger('amount_cents');

            // pending | approved | denied
            $table->string('status')->default('pending');

            $table->dateTime('requested_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('denial_reason')->nullable();

            // Approving a payout MUST draw the credit down. The prototype
            // notified the member but posted nothing, so credit was
            // overstated indefinitely. This records the debit that settles it.
            $table->foreignUuid('ledger_entry_id')->nullable()
                ->constrained('ledger_entries')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // One open request per member. Partial indexes are supported by
        // SQLite and Postgres but not MySQL, so this is guarded rather than
        // assumed — the rule is also enforced in the application.
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX payout_requests_one_pending_per_user
                 ON payout_requests (user_id) WHERE status = 'pending'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The generic approval queue.
     *
     * One table, one resolution path. In the prototype, resolving requests in
     * a batch handled both outcomes for asset submissions while resolving one
     * singly handled only denial — so an asset approved one at a time was
     * never actually approved.
     */
    public function up(): void
    {
        Schema::create('member_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('type');
            $table->string('status');

            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();

            // What is being asked about: an LLC, an asset, a region.
            $table->nullableUuidMorphs('target');

            // The hat this request implies, created immediately but inactive
            // and pending. Approval activates it; denial deletes it. The
            // intended end state exists from the start, so approval is a
            // state change rather than a creation.
            $table->foreignUuid('pending_hat_id')->nullable()
                ->constrained('hats')->nullOnDelete();

            $table->text('message')->nullable();

            // The INTENT, never a fully-formed result. The prototype stored a
            // complete booking and its ledger entries on a cap-override
            // request and replayed them verbatim on approval, which froze the
            // price at request time and computed money on the client.
            $table->json('payload')->nullable();

            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['requested_by', 'status']);
            $table->index(['target_type', 'target_id', 'status'], 'member_requests_target_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_requests');
    }
};

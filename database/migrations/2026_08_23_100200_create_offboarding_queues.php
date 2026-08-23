<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two grace-period queues.
     *
     * The prototype held these as flags and an array on the member
     * (`pendingRecycle`, `quitQueue`), which lost who queued them, when, and
     * why the queue eventually fired. They are state machines with their own
     * actors and timestamps.
     */
    public function up(): void
    {
        Schema::create('member_removal_queues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->string('status');
            $table->text('reason')->nullable();

            $table->foreignUuid('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at');

            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('fired_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Member-initiated: they add an LLC to their own quit queue, and may
        // cancel while it is still queued. Leaving is self-service, but not
        // instant.
        Schema::create('llc_leave_queues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('llc_id')->constrained()->cascadeOnDelete();

            $table->string('status');

            $table->timestamp('queued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('fired_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['llc_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llc_leave_queues');
        Schema::dropIfExists('member_removal_queues');
    }
};

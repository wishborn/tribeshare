<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retirement provenance, recycling and deletion intent.
     *
     * The existing `queued_for_retirement_at` says an entity is queued but
     * not why. Restoring a region has to un-queue exactly the LLCs that
     * region queued and nothing else — otherwise a restore resurrects an LLC
     * that was retired on its own account, or one separately condemned.
     */
    public function up(): void
    {
        foreach (['regions', 'llcs', 'assets'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('queued_source')->nullable()->after('queued_for_retirement_at');
                $table->foreignUuid('queued_by')->nullable()->after('queued_source')
                    ->constrained('users')->nullOnDelete();

                // Recycling is the end of the cascade: the entity is retired
                // in fact, not merely queued. Kept apart from the soft delete
                // so a recycled entity is still readable.
                $table->timestamp('recycled_at')->nullable()->after('queued_by');
                $table->string('recycled_source')->nullable()->after('recycled_at');

                // Deletion is a separate, stricter path. Marking records the
                // intent and suspends the entity; deletion itself is gated.
                $table->timestamp('marked_for_deletion_at')->nullable()->after('recycled_source');
                $table->foreignUuid('marked_for_deletion_by')->nullable()->after('marked_for_deletion_at')
                    ->constrained('users')->nullOnDelete();

                $table->index('queued_for_retirement_at');
                $table->index('recycled_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['regions', 'llcs', 'assets'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('queued_by');
                $table->dropConstrainedForeignId('marked_for_deletion_by');
                $table->dropColumn([
                    'queued_source', 'recycled_at', 'recycled_source', 'marked_for_deletion_at',
                ]);
            });
        }
    }
};

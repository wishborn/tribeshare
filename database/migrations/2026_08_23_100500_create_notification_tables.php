<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tribeshare_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->string('kind');
            $table->string('title');
            $table->text('body')->nullable();

            // Replaces the prototype's loose kind-specific extras: an asset,
            // a booking, a conversation, hung off the record by name.
            $table->nullableUuidMorphs('subject');

            // Where tapping it should land.
            $table->string('link')->nullable();

            // Some notifications demand an acknowledgement rather than
            // merely being seen.
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamp('acknowledged_at')->nullable();

            // "Dismiss" means mark as read, not delete. Deletion is a
            // separate action, and only ever removes already-read rows.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'kind']);
        });

        // Honoured, not decorative. The prototype saved these and read them
        // back on the settings screen, but the routine that creates every
        // notification never consulted them.
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('preference');

            $table->boolean('in_app')->default(true);
            $table->boolean('push')->default(true);

            $table->timestamps();

            $table->primary(['user_id', 'preference'], 'notification_preferences_pk');
        });

        // The sidebar badge system: a per-member map of counts already seen.
        // A page turning "seen" records the count at that moment, and the
        // badge returns when the count exceeds it.
        //
        // Server-computed now — the client no longer receives the whole state
        // to derive counts from.
        Schema::create('page_seen_counts', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('page');
            $table->unsignedInteger('seen_count')->default(0);
            $table->timestamp('seen_at');

            $table->timestamps();

            $table->primary(['user_id', 'page'], 'page_seen_counts_pk');
        });

        // Web push. The prototype's push module was an explicitly-marked
        // placeholder documenting where to substitute a real call, and the
        // `pushDispatched` flag it set aside was never written — so push has
        // never worked. Built here rather than ported.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->text('endpoint');
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding')->default('aesgcm');

            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();

            // A subscription the browser has retired. Kept briefly with a
            // reason rather than vanishing, so a member asking "why did
            // notifications stop" has an answer.
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'public_key'], 'push_subscriptions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('page_seen_counts');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('tribeshare_notifications');
    }
};

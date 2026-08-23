<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name')->nullable();

            // The LLC or pool a conversation belongs to, when it has one.
            $table->nullableUuidMorphs('scopeable');

            // Denormalised for list rendering, which genuinely needs them.
            // Derived on write rather than trusted from the client.
            $table->timestamp('last_message_at')->nullable();
            $table->string('preview', 80)->nullable();

            // A direct conversation is deduplicated: creating one that
            // already exists between the same two members returns it.
            $table->boolean('is_direct')->default(false);
            $table->string('direct_key')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('direct_key');
            $table->index('last_message_at');
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Archiving is PER MEMBER. One member archiving a thread does not
            // hide it from anyone else.
            $table->timestamp('archived_at')->nullable();

            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'conversation_participants_unique');
            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sender_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body')->nullable();

            // A soft delete available only to the sender: the body is
            // cleared and this set, so the message keeps its place in the
            // thread rather than leaving a hole.
            $table->timestamp('body_deleted_at')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        // Rows, not an array on the message, so "unread for me" is a query
        // rather than a scan of every message in the thread.
        Schema::create('message_reads', function (Blueprint $table) {
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->primary(['message_id', 'user_id'], 'message_reads_pk');
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();

            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_reads');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};

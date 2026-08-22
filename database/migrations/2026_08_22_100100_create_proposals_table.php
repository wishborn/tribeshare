<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('governance_config_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('governable');

            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignUuid('proposed_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // draft -> petition -> voting -> passed -> executed
            // plus failed, withdrawn, repealed, blocked
            $table->string('status')->default('draft');

            $table->dateTime('voting_opens_at')->nullable();
            $table->dateTime('voting_closes_at')->nullable();

            // Stamped when the vote carries, honoured by the sweep.
            $table->unsignedSmallInteger('execution_delay_days')->default(2);
            $table->dateTime('executes_at')->nullable();
            $table->dateTime('executed_at')->nullable();

            // Why it could not be applied, when a guard refused it after it
            // had already passed.
            $table->text('failure_reason')->nullable();

            // What it does. Validated against the proposal type on write.
            $table->json('action_payload')->nullable();

            // A field this proposal locks on execution, so the decision
            // cannot be quietly reversed by an owner.
            $table->string('locks_field')->nullable();

            // Set on a repeal, naming what it undoes.
            $table->foreignUuid('repeal_of_id')->nullable()
                ->constrained('proposals')->nullOnDelete();

            $table->timestamps();

            $table->index(['governable_type', 'governable_id', 'status'], 'proposals_entity_status_idx');
            // The sweep scans these two.
            $table->index(['status', 'voting_closes_at']);
            $table->index(['status', 'executes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};

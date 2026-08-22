<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signatures that open a vote once the threshold is reached.
        Schema::create('proposal_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['proposal_id', 'user_id']);
        });

        Schema::create('proposal_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // yes | no | abstain | block
            $table->string('direction');

            // The member's own weight. Delegated weight is resolved at tally
            // time rather than stored, because the endpoint of a delegation
            // chain depends on who ends up voting.
            $table->decimal('weight', 8, 3)->default(1);

            // Consent model: a block should say why.
            $table->text('block_reason')->nullable();

            $table->dateTime('cast_at');
            $table->timestamps();

            $table->unique(['proposal_id', 'user_id']);
        });

        // Delegation is TRANSITIVE and EXCLUSIVE: weight follows the chain to
        // whoever actually votes, and delegating surrenders your own vote
        // until you revoke it. The prototype did neither, so a delegator
        // could vote as well as lend their weight — the same preference
        // counted twice, possibly in opposite directions.
        Schema::create('proposal_delegations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // One delegation out per member per proposal.
            $table->unique(['proposal_id', 'from_user_id']);
            $table->index(['proposal_id', 'to_user_id']);
        });

        Schema::create('proposal_credit_spends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('credits');

            // yes | no
            $table->string('direction');

            $table->timestamps();

            $table->unique(['proposal_id', 'user_id']);
        });

        // The budget credits are spent from. Allocated per member per period
        // and drawn down across every proposal, which is what makes spending
        // a real choice.
        Schema::create('governance_credit_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('governance_config_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('credits_remaining');
            $table->dateTime('allocated_at');

            $table->timestamps();

            $table->unique(['governance_config_id', 'user_id'], 'credit_balances_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_credit_balances');
        Schema::dropIfExists('proposal_credit_spends');
        Schema::dropIfExists('proposal_delegations');
        Schema::dropIfExists('proposal_votes');
        Schema::dropIfExists('proposal_signatures');
    }
};

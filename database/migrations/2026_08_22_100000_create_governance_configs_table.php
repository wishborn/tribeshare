<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How one entity decides things. An LLC, a region or a single asset
        // may each govern itself differently.
        Schema::create('governance_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('governable');

            $table->boolean('enabled')->default(false);
            $table->string('model')->default('one_member_one_vote');

            $table->decimal('quorum_pct', 5, 2)->default(50);
            $table->decimal('pass_pct', 5, 2)->default(60);

            $table->unsignedSmallInteger('voting_window_days')->default(7);

            // Cooling-off between passing and taking effect. Honoured on
            // every path — the prototype skipped it entirely when a proposal
            // was executed straight out of voting.
            $table->unsignedSmallInteger('execution_delay_days')->default(2);

            $table->boolean('petition_enabled')->default(true);
            $table->decimal('petition_threshold_pct', 5, 2)->default(20);

            // owners | members | granted
            $table->string('who_can_propose')->default('owners');

            // The quadratic allowance, allocated per member per period and
            // spent down ACROSS proposals — not reset for each one, which
            // would remove the scarcity the mechanism depends on.
            $table->unsignedInteger('voting_credits')->default(100);
            $table->unsignedSmallInteger('credit_period_days')->default(30);

            $table->timestamps();

            $table->unique(['governable_type', 'governable_id'], 'governance_configs_unique_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_configs');
    }
};

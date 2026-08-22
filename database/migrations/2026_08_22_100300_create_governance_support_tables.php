<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Multi-stakeholder: each class votes on its own thresholds, and
        // EVERY class must pass.
        Schema::create('stakeholder_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('governance_config_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->decimal('quorum_pct', 5, 2)->nullable();
            $table->decimal('pass_pct', 5, 2)->nullable();

            $table->timestamps();
        });

        Schema::create('stakeholder_class_members', function (Blueprint $table) {
            $table->foreignUuid('stakeholder_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['stakeholder_class_id', 'user_id'], 'stakeholder_class_members_pk');
        });

        // An explicit grant of the right to propose, separate from hats — a
        // deliberate escape hatch from whoever `who_can_propose` allows.
        Schema::create('proposal_rights', function (Blueprint $table) {
            $table->foreignUuid('governance_config_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['governance_config_id', 'user_id'], 'proposal_rights_pk');
        });

        // A field a decision froze. Lifted when a repeal executes.
        Schema::create('governance_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('lockable');

            $table->string('field');
            $table->foreignUuid('proposal_id')->constrained()->cascadeOnDelete();
            $table->dateTime('locked_at');

            $table->timestamps();

            $table->unique(['lockable_type', 'lockable_id', 'field'], 'governance_locks_unique_field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_locks');
        Schema::dropIfExists('proposal_rights');
        Schema::dropIfExists('stakeholder_class_members');
        Schema::dropIfExists('stakeholder_classes');
    }
};

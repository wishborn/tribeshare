<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Both role hats (RCM, LLCOwner, AssetManager, ...) and the three
            // membership hats (Regional Member, LLC Member, Asset Pool
            // Member). The prototype conflates them and the rank ordering
            // spans both, so they share a table.
            $table->string('type');

            // Null scope means the "all" scope — authority everywhere.
            //
            // The distinction matters: sensitive LLC powers require a hat
            // scoped EXACTLY to that LLC, so those checks compare
            // scopeable_id directly rather than also accepting null.
            $table->nullableUuidMorphs('scopeable');

            $table->boolean('active')->default(true);

            $table->timestamps();

            // NOTE: SQL treats NULLs as distinct, so this unique index does
            // NOT prevent duplicate "all"-scoped hats. That case has to be
            // guarded in the application until there is a sentinel scope.
            $table->unique(['user_id', 'type', 'scopeable_type', 'scopeable_id'], 'hats_unique_grant');

            $table->index(['type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hats');
    }
};

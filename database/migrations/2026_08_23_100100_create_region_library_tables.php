<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A region's document library. Neither this nor claims appeared in
        // the prototype's initial state, so both were invisible until the
        // action inventory surfaced them.
        Schema::create('region_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('region_id')->constrained()->cascadeOnDelete();

            $table->string('category');
            $table->string('title');
            $table->text('notes')->nullable();

            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['region_id', 'category']);
        });

        // Claims are their own concept, not a document with extra fields.
        // They have a lifecycle documents do not: filed, reviewed, settled.
        Schema::create('region_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('region_id')->constrained()->cascadeOnDelete();

            // What the claim is against, when it is against something we
            // know about. A claim may also be raised on the region at large.
            $table->nullableUuidMorphs('subject');

            $table->string('reference')->nullable();
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status');
            $table->date('incident_on');
            $table->date('filed_on');
            $table->date('settled_on')->nullable();

            // Money in integer cents, like everything else.
            $table->unsignedBigInteger('claimed_cents')->default(0);
            $table->unsignedBigInteger('settled_cents')->nullable();

            $table->foreignUuid('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['region_id', 'status']);
        });

        // Every status a claim has passed through, and who moved it. A claim
        // is worth an audit trail; the prototype overwrote the status string.
        Schema::create('region_claim_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('region_claim_id')->constrained()->cascadeOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();

            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Documents attached to a claim, over and above the region library.
        Schema::create('region_claim_documents', function (Blueprint $table) {
            $table->foreignUuid('region_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('region_document_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['region_claim_id', 'region_document_id'], 'region_claim_documents_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_claim_documents');
        Schema::dropIfExists('region_claim_events');
        Schema::dropIfExists('region_claims');
        Schema::dropIfExists('region_documents');
    }
};

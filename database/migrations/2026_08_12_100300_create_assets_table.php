<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('llc_id')->constrained()->restrictOnDelete();

            // Receives asset income on every booking, less any voluntary
            // contributions redirected to the LLC and region.
            $table->foreignUuid('main_owner_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('type');

            // Slot types and prices, unit prices, group pricing, voluntary
            // contribution percentages, offer-up splits, delegated powers,
            // quizzes. Deliberately JSON: this is unspecified, and guessing
            // its shape into columns would be worse than deferring. Expect
            // pricing in particular to become real tables once assets are
            // specified.
            $table->json('settings')->nullable();

            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('queued_for_retirement_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('llc_id');
            $table->index('main_owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

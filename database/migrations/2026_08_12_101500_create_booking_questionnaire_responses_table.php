<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assets may require a questionnaire at booking time. Responses are
        // surfaced to the asset's owners, who acknowledge them.
        Schema::create('booking_questionnaire_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();

            $table->string('question');
            $table->text('answer')->nullable();

            $table->foreignUuid('acknowledged_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();

            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_questionnaire_responses');
    }
};

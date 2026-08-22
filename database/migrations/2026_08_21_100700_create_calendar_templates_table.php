<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A saved month of rules, reusable across months.
        //
        // No silent cap: the prototype refused a fifth template by returning
        // unchanged state, so the save appeared to work and nothing
        // happened. If a limit is wanted it should refuse loudly.
        Schema::create('calendar_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // The rules themselves, in the same range shape as calendar_rules.
            $table->json('rules');

            // How long the month it came from was, so applying a 31-day
            // template to a shorter month can be reconciled rather than
            // silently truncated.
            $table->unsignedTinyInteger('source_days_in_month');

            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['asset_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();

            // The figure that counts once confirmed.
            $table->unsignedBigInteger('amount_cents');

            // What the member originally claimed, preserved when an admin
            // confirms a different amount to what actually arrived.
            $table->unsignedBigInteger('claimed_amount_cents')->nullable();

            $table->text('note')->nullable();

            // pending | confirmed
            //
            // A payment is a CLAIM, not a ledger entry. It becomes real on
            // confirmation, and only confirmed payments count toward
            // balances. Reversing one posts a balancing ledger entry; this
            // row is never rewritten.
            $table->string('status')->default('pending');

            $table->dateTime('submitted_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignUuid('confirmed_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

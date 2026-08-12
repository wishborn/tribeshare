<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Two distinct and easily-confused limits: a cap on outstanding
            // MONEY, and a cap on the NUMBER of bookings per month.
            $table->unsignedBigInteger('carried_balance_limit_cents')
                ->default(config('tribeshare.billing.default_carried_balance_limit_cents'));
            $table->unsignedInteger('monthly_booking_cap')->nullable();

            // Cache only. The ledger is the source of truth for whether a
            // member is billing-suspended; this is rebuilt by the sweep and
            // must never be treated as authoritative.
            $table->boolean('billing_suspended')->default(false);

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'carried_balance_limit_cents',
                'monthly_booking_cap',
                'billing_suspended',
                'deleted_at',
            ]);
        });
    }
};

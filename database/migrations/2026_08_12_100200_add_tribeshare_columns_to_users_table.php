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
            // $1,000.00. A literal, not a config lookup: a column default is
            // baked into the schema when the migration runs, so reading it
            // from config makes the schema depend on environment — and
            // yields NULL outright if the key is ever missing. Keep this in
            // step with tribeshare.billing.default_carried_balance_limit_cents,
            // which is what application code reads.
            $table->unsignedBigInteger('carried_balance_limit_cents')->default(1000_00);
            $table->unsignedInteger('monthly_booking_cap')->nullable();

            // Cache only. The ledger is the source of truth for whether a
            // member is billing-suspended; this is rebuilt by the sweep and
            // must never be treated as authoritative.
            $table->boolean('billing_suspended')->default(false);

            // NOTE: no soft deletes here. The prototype has two distinct
            // ideas — "recycled" and "permanently deleted" — and neither is
            // specified yet. Introducing SoftDeletes now would silently
            // change what account deletion means, so the member lifecycle is
            // modelled deliberately in the permissions pass instead.
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'carried_balance_limit_cents',
                'monthly_booking_cap',
                'billing_suspended',
            ]);
        });
    }
};

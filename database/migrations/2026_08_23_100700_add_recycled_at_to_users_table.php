<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member whose removal queue has fired.
     *
     * Kept apart from the soft delete so a recycled member is still readable:
     * their bookings, ledger entries and messages all reference them, and the
     * history has to keep making sense after they leave.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('recycled_at')->nullable()->after('billing_suspended');
            $table->index('recycled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['recycled_at']);
            $table->dropColumn('recycled_at');
        });
    }
};

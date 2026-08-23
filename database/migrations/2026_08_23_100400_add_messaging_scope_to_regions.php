<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who a member may start a conversation with.
     *
     * The prototype held this platform-wide and never consulted it — the only
     * enforcement was in the messages page, so a direct call reached anybody.
     * It is now per region, because every value it takes (`llc_only`,
     * `pool_only`, `regional`) is a scoped concept that never fitted in a
     * global field.
     *
     * Null means "use the platform default" rather than "no restriction", so
     * a region that has never chosen still gets the configured policy.
     */
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('messaging_scope')->nullable()->after('visible');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn('messaging_scope');
        });
    }
};

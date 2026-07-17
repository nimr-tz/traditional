<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which of the user's assigned roles they're currently viewing the
            // site as. Nullable — falls back to the primary `role` column when
            // unset or when it no longer names one of their assigned roles.
            $table->string('active_role')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_role');
        });
    }
};

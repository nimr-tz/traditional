<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dignitary's role — "Director General", "Permanent Secretary" — printed on
 * the badge next to their institution ("DIRECTOR GENERAL, MUHAS"). Set only for
 * the handful of leaders registered at the desk as walk-ins; null for everyone
 * who registers themselves online, where the field is not offered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('position_title')->nullable()->after('salutation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('position_title');
        });
    }
};

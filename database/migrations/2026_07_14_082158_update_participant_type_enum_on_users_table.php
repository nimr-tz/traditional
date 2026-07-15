<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop policy_maker/decision_maker/media, add a generic "participant"
     * option for attendees who don't fit researcher/practitioner/academic/
     * student. Switches participant_type from a rigid DB enum to a plain
     * string validated against config('tmsc.participant_types') — matches
     * how fee categories/subthemes/institutions are already handled, and
     * avoids enum-alteration syntax that differs between MySQL and SQLite.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('participant_type')->nullable()->change();
        });

        DB::table('users')
            ->whereIn('participant_type', ['policy_maker', 'decision_maker', 'media'])
            ->update(['participant_type' => 'participant']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('participant_type', [
                'researcher', 'practitioner', 'academic', 'policy_maker', 'decision_maker', 'student', 'media',
            ])->nullable()->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance was one record per person for the whole conference.
 *
 * Scanning someone at the door on the first morning meant they could never be
 * recorded again — the check-in endpoint answered "already checked in" to every
 * later scan, so a multi-day conference produced a single day's data and no way
 * to tell who came back.
 *
 * A person is now recorded once per day, with the time they were scanned. The
 * date is stored explicitly rather than derived from `checked_in_at`, because a
 * unique index over an expression is not portable across MySQL and the SQLite
 * used by the test suite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->date('attendance_date')->nullable()->after('user_id');
        });

        // Existing rows predate the column; their date is the day they were scanned.
        DB::table('attendances')->whereNull('attendance_date')->update([
            'attendance_date' => DB::raw('date(checked_in_at)'),
        ]);

        $this->removeDuplicateDays();

        Schema::table('attendances', function (Blueprint $table) {
            $table->date('attendance_date')->nullable(false)->change();
            $table->unique(['user_id', 'attendance_date']);
            $table->index('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'attendance_date']);
            $table->dropIndex(['attendance_date']);
            $table->dropColumn('attendance_date');
        });
    }

    /**
     * The old table had no constraint, so the same person could in principle
     * hold several rows for one day. Keep the earliest — the moment they first
     * came through is the one that matters — before the unique index goes on,
     * or adding it would fail outright.
     */
    private function removeDuplicateDays(): void
    {
        $duplicates = DB::table('attendances')
            ->select('user_id', 'attendance_date', DB::raw('min(id) as keep_id'))
            ->groupBy('user_id', 'attendance_date')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('attendances')
                ->where('user_id', $duplicate->user_id)
                ->where('attendance_date', $duplicate->attendance_date)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }
    }
};

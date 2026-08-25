<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The registration deadline is gone — sign-ups are accepted until an organizer
 * closes registration on purpose, and the admin settings form no longer offers
 * a date.
 *
 * Nothing reads `registration_deadline` any more, but conference settings reach
 * every Inertia page as one `allSettings()` bag, so a row left behind would
 * keep shipping a date to the browser that no longer means anything. There is
 * no `down()`: restoring the key without the code that read it would be
 * restoring a value nothing acts on.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('conference_settings')->where('key', 'registration_deadline')->delete();
    }
};

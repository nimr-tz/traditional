<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Walk-ins registered at the venue desk may have no email address at all.
 *
 * In the room, a phone number is the reliable way to reach someone — plenty of
 * attendees arrive without an address they can recall or spell, and forcing a
 * made-up one produces junk that bounces and pollutes every campaign audience.
 * Name, phone and institution carry the identity instead.
 *
 * The unique index survives: MySQL and SQLite both treat NULLs as distinct, so
 * any number of registrants may have no address while real ones stay unique.
 * Self-service registration still demands an email — people who sign up online
 * need somewhere to receive their control number and badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows without an address would violate the NOT NULL constraint, so give
        // them a placeholder rather than failing the rollback outright.
        DB::table('users')->whereNull('email')->update([
            'email' => DB::raw("concat('no-email-', id, '@tmsc.invalid')"),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};

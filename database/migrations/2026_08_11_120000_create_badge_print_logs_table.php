<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every badge that comes off the printer, recorded.
 *
 * A badge is entry to the conference, so an unlogged reprint is an untracked
 * second way in. Reprints are allowed — people genuinely lose badges — but the
 * desk is warned first and the count is visible afterwards, which is the
 * difference between a known reissue and a quiet duplicate.
 *
 * The printed name and institution are copied onto the row rather than read
 * back through the user. What was on the card at the time is the fact worth
 * keeping; the account can be edited afterwards and often is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();

            // 1 for the original, 2+ for reprints.
            $table->unsignedInteger('print_number');

            $table->string('printed_name');
            $table->string('printed_institution')->nullable();
            $table->string('printed_category')->nullable();
            $table->string('registration_code')->nullable();

            $table->timestamp('printed_at');
            $table->timestamps();

            $table->index(['user_id', 'printed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_print_logs');
    }
};

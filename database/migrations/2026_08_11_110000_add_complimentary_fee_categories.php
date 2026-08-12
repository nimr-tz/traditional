<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not everyone at the conference is paying for it.
 *
 * Media, the secretariat, invited guests and exhibition staff attend by role
 * rather than by fee. Until now the only route in for them was a finance waiver
 * — a fee recorded, then forgiven, which misrepresents both the revenue figures
 * and the reason they are there.
 *
 * A complimentary category owes nothing by definition. Marking it on the
 * category rather than the person keeps the list of who attends free small,
 * deliberate, and visible in Conference Settings, instead of a judgement made
 * one attendee at a time at the door.
 *
 * These categories are never offered on the public registration form — see
 * FeeCategory::selectableByPublic(). Free entry is granted at the desk, not
 * claimed by whoever is filling in the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_categories', function (Blueprint $table) {
            $table->boolean('is_complimentary')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('fee_categories', function (Blueprint $table) {
            $table->dropColumn('is_complimentary');
        });
    }
};

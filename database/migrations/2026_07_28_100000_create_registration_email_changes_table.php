<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for admin corrections to a registrant's email address.
     *
     * Email is the registrant's identity — it carries the verification link,
     * the control number, the badge, and the certificate — so the one place it
     * can be changed keeps a permanent before/after record of who changed it.
     */
    public function up(): void
    {
        Schema::create('registration_email_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_email');
            $table->string('new_email');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name');
            $table->string('changed_by_email');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_email_changes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Was an enum('pending','submitted','verified','rejected'); switched to a plain
            // string (matching role/student_verification_status elsewhere in this table) so
            // the finance role can grant a 'waived' status without a schema-level enum change.
            $table->string('payment_status')->default('pending')->change();

            $table->foreignId('payment_verified_by')->nullable()->after('payment_notes')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_verified_by');
            $table->enum('payment_status', ['pending', 'submitted', 'verified', 'rejected'])
                ->default('pending')->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE abstract_submissions MODIFY status ENUM('submitted', 'revision_requested', 'accepted', 'rejected') NOT NULL DEFAULT 'submitted'");
        }

        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('revision_requested_at')->nullable()->after('decision_notes');
            $table->timestamp('resubmitted_at')->nullable()->after('revision_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropColumn(['revision_requested_at', 'resubmitted_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE abstract_submissions MODIFY status ENUM('submitted', 'accepted', 'rejected') NOT NULL DEFAULT 'submitted'");
        }
    }
};

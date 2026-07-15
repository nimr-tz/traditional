<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_document_path')->nullable()->after('fee_category');
            $table->string('student_verification_status')->nullable()->after('student_document_path');
            $table->timestamp('student_verified_at')->nullable()->after('student_verification_status');
            $table->foreignId('student_verified_by')->nullable()->after('student_verified_at')
                ->constrained('users')->nullOnDelete();
            $table->text('student_verification_notes')->nullable()->after('student_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_verified_by');
            $table->dropColumn([
                'student_document_path',
                'student_verification_status',
                'student_verified_at',
                'student_verification_notes',
            ]);
        });
    }
};

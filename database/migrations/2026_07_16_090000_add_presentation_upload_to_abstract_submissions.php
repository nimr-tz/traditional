<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->string('presentation_file')->nullable()->after('decided_at');
            $table->string('presentation_original_name')->nullable()->after('presentation_file');
            $table->timestamp('presentation_uploaded_at')->nullable()->after('presentation_original_name');
            $table->text('presentation_notes')->nullable()->after('presentation_uploaded_at');
            $table->string('presentation_status')->default('pending')->after('presentation_notes');
            $table->text('presentation_review_notes')->nullable()->after('presentation_status');
        });
    }

    public function down(): void
    {
        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'presentation_file',
                'presentation_original_name',
                'presentation_uploaded_at',
                'presentation_notes',
                'presentation_status',
                'presentation_review_notes',
            ]);
        });
    }
};

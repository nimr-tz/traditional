<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->foreignId('reviewer_one_id')->nullable()->after('reviewer_id')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_two_id')->nullable()->after('reviewer_one_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_one_id');
            $table->dropConstrainedForeignId('reviewer_two_id');
        });
    }
};

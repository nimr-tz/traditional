<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abstract_reviewer_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abstract_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('recommendation', 20);
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(
                ['abstract_submission_id', 'reviewer_id'],
                'abstract_review_reviewer_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abstract_reviewer_decisions');
    }
};

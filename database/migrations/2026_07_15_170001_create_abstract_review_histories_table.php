<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abstract_review_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abstract_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['abstract_submission_id', 'created_at'], 'abstract_history_submission_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abstract_review_histories');
    }
};

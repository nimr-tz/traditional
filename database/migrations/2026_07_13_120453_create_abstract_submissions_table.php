<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abstract_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subtheme_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->json('authors');
            $table->text('abstract_text');
            $table->enum('presentation_type', ['oral', 'poster']);
            $table->enum('status', ['submitted', 'revision_requested', 'accepted', 'rejected'])->default('submitted');
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abstract_submissions');
    }
};

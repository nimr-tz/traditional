<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abstract_reviewer_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abstract_reviewer_decision_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20)->nullable();
            $table->text('body');
            $table->timestamp('addressed_at')->nullable();
            $table->timestamps();
        });

        // Carry forward any existing free-text notes as a single "Overall" comment.
        DB::table('abstract_reviewer_decisions')->whereNotNull('notes')->where('notes', '!=', '')->get()
            ->each(function ($decision) {
                DB::table('abstract_reviewer_comments')->insert([
                    'abstract_reviewer_decision_id' => $decision->id,
                    'section' => null,
                    'body' => $decision->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('abstract_reviewer_decisions', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        Schema::table('abstract_reviewer_decisions', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });

        Schema::dropIfExists('abstract_reviewer_comments');
    }
};

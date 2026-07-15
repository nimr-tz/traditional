<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('abstract_submissions')
            ->orderBy('id')
            ->each(function (object $submission) {
                $hasHistory = DB::table('abstract_review_histories')
                    ->where('abstract_submission_id', $submission->id)
                    ->exists();

                if ($hasHistory) {
                    return;
                }

                DB::table('abstract_review_histories')->insert([
                    'abstract_submission_id' => $submission->id,
                    'acted_by' => in_array($submission->status, ['accepted', 'rejected', 'revision_requested'], true)
                        ? $submission->reviewer_id
                        : $submission->user_id,
                    'action' => $submission->status,
                    'from_status' => null,
                    'to_status' => $submission->status,
                    'notes' => $submission->decision_notes,
                    'created_at' => $submission->decided_at ?? $submission->created_at,
                    'updated_at' => $submission->decided_at ?? $submission->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        // Historical records may have grown after this migration; never delete them on rollback.
    }
};

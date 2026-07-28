<?php

namespace Tests\Feature;

use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AbstractReviewerCommentTest extends TestCase
{
    use RefreshDatabase;

    private function reviewedSubmission(): array
    {
        $subtheme = Subtheme::create(['title' => 'Innovations', 'active' => true, 'sort_order' => 1]);
        $author = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $submission = AbstractSubmission::create([
            'user_id' => $author->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Herbal Innovations',
            'authors' => [['name' => 'A. Presenter', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background.',
            'objective' => 'Objective.',
            'methods' => 'Methods.',
            'results' => 'Results.',
            'conclusion' => 'Conclusion.',
            'presentation_type' => 'poster',
            'status' => 'submitted',
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ]);

        $this->actingAs($reviewerA)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'revision_requested',
            'comments' => [['section' => 'methods', 'body' => 'Describe the sampling strategy.']],
        ])->assertRedirect();

        return [$submission, $author, $admin, $reviewerA, $reviewerB];
    }

    public function test_the_author_sees_reviewer_comments_with_anonymised_labels(): void
    {
        [$submission, $author, , $reviewerA] = $this->reviewedSubmission();

        $response = $this->actingAs($author)->get(route('abstracts.edit', $submission));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('abstracts/edit')
            ->has('reviewerComments', 1)
            ->where('reviewerComments.0.section', 'methods')
            ->where('reviewerComments.0.reviewer_label', 'Reviewer A')
            ->where('reviewerComments.0.addressed', false));

        $this->assertSame($reviewerA->id, $submission->reviewer_one_id);
    }

    public function test_the_author_can_mark_a_comment_as_addressed_and_toggle_it_back(): void
    {
        [$submission, $author] = $this->reviewedSubmission();
        $comment = $submission->reviewerDecisions()->first()->comments()->first();

        $this->actingAs($author)
            ->post(route('abstracts.comments.toggle-addressed', [$submission, $comment]))
            ->assertRedirect();

        $this->assertNotNull($comment->fresh()->addressed_at);

        $this->actingAs($author)
            ->post(route('abstracts.comments.toggle-addressed', [$submission, $comment]))
            ->assertRedirect();

        $this->assertNull($comment->fresh()->addressed_at);
    }

    public function test_a_stranger_cannot_toggle_someone_elses_comment(): void
    {
        [$submission] = $this->reviewedSubmission();
        $comment = $submission->reviewerDecisions()->first()->comments()->first();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('abstracts.comments.toggle-addressed', [$submission, $comment]))
            ->assertForbidden();

        $this->assertNull($comment->fresh()->addressed_at);
    }

    /**
     * Superseded recommendations and their comments are archived onto the old
     * round rather than deleted — the reviewer needs them to judge whether the
     * revision answered what they asked for, and they are the record of why a
     * revision was requested at all.
     */
    public function test_resubmission_opens_a_new_round_and_archives_the_previous_one(): void
    {
        Mail::fake();

        [$submission, $author] = $this->reviewedSubmission();
        $submission->update(['status' => 'revision_requested']);

        $this->assertSame(1, $submission->reviewerDecisions()->count());

        $this->actingAs($author)->put(route('abstracts.update', $submission), [
            'title' => $submission->title,
            'subtheme_id' => $submission->subtheme_id,
            'presentation_type' => $submission->presentation_type,
            'authors' => $submission->authors,
            'background' => 'Revised background addressing the sampling strategy.',
            'objective' => $submission->objective,
            'methods' => 'Revised methods with sampling details.',
            'results' => $submission->results,
            'conclusion' => $submission->conclusion,
        ])->assertRedirect(route('abstracts.index'));

        $submission->refresh();
        $this->assertSame('submitted', $submission->status);
        $this->assertSame(2, $submission->review_round);

        // Nothing counts toward the new round yet...
        $this->assertSame(0, $submission->currentReviewerDecisions()->count());

        // ...but the previous round and its comments are still on file.
        $this->assertSame(1, $submission->reviewerDecisions()->where('round', 1)->count());
        $this->assertDatabaseCount('abstract_reviewer_comments', 1);

        // The same two reviewers stay assigned for the next round.
        $this->assertNotNull($submission->reviewer_one_id);
        $this->assertNotNull($submission->reviewer_two_id);
    }
}

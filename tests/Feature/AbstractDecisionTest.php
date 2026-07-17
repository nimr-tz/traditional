<?php

namespace Tests\Feature;

use App\Mail\AbstractDecision;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AbstractDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function submission(array $overrides = []): AbstractSubmission
    {
        $subtheme = Subtheme::create(['title' => 'Innovations', 'active' => true, 'sort_order' => 1]);

        return AbstractSubmission::create(array_merge([
            'user_id' => User::factory()->create()->id,
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
        ], $overrides));
    }

    /** Assign two reviewers and have both recommend the same action, leaving the abstract ready for a final decision. */
    private function readyForDecision(AbstractSubmission $submission, User $admin, string $recommendation = 'accepted'): array
    {
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ])->assertRedirect();

        $comments = $recommendation === 'accepted' ? [] : [['section' => null, 'body' => 'Needs work.']];

        $this->actingAs($reviewerA)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => $recommendation,
            'comments' => $comments,
        ])->assertRedirect();

        $this->actingAs($reviewerB)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => $recommendation,
            'comments' => $comments,
        ])->assertRedirect();

        return [$reviewerA, $reviewerB];
    }

    public function test_admin_can_assign_two_reviewers(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ])->assertRedirect()->assertSessionHas('success', 'Reviewers assigned.');

        $submission->refresh();
        $this->assertSame($reviewerA->id, $submission->reviewer_one_id);
        $this->assertSame($reviewerB->id, $submission->reviewer_two_id);
    }

    public function test_the_authors_own_account_cannot_be_assigned_as_a_reviewer(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $submission->user_id,
            'reviewer_two_id' => $reviewer->id,
        ])->assertSessionHasErrors('reviewer_one_id');
    }

    public function test_a_reviewer_cannot_assign_reviewers(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->submission();
        $otherReviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewer->id,
            'reviewer_two_id' => $otherReviewer->id,
        ])->assertForbidden();
    }

    public function test_an_assigned_reviewer_can_submit_a_recommendation(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ]);

        $this->actingAs($reviewerA)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'accepted',
        ])->assertRedirect()->assertSessionHas('success', 'Your recommendation has been recorded.');

        $this->assertDatabaseHas('abstract_reviewer_decisions', [
            'abstract_submission_id' => $submission->id,
            'reviewer_id' => $reviewerA->id,
            'recommendation' => 'accepted',
        ]);
    }

    public function test_a_comment_is_required_unless_the_recommendation_is_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ]);

        $this->actingAs($reviewerA)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'revision_requested',
            'comments' => [],
        ])->assertSessionHasErrors('comments');
    }

    public function test_reviewer_comments_are_saved_with_their_section(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ]);

        $this->actingAs($reviewerA)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'revision_requested',
            'comments' => [
                ['section' => null, 'body' => 'Please clarify the overall contribution.'],
                ['section' => 'methods', 'body' => 'Describe the sampling strategy.'],
            ],
        ])->assertRedirect();

        $decision = $submission->reviewerDecisions()->where('reviewer_id', $reviewerA->id)->firstOrFail();
        $this->assertCount(2, $decision->comments);
        $this->assertSame('methods', $decision->comments->last()->section);

        // Resubmitting a recommendation replaces the previous comment set rather than appending to it.
        $this->actingAs($reviewerA)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'revision_requested',
            'comments' => [
                ['section' => 'results', 'body' => 'Quantify the finding.'],
            ],
        ])->assertRedirect();

        $decision->refresh();
        $this->assertCount(1, $decision->comments);
        $this->assertSame('results', $decision->comments->first()->section);
    }

    public function test_an_unassigned_reviewer_cannot_submit_a_recommendation(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();
        $outsider = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ]);

        $this->actingAs($outsider)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'accepted',
        ])->assertForbidden();
    }

    public function test_an_unassigned_reviewer_cannot_view_the_abstract(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();
        $outsider = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ]);

        $this->actingAs($outsider)->get(route('admin.abstracts.show', $submission))->assertForbidden();
        $this->actingAs($reviewerA)->get(route('admin.abstracts.show', $submission))->assertOk();
    }

    public function test_decide_is_blocked_until_reviewers_are_assigned(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'accepted',
        ])->assertStatus(422);
    }

    public function test_decide_is_blocked_until_both_reviewers_submit_a_recommendation(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ]);

        $this->actingAs($reviewerA)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'accepted',
        ]);

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'accepted',
        ])->assertStatus(422);
    }

    public function test_a_reviewer_cannot_make_the_final_decision(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        [$reviewerA] = $this->readyForDecision($submission, $admin);

        $this->actingAs($reviewerA)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'accepted',
        ])->assertForbidden();
    }

    public function test_abstract_is_automatically_accepted_once_both_reviewers_recommend_acceptance(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $submission = $this->submission();

        // Both reviewers recommending acceptance should finalize the abstract
        // by itself — no separate admin decision is needed or possible.
        $this->readyForDecision($submission, $admin, 'accepted');

        $submission->refresh();

        $this->assertSame('accepted', $submission->status);
        $this->assertNull($submission->reviewer_id);
        $this->assertNotNull($submission->decided_at);
        $this->assertDatabaseHas('abstract_review_histories', [
            'abstract_submission_id' => $submission->id,
            'acted_by' => null,
            'action' => 'accepted',
        ]);
        Mail::assertQueued(AbstractDecision::class, fn ($mail) => $mail->hasTo($submission->user->email));

        // The abstract already left the 'submitted' state automatically, so
        // the manual decision endpoint is no longer reachable.
        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'accepted',
        ])->assertStatus(422);
    }

    public function test_admin_still_decides_when_reviewers_disagree(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ])->assertRedirect();

        $this->actingAs($reviewerA)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'accepted',
        ])->assertRedirect();

        $this->actingAs($reviewerB)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'rejected',
            'comments' => [['section' => null, 'body' => 'Not novel enough.']],
        ])->assertRedirect();

        // Disagreement doesn't auto-resolve — the abstract stays 'submitted'
        // and waits for the admin's manual call.
        $this->assertSame('submitted', $submission->fresh()->status);

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'accepted',
            'decision_notes' => 'Great work.',
        ])->assertRedirect();

        $submission->refresh();
        $this->assertSame('accepted', $submission->status);
        $this->assertSame($admin->id, $submission->reviewer_id);
        Mail::assertQueued(AbstractDecision::class, fn ($mail) => $mail->hasTo($submission->user->email));
    }

    public function test_revision_requires_a_comment_and_returns_the_abstract_to_the_author(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $this->readyForDecision($submission, $admin, 'revision_requested');

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'revision_requested',
            'decision_notes' => '',
        ])->assertSessionHasErrors('decision_notes');

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'revision_requested',
            'decision_notes' => 'Clarify the study methods.',
        ])->assertRedirect(route('admin.abstracts.show', $submission));

        $this->assertSame('revision_requested', $submission->fresh()->status);
        $this->assertNull($submission->fresh()->decided_at);
        Mail::assertQueued(AbstractDecision::class, fn ($mail) => $mail->hasTo($submission->user->email));
    }

    public function test_accepted_and_rejected_decisions_are_final(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission([
            'status' => 'accepted',
            'decided_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.abstracts.decide', $submission), [
            'action' => 'rejected',
            'decision_notes' => 'Changed mind.',
        ])->assertUnprocessable();

        $this->assertSame('accepted', $submission->fresh()->status);
    }

    public function test_non_admin_cannot_access_the_admin_abstracts_area(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/abstracts')->assertForbidden();
    }

    public function test_a_reviewer_cannot_see_the_other_reviewers_recommendation_or_comments(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $submission), [
            'reviewer_one_id' => $reviewerA->id,
            'reviewer_two_id' => $reviewerB->id,
        ])->assertRedirect();

        $this->actingAs($reviewerB)->post(route('admin.abstracts.reviewer-decision', $submission), [
            'recommendation' => 'revision_requested',
            'comments' => [['section' => null, 'body' => 'Reviewer B is not happy about the methods section.']],
        ])->assertRedirect();

        // Reviewer A: their own (still-pending) slot is visible, but Reviewer B's
        // recommendation and comment text must not leak into the page props.
        $this->actingAs($reviewerA)
            ->get(route('admin.abstracts.show', $submission))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('submission.reviewer_decisions.0.reviewer_id', $reviewerB->id)
                ->where('submission.reviewer_decisions.0.recommendation', null)
                ->where('submission.reviewer_decisions.0.comments', []));

        // Admin sees everything, needed to weigh both recommendations.
        $this->actingAs($admin)
            ->get(route('admin.abstracts.show', $submission))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('submission.reviewer_decisions.0.reviewer_id', $reviewerB->id)
                ->where('submission.reviewer_decisions.0.recommendation', 'revision_requested')
                ->where('submission.reviewer_decisions.0.comments.0.body', 'Reviewer B is not happy about the methods section.'));
    }
}

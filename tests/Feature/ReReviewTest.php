<?php

namespace Tests\Feature;

use App\Mail\AbstractReviewRequested;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A revision is a *re-review*: the reviewer who asked for changes gets the
 * abstract back with their own earlier comments attached, so they can judge
 * whether the author answered them.
 *
 * Superseded rounds are kept rather than deleted — previously a resubmission
 * hard-deleted the recommendation and cascaded away its comments, destroying
 * both the reviewer's context and the record of why a revision was requested.
 */
class ReReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reviewerA;

    private User $reviewerB;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->admin()->create();
        $this->reviewerA = User::factory()->reviewer()->create(['name' => 'Aaliyah Reviewer']);
        $this->reviewerB = User::factory()->reviewer()->create(['name' => 'Baraka Reviewer']);
    }

    private function submission(): AbstractSubmission
    {
        $subtheme = Subtheme::firstOrCreate(['title' => 'Innovations'], ['active' => true, 'sort_order' => 1]);

        $abstract = AbstractSubmission::create([
            'user_id' => User::factory()->create()->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Herbal Innovations',
            'authors' => [['name' => 'A', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'B.', 'objective' => 'O.', 'methods' => 'M.', 'results' => 'R.', 'conclusion' => 'C.',
            'presentation_type' => 'oral',
            'status' => 'submitted',
        ]);

        $abstract->forceFill([
            'reviewer_one_id' => $this->reviewerA->id,
            'reviewer_two_id' => $this->reviewerB->id,
        ])->save();

        return $abstract;
    }

    private function recommend(User $reviewer, AbstractSubmission $abstract, string $recommendation, string $comment = 'Please expand the methods.'): void
    {
        $this->actingAs($reviewer)->post(route('admin.abstracts.reviewer-decision', $abstract), [
            'recommendation' => $recommendation,
            'comments' => $recommendation === 'accepted' ? [] : [['section' => 'methods', 'body' => $comment]],
        ])->assertRedirect();
    }

    private function requestRevision(AbstractSubmission $abstract): void
    {
        $this->actingAs($this->admin)->post(route('admin.abstracts.decide', $abstract), [
            'action' => 'revision_requested',
            'decision_notes' => 'Please address the reviewer comments.',
        ])->assertRedirect();
    }

    private function resubmit(AbstractSubmission $abstract): void
    {
        $this->actingAs($abstract->user)->put(route('abstracts.update', $abstract), [
            'title' => $abstract->title,
            'subtheme_id' => $abstract->subtheme_id,
            'presentation_type' => $abstract->presentation_type,
            'authors' => $abstract->authors,
            'background' => 'Revised background.',
            'objective' => 'O.', 'methods' => 'Expanded methods.', 'results' => 'R.', 'conclusion' => 'C.',
        ])->assertRedirect();
    }

    public function test_a_resubmission_advances_the_round_and_keeps_the_earlier_recommendation(): void
    {
        $abstract = $this->submission();

        $this->recommend($this->reviewerA, $abstract, 'revision_requested', 'Methods are too thin.');
        $this->recommend($this->reviewerB, $abstract, 'revision_requested', 'Results need numbers.');
        $this->requestRevision($abstract);
        $this->resubmit($abstract);

        $abstract->refresh();

        $this->assertSame(2, $abstract->review_round);
        $this->assertSame('submitted', $abstract->status);
        $this->assertTrue($abstract->isReReview());
        $this->assertSame(1, $abstract->completedReviewRounds());

        // Nothing was destroyed: both round-1 recommendations survive, with comments.
        $this->assertSame(2, $abstract->reviewerDecisions()->where('round', 1)->count());
        $this->assertSame(0, $abstract->currentReviewerDecisions()->count());
        $this->assertDatabaseHas('abstract_reviewer_comments', ['body' => 'Methods are too thin.']);
    }

    public function test_an_acceptance_carries_forward_and_that_reviewer_is_not_asked_again(): void
    {
        $abstract = $this->submission();

        $this->recommend($this->reviewerA, $abstract, 'accepted');
        $this->recommend($this->reviewerB, $abstract, 'rejected', 'Not novel enough.');
        $this->requestRevision($abstract);

        Mail::fake();
        $this->resubmit($abstract);

        $abstract->refresh();

        // A's acceptance moved into round 2; B's rejection stayed behind.
        $this->assertSame(1, $abstract->currentReviewerDecisions()->count());
        $this->assertSame(
            'accepted',
            $abstract->currentReviewerDecisions()->where('reviewer_id', $this->reviewerA->id)->value('recommendation')
        );
        $this->assertSame(1, $abstract->reviewerDecisions()->where('round', 1)->where('reviewer_id', $this->reviewerB->id)->count());

        // Only B is asked to look again.
        // The mailable is ShouldQueue, so it lands in the queued bag, not sent.
        Mail::assertQueued(AbstractReviewRequested::class, 1);
        Mail::assertQueued(AbstractReviewRequested::class, fn ($mail) => $mail->hasTo($this->reviewerB->email));
    }

    /** With the carried-forward acceptance, one more accept finishes it automatically. */
    public function test_the_carried_acceptance_can_complete_the_abstract_on_the_next_round(): void
    {
        $abstract = $this->submission();

        $this->recommend($this->reviewerA, $abstract, 'accepted');
        $this->recommend($this->reviewerB, $abstract, 'revision_requested', 'Needs work.');
        $this->requestRevision($abstract);
        $this->resubmit($abstract);

        $this->recommend($this->reviewerB, $abstract->refresh(), 'accepted');

        $abstract->refresh();
        $this->assertSame('accepted', $abstract->status);
        // Auto-accepted, so no human is recorded as the decider.
        $this->assertNull($abstract->reviewer_id);
    }

    /** A stale acceptance must not combine with a fresh one across rounds. */
    public function test_a_superseded_recommendation_never_counts_toward_completion(): void
    {
        $abstract = $this->submission();

        $this->recommend($this->reviewerA, $abstract, 'revision_requested', 'Fix this.');
        $this->recommend($this->reviewerB, $abstract, 'revision_requested', 'And this.');
        $this->requestRevision($abstract);
        $this->resubmit($abstract);

        // Round 2: only A has answered so far.
        $this->recommend($this->reviewerA, $abstract->refresh(), 'accepted');

        $abstract->refresh();
        $this->assertFalse($abstract->bothReviewersDecided());
        $this->assertSame('submitted', $abstract->status);

        // The admin still cannot decide on one current recommendation.
        $this->actingAs($this->admin)
            ->post(route('admin.abstracts.decide', $abstract), ['action' => 'accepted'])
            ->assertStatus(422);
    }

    public function test_a_reviewer_sees_their_own_previous_round_when_re_reviewing(): void
    {
        $abstract = $this->submission();

        $this->recommend($this->reviewerA, $abstract, 'revision_requested', 'Methods are too thin.');
        $this->recommend($this->reviewerB, $abstract, 'revision_requested', 'Results need numbers.');
        $this->requestRevision($abstract);
        $this->resubmit($abstract);

        $this->actingAs($this->reviewerA)
            ->get(route('admin.abstracts.show', $abstract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('submission.is_re_review', true)
                ->where('submission.review_round', 2)
                ->where('submission.completed_rounds', 1)
                ->has('submission.prior_rounds', 1)
                ->where('submission.prior_rounds.0.round', 1)
                ->where('submission.prior_rounds.0.recommendation', 'revision_requested')
                ->where('submission.prior_rounds.0.reviewer_name', 'You')
                ->where('submission.prior_rounds.0.comments.0.body', 'Methods are too thin.')
            );
    }

    /** Blind review still applies to history: A must not read B's past comments. */
    public function test_a_reviewer_never_sees_the_other_reviewers_previous_round(): void
    {
        $abstract = $this->submission();

        $this->recommend($this->reviewerA, $abstract, 'revision_requested', 'Methods are too thin.');
        $this->recommend($this->reviewerB, $abstract, 'revision_requested', 'SECRET-B-COMMENT');
        $this->requestRevision($abstract);
        $this->resubmit($abstract);

        $response = $this->actingAs($this->reviewerA)->get(route('admin.abstracts.show', $abstract));
        $payload = json_encode($response->viewData('page')['props']);

        $this->assertStringNotContainsString('SECRET-B-COMMENT', $payload);
        $this->assertStringContainsString('Methods are too thin.', $payload);
    }

    public function test_an_admin_sees_every_reviewers_previous_rounds(): void
    {
        $abstract = $this->submission();

        $this->recommend($this->reviewerA, $abstract, 'revision_requested', 'Comment from A.');
        $this->recommend($this->reviewerB, $abstract, 'revision_requested', 'Comment from B.');
        $this->requestRevision($abstract);
        $this->resubmit($abstract);

        $this->actingAs($this->admin)
            ->get(route('admin.abstracts.show', $abstract))
            ->assertInertia(fn (Assert $page) => $page->has('submission.prior_rounds', 2));

        $payload = json_encode(
            $this->actingAs($this->admin)->get(route('admin.abstracts.show', $abstract))->viewData('page')['props']
        );
        $this->assertStringContainsString('Comment from A.', $payload);
        $this->assertStringContainsString('Comment from B.', $payload);
    }

    /** The author keeps earlier feedback, flagged so it isn't mistaken for outstanding work. */
    public function test_the_author_still_sees_earlier_round_comments_marked_as_earlier(): void
    {
        $abstract = $this->submission();

        $this->recommend($this->reviewerA, $abstract, 'revision_requested', 'Round one comment.');
        $this->recommend($this->reviewerB, $abstract, 'revision_requested', 'Another round one comment.');
        $this->requestRevision($abstract);
        $this->resubmit($abstract);

        $this->actingAs($abstract->user)
            ->get(route('abstracts.edit', $abstract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reviewerComments', 2)
                ->where('reviewerComments.0.round', 1)
                ->where('reviewerComments.0.is_current', false)
            );
    }

    public function test_the_re_review_email_is_framed_as_a_re_review(): void
    {
        $abstract = $this->submission();
        $rendered = (new AbstractReviewRequested($abstract, true))->render();

        $this->assertStringContainsString('re-review', strtolower($rendered));
        $this->assertStringNotContainsString('New abstract ready for review', $rendered);
    }

    /** There is no cap: the loop can run as many times as the committee needs. */
    public function test_revision_rounds_are_unlimited(): void
    {
        $abstract = $this->submission();

        foreach (range(1, 4) as $round) {
            $this->recommend($this->reviewerA, $abstract->refresh(), 'revision_requested', "Round {$round} from A.");
            $this->recommend($this->reviewerB, $abstract->refresh(), 'revision_requested', "Round {$round} from B.");
            $this->requestRevision($abstract->refresh());
            $this->resubmit($abstract->refresh());
        }

        $abstract->refresh();
        $this->assertSame(5, $abstract->review_round);
        $this->assertSame(4, $abstract->completedReviewRounds());
        // Every round's feedback is still on file.
        $this->assertSame(8, $abstract->reviewerDecisions()->count());
    }
}

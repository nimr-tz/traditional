<?php

namespace Tests\Feature;

use App\Mail\AbstractReviewRequested;
use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Review is single-blind: an assigned reviewer must not be able to work out who
 * wrote the abstract they are judging. Author identity used to reach them
 * through five separate channels, so each one gets its own test.
 */
class BlindReviewTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHOR_NAME = 'Fatuma Distinctive Mwakalinga';

    private const AUTHOR_EMAIL = 'fatuma-distinctive@example.com';

    private const AUTHOR_INSTITUTION = 'Distinctive Institute of Herbal Science';

    private function submission(): AbstractSubmission
    {
        $subtheme = Subtheme::firstOrCreate(['title' => 'Innovations'], ['active' => true, 'sort_order' => 1]);

        $author = User::factory()->create([
            'name' => self::AUTHOR_NAME,
            'email' => self::AUTHOR_EMAIL,
            'institution' => self::AUTHOR_INSTITUTION,
            'phone' => '+255700111222',
        ]);

        $submission = AbstractSubmission::create([
            'user_id' => $author->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Herbal Innovations',
            'authors' => [
                ['name' => self::AUTHOR_NAME, 'institution' => self::AUTHOR_INSTITUTION, 'is_presenter' => true],
                ['name' => 'Co Author', 'institution' => 'Another Institute', 'is_presenter' => false],
            ],
            'background' => 'Background.', 'objective' => 'Objective.', 'methods' => 'Methods.',
            'results' => 'Results.', 'conclusion' => 'Conclusion.',
            'presentation_type' => 'oral',
            'status' => 'submitted',
        ]);

        // The author is the actor on their own submission, which used to surface
        // their name in the review-history timeline.
        $submission->reviewHistory()->create([
            'acted_by' => $author->id,
            'action' => 'submitted',
            'from_status' => null,
            'to_status' => 'submitted',
        ]);

        return $submission;
    }

    private function assign(AbstractSubmission $abstract, User $reviewer): void
    {
        $abstract->forceFill([
            'reviewer_one_id' => $reviewer->id,
            'reviewer_two_id' => User::factory()->reviewer()->create()->id,
        ])->save();
    }

    /** The whole payload, serialised — the author must not appear anywhere in it. */
    public function test_no_author_identity_reaches_a_reviewer_anywhere_in_the_payload(): void
    {
        $abstract = $this->submission();
        $reviewer = User::factory()->reviewer()->create();
        $this->assign($abstract, $reviewer);

        $response = $this->actingAs($reviewer)->get(route('admin.abstracts.show', $abstract));
        $response->assertOk();

        $payload = json_encode($response->viewData('page')['props']);

        $this->assertStringNotContainsString(self::AUTHOR_NAME, $payload);
        $this->assertStringNotContainsString(self::AUTHOR_EMAIL, $payload);
        $this->assertStringNotContainsString(self::AUTHOR_INSTITUTION, $payload);
        $this->assertStringNotContainsString('+255700111222', $payload);
    }

    public function test_a_reviewer_sees_anonymised_authors_but_keeps_the_count_and_presenter(): void
    {
        $abstract = $this->submission();
        $reviewer = User::factory()->reviewer()->create();
        $this->assign($abstract, $reviewer);

        $this->actingAs($reviewer)
            ->get(route('admin.abstracts.show', $abstract))
            ->assertInertia(fn (Assert $page) => $page
                ->where('submission.is_blinded', true)
                ->missing('submission.user')
                ->missing('submission.user_id')
                ->has('submission.authors', 2)
                ->where('submission.authors.0.name', 'Author 1')
                ->where('submission.authors.0.institution', null)
                ->where('submission.authors.0.is_presenter', true)
                ->where('submission.authors.1.name', 'Author 2')
                ->where('submission.authors.1.is_presenter', false)
            );
    }

    public function test_the_review_history_shows_roles_not_names_to_a_reviewer(): void
    {
        $abstract = $this->submission();
        $reviewer = User::factory()->reviewer()->create();
        $this->assign($abstract, $reviewer);

        $this->actingAs($reviewer)
            ->get(route('admin.abstracts.show', $abstract))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submission.review_history', 1)
                ->where('submission.review_history.0.action', 'submitted')
                ->where('submission.review_history.0.actor.name', 'Author')
            );
    }

    public function test_the_browse_list_hides_the_author_from_reviewers(): void
    {
        $abstract = $this->submission();
        $reviewer = User::factory()->reviewer()->create();
        $this->assign($abstract, $reviewer);

        $response = $this->actingAs($reviewer)->get(route('admin.abstracts.index'));
        $response->assertOk();

        $payload = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString(self::AUTHOR_NAME, $payload);
        $this->assertStringNotContainsString(self::AUTHOR_EMAIL, $payload);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('isBlinded', true)
            ->missing('submissions.data.0.user')
        );
    }

    /** Searching by author name would let a reviewer probe for the identity. */
    public function test_a_reviewer_cannot_search_abstracts_by_author(): void
    {
        $abstract = $this->submission();
        $reviewer = User::factory()->reviewer()->create();
        $this->assign($abstract, $reviewer);

        $this->actingAs($reviewer)
            ->get(route('admin.abstracts.index', ['search' => self::AUTHOR_NAME]))
            ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 0));

        // The same search still works for an admin.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.abstracts.index', ['search' => self::AUTHOR_NAME]))
            ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 1));
    }

    public function test_the_reviewer_invitation_email_does_not_name_the_author(): void
    {
        $abstract = $this->submission();
        $rendered = (new AbstractReviewRequested($abstract, false))->render();

        $this->assertStringNotContainsString(self::AUTHOR_NAME, $rendered);
        $this->assertStringContainsString('Herbal Innovations', $rendered);
    }

    /** Uploaded files are routinely named after their author. */
    public function test_a_reviewer_cannot_download_or_see_the_presentation_filename(): void
    {
        $abstract = $this->submission();
        $reviewer = User::factory()->reviewer()->create();
        $this->assign($abstract, $reviewer);

        $abstract->forceFill([
            'status' => 'accepted',
            'presentation_file' => 'presentations/x.pdf',
            'presentation_original_name' => self::AUTHOR_NAME.' poster.pdf',
            'presentation_status' => 'uploaded',
        ])->save();

        $this->actingAs($reviewer)
            ->get(route('admin.abstracts.show', $abstract))
            ->assertInertia(fn (Assert $page) => $page->where('submission.presentation_original_name', 'Presentation file'));

        $this->actingAs($reviewer)
            ->get(route('admin.abstracts.presentation.download', $abstract))
            ->assertForbidden();
    }

    /** Admins decide, and need identity to spot author/reviewer conflicts of interest. */
    public function test_admins_still_see_the_author_in_full(): void
    {
        $abstract = $this->submission();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.abstracts.show', $abstract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('submission.user.name', self::AUTHOR_NAME)
                ->where('submission.user.email', self::AUTHOR_EMAIL)
                ->where('submission.authors.0.name', self::AUTHOR_NAME)
                ->where('submission.authors.0.institution', self::AUTHOR_INSTITUTION)
                ->where('submission.review_history.0.actor.name', self::AUTHOR_NAME)
                ->missing('submission.is_blinded')
            );
    }

    /** The other direction was already blind and must stay that way. */
    public function test_reviewers_still_cannot_see_each_others_recommendations(): void
    {
        $abstract = $this->submission();
        $reviewerA = User::factory()->reviewer()->create();
        $this->assign($abstract, $reviewerA);
        $reviewerB = User::find($abstract->reviewer_two_id);

        $abstract->reviewerDecisions()->create([
            'reviewer_id' => $reviewerB->id,
            'recommendation' => 'rejected',
            'decided_at' => now(),
        ]);

        $this->actingAs($reviewerA)
            ->get(route('admin.abstracts.show', $abstract))
            ->assertInertia(fn (Assert $page) => $page
                ->where('submission.reviewer_decisions.0.recommendation', null)
                ->has('submission.reviewer_decisions.0.comments', 0)
            );
    }
}

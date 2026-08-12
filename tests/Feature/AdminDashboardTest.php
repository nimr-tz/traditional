<?php

namespace Tests\Feature;

use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_reviewer_gets_the_scoped_reviewer_dashboard_not_the_admin_one(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/reviewer-dashboard')
                ->has('stats', fn (Assert $stats) => $stats
                    ->has('assigned_total')
                    ->has('awaiting_my_decision')
                    ->has('reviewed_by_me')
                    ->has('revisions_i_requested')
                    ->has('acceptances_i_recommended')
                )
                ->has('reviewQueue')
                ->missing('stats.total_registrants')
                ->missing('stats.paid')
                ->missing('stats.students_pending')
                ->missing('studentQueue')
            );
    }

    public function test_a_reviewers_queue_only_contains_abstracts_assigned_to_them(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $otherReviewer = User::factory()->reviewer()->create();
        $thirdReviewer = User::factory()->reviewer()->create();
        $subtheme = Subtheme::create(['title' => 'Policy', 'active' => true, 'sort_order' => 1]);
        $author = User::factory()->create();

        $makeSubmission = fn (array $overrides) => AbstractSubmission::create(array_merge([
            'user_id' => $author->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Untitled '.uniqid(),
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background.',
            'objective' => 'Objective.',
            'methods' => 'Methods.',
            'results' => 'Results.',
            'conclusion' => 'Conclusion.',
            'presentation_type' => 'poster',
            'status' => 'submitted',
        ], $overrides));

        $assignedToMe = $makeSubmission(['reviewer_one_id' => $reviewer->id, 'reviewer_two_id' => $otherReviewer->id]);
        $makeSubmission(['reviewer_one_id' => $otherReviewer->id, 'reviewer_two_id' => $thirdReviewer->id]);
        $makeSubmission([]);

        $response = $this->actingAs($reviewer)->get(route('admin.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/reviewer-dashboard')
            ->where('stats.assigned_total', 1)
            ->where('stats.awaiting_my_decision', 1)
            ->where('reviewQueue.0.id', $assignedToMe->id)
        );
    }

    /**
     * The dashboard queue was the one surface that handed a reviewer the author:
     * it eager-loaded `user` and serialised name and institution straight into
     * the Inertia payload, where anyone can read them in the page source.
     */
    public function test_the_reviewer_dashboard_never_carries_the_author(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $subtheme = Subtheme::create(['title' => 'Policy', 'active' => true, 'sort_order' => 1]);
        $author = User::factory()->create(['name' => 'Prof Identifiable', 'institution' => 'Very Distinctive Institute']);

        AbstractSubmission::create([
            'user_id' => $author->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'An abstract under review',
            'authors' => [['name' => 'Prof Identifiable', 'institution' => 'Very Distinctive Institute', 'is_presenter' => true]],
            'background' => 'Background.', 'objective' => 'Objective.', 'methods' => 'Methods.',
            'results' => 'Results.', 'conclusion' => 'Conclusion.',
            'presentation_type' => 'poster', 'status' => 'submitted',
            'reviewer_one_id' => $reviewer->id,
        ]);

        $response = $this->actingAs($reviewer)->get(route('admin.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('reviewQueue.0.is_blinded', true)
            ->missing('reviewQueue.0.user')
            ->missing('reviewQueue.0.user_id')
            ->where('reviewQueue.0.authors.0.name', 'Author 1')
            ->where('reviewQueue.0.authors.0.institution', null)
            ->etc());

        // Nothing identifying anywhere in the rendered payload.
        $response->assertDontSee('Prof Identifiable');
        $response->assertDontSee('Very Distinctive Institute');
    }

    /**
     * A reviewer who asked for a revision had already "decided", so the revised
     * abstract never returned to their queue — the round is what makes the
     * earlier opinion stale.
     */
    public function test_a_revised_abstract_comes_back_to_the_reviewer_who_asked_for_it(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $subtheme = Subtheme::create(['title' => 'Policy', 'active' => true, 'sort_order' => 1]);
        $author = User::factory()->create();

        $abstract = AbstractSubmission::create([
            'user_id' => $author->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Needs work',
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background.', 'objective' => 'Objective.', 'methods' => 'Methods.',
            'results' => 'Results.', 'conclusion' => 'Conclusion.',
            'presentation_type' => 'oral', 'status' => 'submitted',
            'reviewer_one_id' => $reviewer->id,
        ]);

        // Round 1: the reviewer asks for a revision.
        $abstract->reviewerDecisions()->create([
            'reviewer_id' => $reviewer->id,
            'round' => 1,
            'recommendation' => 'revision_requested',
            'decided_at' => now(),
        ]);
        $abstract->forceFill(['status' => 'revision_requested'])->save();

        $this->actingAs($reviewer)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.awaiting_my_decision', 0)
                ->where('stats.revisions_i_requested', 1)
                ->etc());

        // The author revises: the abstract moves to round 2 and is submitted again.
        $abstract->forceFill(['status' => 'submitted', 'review_round' => 2, 'resubmitted_at' => now()])->save();

        $this->actingAs($reviewer)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.awaiting_my_decision', 1)
                ->where('stats.reviewed_by_me', 0)
                // Still a record of what they sent back, even though the status moved on.
                ->where('stats.revisions_i_requested', 1)
                ->where('reviewQueue.0.id', $abstract->id)
                ->etc());
    }

    /** Awaiting plus reviewed must account for everything assigned. */
    public function test_the_tiles_tally_against_what_is_assigned(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $subtheme = Subtheme::create(['title' => 'Policy', 'active' => true, 'sort_order' => 1]);
        $author = User::factory()->create();

        $make = fn (array $overrides = []) => AbstractSubmission::create(array_merge([
            'user_id' => $author->id,
            'subtheme_id' => $subtheme->id,
            'title' => 'Untitled '.uniqid(),
            'authors' => [['name' => 'A. Author', 'institution' => 'NIMR', 'is_presenter' => true]],
            'background' => 'Background.', 'objective' => 'Objective.', 'methods' => 'Methods.',
            'results' => 'Results.', 'conclusion' => 'Conclusion.',
            'presentation_type' => 'poster', 'status' => 'submitted',
            'reviewer_one_id' => $reviewer->id,
        ], $overrides));

        $make();
        $make();
        $decided = $make();

        $decided->reviewerDecisions()->create([
            'reviewer_id' => $reviewer->id,
            'round' => 1,
            'recommendation' => 'accepted',
            'decided_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.assigned_total', 3)
                ->where('stats.awaiting_my_decision', 2)
                ->where('stats.reviewed_by_me', 1)
                ->where('stats.acceptances_i_recommended', 1)
                ->where('stats.revisions_i_requested', 0)
                ->etc());
    }

    public function test_admin_still_gets_the_full_conference_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->has('stats.total_registrants')
                ->has('stats.students_pending')
                ->has('studentQueue')
            );
    }
}

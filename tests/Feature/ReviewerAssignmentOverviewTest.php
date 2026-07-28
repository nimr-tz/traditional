<?php

namespace Tests\Feature;

use App\Models\AbstractSubmission;
use App\Models\Subtheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewerAssignmentOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function submission(array $overrides = []): AbstractSubmission
    {
        $subtheme = Subtheme::firstOrCreate(['title' => 'Innovations'], ['active' => true, 'sort_order' => 1]);

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

    private function assign(User $admin, AbstractSubmission $abstract, User $one, User $two): void
    {
        $this->actingAs($admin)->post(route('admin.abstracts.reviewers.assign', $abstract), [
            'reviewer_one_id' => $one->id,
            'reviewer_two_id' => $two->id,
        ])->assertRedirect();
    }

    private function recommend(User $reviewer, AbstractSubmission $abstract, string $recommendation = 'revision_requested'): void
    {
        $this->actingAs($reviewer)->post(route('admin.abstracts.reviewer-decision', $abstract), [
            'recommendation' => $recommendation,
            'comments' => [['section' => null, 'body' => 'Needs work.']],
        ])->assertRedirect();
    }

    public function test_the_pipeline_counts_every_stage_of_review(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $reviewerA = User::factory()->reviewer()->create(['name' => 'Reviewer A']);
        $reviewerB = User::factory()->reviewer()->create(['name' => 'Reviewer B']);

        $this->submission(['title' => 'Nobody assigned']);

        $awaiting = $this->submission(['title' => 'Half reviewed']);
        $this->assign($admin, $awaiting, $reviewerA, $reviewerB);
        $this->recommend($reviewerA, $awaiting);

        $ready = $this->submission(['title' => 'Both reviewed']);
        $this->assign($admin, $ready, $reviewerA, $reviewerB);
        $this->recommend($reviewerA, $ready);
        $this->recommend($reviewerB, $ready);

        $this->submission(['title' => 'Already accepted', 'status' => 'accepted']);

        $this->actingAs($admin)
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/assignments/index')
                ->where('stats.total', 4)
                ->where('stats.unassigned', 1)
                ->where('stats.awaiting_reviews', 1)
                ->where('stats.ready_for_decision', 1)
                ->where('stats.decided', 1)
            );
    }

    /**
     * Admins hold ABSTRACT_REVIEWER_ROLES too, so an admin is themselves an
     * eligible reviewer and appears in the workload list — the same list the
     * abstract detail page offers. Names are pinned because the list is
     * ordered by name.
     */
    public function test_reviewer_workload_separates_assigned_pending_and_submitted(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['name' => 'Zuhura Admin']);
        $reviewerA = User::factory()->reviewer()->create(['name' => 'Aaliyah Reviewer']);
        $reviewerB = User::factory()->reviewer()->create(['name' => 'Baraka Reviewer']);

        $first = $this->submission(['title' => 'First']);
        $second = $this->submission(['title' => 'Second']);

        $this->assign($admin, $first, $reviewerA, $reviewerB);
        $this->assign($admin, $second, $reviewerA, $reviewerB);

        // A has recommended on one of their two; B has recommended on neither.
        $this->recommend($reviewerA, $first);

        $this->actingAs($admin)
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reviewers', 3)
                ->where('reviewers.0.name', 'Aaliyah Reviewer')
                ->where('reviewers.0.assigned_count', 2)
                ->where('reviewers.0.awaiting_count', 1)
                ->where('reviewers.0.decisions_count', 1)
                ->where('reviewers.1.name', 'Baraka Reviewer')
                ->where('reviewers.1.assigned_count', 2)
                ->where('reviewers.1.awaiting_count', 2)
                ->where('reviewers.1.decisions_count', 0)
                // The admin is eligible but carries nothing.
                ->where('reviewers.2.name', 'Zuhura Admin')
                ->where('reviewers.2.assigned_count', 0)
                ->where('reviewers.2.awaiting_count', 0)
            );
    }

    /** A decided abstract stops counting against a reviewer — there's nothing left for them to do. */
    public function test_a_decided_abstract_no_longer_counts_as_pending_for_its_reviewers(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['name' => 'Zuhura Admin']);
        $reviewerA = User::factory()->reviewer()->create(['name' => 'Aaliyah Reviewer']);
        $reviewerB = User::factory()->reviewer()->create(['name' => 'Baraka Reviewer']);

        $abstract = $this->submission();
        $this->assign($admin, $abstract, $reviewerA, $reviewerB);

        $this->actingAs($admin)
            ->get(route('admin.assignments.index'))
            ->assertInertia(fn (Assert $page) => $page->where('reviewers.0.awaiting_count', 1));

        $abstract->forceFill(['status' => 'rejected'])->save();

        $this->actingAs($admin)
            ->get(route('admin.assignments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviewers.0.awaiting_count', 0)
                ->where('reviewers.0.assigned_count', 1)
            );
    }

    public function test_the_list_can_be_filtered_to_a_single_stage(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $this->submission(['title' => 'Nobody assigned']);
        $assigned = $this->submission(['title' => 'Has reviewers']);
        $this->assign($admin, $assigned, $reviewerA, $reviewerB);

        $this->actingAs($admin)
            ->get(route('admin.assignments.index', ['stage' => 'unassigned']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.title', 'Nobody assigned')
                ->where('submissions.data.0.stage', 'unassigned')
            );

        $this->actingAs($admin)
            ->get(route('admin.assignments.index', ['stage' => 'awaiting_reviews']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.title', 'Has reviewers')
                ->where('submissions.data.0.stage', 'awaiting_reviews')
            );
    }

    public function test_the_list_can_be_filtered_to_one_reviewer(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();
        $reviewerC = User::factory()->reviewer()->create();

        $mine = $this->submission(['title' => 'Assigned to A']);
        $this->assign($admin, $mine, $reviewerA, $reviewerB);

        $theirs = $this->submission(['title' => 'Not assigned to A']);
        $this->assign($admin, $theirs, $reviewerB, $reviewerC);

        $this->actingAs($admin)
            ->get(route('admin.assignments.index', ['reviewer_id' => $reviewerA->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.title', 'Assigned to A')
            );
    }

    /** The page flags which reviewer has already recommended, so admins can chase the right person. */
    public function test_each_row_reports_which_reviewers_have_recommended(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();

        $abstract = $this->submission();
        $this->assign($admin, $abstract, $reviewerA, $reviewerB);
        $this->recommend($reviewerA, $abstract);

        $this->actingAs($admin)
            ->get(route('admin.assignments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('submissions.data.0.decided_reviewer_ids', [$reviewerA->id])
                ->where('submissions.data.0.decisions_count', 1)
            );
    }

    /**
     * Reviewers see only the abstracts assigned to them (admin/abstracts);
     * who reviews what is an admin decision, so the console stays closed to
     * them even though they're part of the workflow it describes.
     */
    public function test_reviewers_and_registrants_cannot_reach_the_assignment_console(): void
    {
        $this->actingAs(User::factory()->reviewer()->create())
            ->get(route('admin.assignments.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.assignments.index'))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.assignments.index'))->assertRedirect(route('login'));
    }

    public function test_a_super_admin_can_reach_the_assignment_console(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.assignments.index'))
            ->assertOk();
    }

    /**
     * Abstracts predating the five-section split have their whole text in
     * `background` and null in the other four columns, which the detail page
     * used to call .trim() on — throwing in the browser and rendering a blank
     * page. The server response was always fine, so only asserting the payload
     * would have missed it; this at least pins that nulls reach the page and
     * are expected there.
     */
    public function test_an_abstract_with_unfilled_sections_still_renders(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $legacy = $this->submission([
            'title' => 'Pre-split submission',
            'background' => 'The entire original abstract text.',
            'objective' => null,
            'methods' => null,
            'results' => null,
            'conclusion' => null,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.abstracts.show', $legacy))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/abstracts/show')
                ->where('submission.background', 'The entire original abstract text.')
                ->where('submission.objective', null)
                ->where('submission.methods', null)
                ->where('submission.results', null)
                ->where('submission.conclusion', null)
            );

        // The author's own edit page reads the same columns and had the same bug.
        $this->actingAs($legacy->user)
            ->get(route('abstracts.edit', $legacy))
            ->assertOk();
    }

    /**
     * A super admin is an eligible reviewer and makes the final call on every
     * abstract, so both the browse list and the full read-and-decide page have
     * to be reachable by them — for a while the sidebar linked to neither.
     */
    public function test_a_super_admin_can_read_any_abstract_in_full(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $abstract = $this->submission(['title' => 'Readable end to end']);

        $this->actingAs($superAdmin)
            ->get(route('admin.abstracts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/abstracts/index')
                ->has('submissions.data', 1)
            );

        $this->actingAs($superAdmin)
            ->get(route('admin.abstracts.show', $abstract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/abstracts/show')
                ->where('submission.title', 'Readable end to end')
                ->where('submission.background', 'Background.')
                ->where('submission.objective', 'Objective.')
                ->where('submission.methods', 'Methods.')
                ->where('submission.results', 'Results.')
                ->where('submission.conclusion', 'Conclusion.')
                ->has('submission.authors')
                ->has('submission.review_history')
                ->has('submission.reviewer_decisions')
                // Full recommendations and comments, not the blind-review redaction.
                ->has('eligibleReviewers')
            );
    }
}

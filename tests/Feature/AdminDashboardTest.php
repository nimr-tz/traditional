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
                    ->has('accepted')
                    ->has('revision_requested')
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
